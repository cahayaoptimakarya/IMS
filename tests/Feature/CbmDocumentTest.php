<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\Item;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Cbm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CbmDocumentTest extends TestCase
{
    use RefreshDatabase;

    private int $from;
    private int $to;
    private int $itemWithDims;
    private int $itemWithoutDims;
    private int $trId;
    private int $shipmentId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->from = DB::table('warehouses')->insertGetId(['name' => 'Gudang Nanggewer']);
        $this->to = DB::table('warehouses')->insertGetId(['name' => 'Gudang Seha']);
        DB::table('uoms')->insert(['id' => 1, 'name' => 'Pieces', 'symbol' => 'pcs']);
        DB::table('item_types')->insert(['id' => 1, 'name' => 'Barang']);

        // 60 x 40 x 30,5 cm = 0,0732 m³ per koli, isi 12 pcs per koli
        $this->itemWithDims = DB::table('items')->insertGetId([
            'sku' => 'SKU-DIM', 'nama_barang' => 'Barang Berdimensi', 'uom_id' => 1, 'item_type_id' => 1, 'koli' => 12,
            'panjang_koli' => 60, 'lebar_koli' => 40, 'tinggi_koli' => 30.5,
        ]);
        $this->itemWithoutDims = DB::table('items')->insertGetId([
            'sku' => 'SKU-NODIM', 'nama_barang' => 'Barang Tanpa Dimensi', 'uom_id' => 1, 'item_type_id' => 1, 'koli' => 10,
        ]);
        foreach ([$this->itemWithDims, $this->itemWithoutDims] as $itemId) {
            DB::table('inventories')->insert(['item_id' => $itemId, 'warehouse_id' => $this->from, 'quantity' => 500, 'koli' => 50]);
        }

        $this->trId = DB::table('transfer_requests')->insertGetId([
            'code' => 'TR-20261008-0001', 'date' => '2026-10-08', 'status' => 'on_progress',
            'from_warehouse_id' => $this->from, 'to_warehouse_id' => $this->to,
        ]);
        $triDims = DB::table('transfer_request_items')->insertGetId([
            'transfer_request_id' => $this->trId, 'item_id' => $this->itemWithDims, 'quantity' => 24, 'koli' => 2,
            'ship_remaining_quantity' => 12, 'ship_remaining_koli' => 1,
        ]);
        $triNoDims = DB::table('transfer_request_items')->insertGetId([
            'transfer_request_id' => $this->trId, 'item_id' => $this->itemWithoutDims, 'quantity' => 10, 'koli' => 1,
        ]);

        $this->shipmentId = DB::table('shipments')->insertGetId([
            'code' => 'SHIP-20261008-0001', 'reference_type' => Shipment::REFERENCE_TYPE_TRANSFER_REQUEST,
            'shipping_date' => '2026-10-08', 'from_warehouse_id' => (string) $this->from, 'to_warehouse_id' => (string) $this->to,
            'vehicle_type' => 'Truk', 'license_plate' => 'B 1234 CD', 'driver_name' => 'Budi', 'status' => 'packing',
        ]);
        DB::table('shipment_item_details')->insert([
            ['shipment_id' => $this->shipmentId, 'item_id' => $this->itemWithDims, 'reference_id' => $triDims,
                'reference_type' => 'transfer_request_items', 'quantity_shipped' => 12, 'koli_shipped' => 1],
            ['shipment_id' => $this->shipmentId, 'item_id' => $this->itemWithoutDims, 'reference_id' => $triNoDims,
                'reference_type' => 'transfer_request_items', 'quantity_shipped' => 10, 'koli_shipped' => 1],
        ]);

        $this->withoutMiddleware(PermissionMiddleware::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_line_cbm_uses_koli_and_falls_back_to_quantity(): void
    {
        $item = Item::findOrFail($this->itemWithDims);

        $this->assertEqualsWithDelta(0.1464, Cbm::forLine($item, 2, 24), 1e-9);
        $this->assertEqualsWithDelta(0.0366, Cbm::forLine($item, 0, 6), 1e-9);   // 6 pcs x 0,0061
        $this->assertSame(0.0, Cbm::forLine($item, 0, 0));
        $this->assertNull(Cbm::forLine(Item::findOrFail($this->itemWithoutDims), 1, 10));
        $this->assertNull(Cbm::forLine(null, 1, 10));
    }

    public function test_summary_and_format(): void
    {
        $items = Item::findMany([$this->itemWithDims, $this->itemWithoutDims])->keyBy('id');
        $rows = [[$items[$this->itemWithDims], 2, 24], [$items[$this->itemWithoutDims], 1, 10]];

        $summary = Cbm::summarize($rows, fn ($r) => $r);
        $this->assertEqualsWithDelta(0.1464, $summary['total'], 1e-9);
        $this->assertSame(1, $summary['missing']);

        // Item tanpa dimensi tidak ikut dihitung: total tetap angka, bukan null
        $this->assertSame(['total' => 0.0, 'missing' => 1], Cbm::summarize([$rows[1]], fn ($r) => $r));
        $this->assertSame(['total' => 0.0, 'missing' => 0], Cbm::summarize([], fn ($r) => $r));

        $this->assertSame('0,146', Cbm::format(0.1464));
        $this->assertSame('1.234,568', Cbm::format(1234.5678));
        $this->assertSame('0,0004', Cbm::format(0.0004));
        $this->assertSame('0,000', Cbm::format(0.0));
        $this->assertSame('-', Cbm::format(null));
    }

    public function test_request_transfer_pages_show_cbm(): void
    {
        $this->get(route('admin.stok-masuk.request-transfer.show', $this->trId))
            ->assertOk()
            ->assertSee('CBM (m³)', false)
            ->assertSee('0,146')                 // total request (item tanpa dimensi tidak dihitung)
            ->assertSee('0,073')                 // sisa kirim & pengiriman
            ->assertDontSee('belum memiliki dimensi koli', false);

        $this->get(route('admin.stok-masuk.request-transfer.create'))
            ->assertOk()
            ->assertSee('id="cbm-summary"', false)
            ->assertSee('window.ImsCbm', false);

        DB::table('transfer_requests')->where('id', $this->trId)->update(['status' => 'pending']);
        $this->get(route('admin.stok-masuk.request-transfer.edit', $this->trId))
            ->assertOk()
            ->assertSee('id="cbm-summary"', false)
            ->assertSee('"panjang_koli":60', false);
    }

    public function test_permintaan_barang_detail_and_ship_modal_data_include_cbm(): void
    {
        $this->get(route('admin.stok-keluar.permintaan-barang.show', $this->trId))
            ->assertOk()
            ->assertSee('CBM (m³)', false)
            ->assertSee('0,146')
            ->assertSee('0,073 m³', false);

        $html = $this->get(route('admin.stok-keluar.permintaan-barang.index'))
            ->assertOk()
            ->assertSee('id="shipment-cbm-summary"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'window.ImsCbm = '));

        $this->getJson(route('admin.stok-keluar.permintaan-barang.items-to-ship', $this->trId))
            ->assertOk()
            ->assertJsonPath('0.item.panjang_koli', 60)
            ->assertJsonPath('0.item.tinggi_koli', 30.5);
    }

    public function test_surat_jalan_shows_cbm_per_line_and_total(): void
    {
        $this->get(route('admin.riwayat-pengiriman.show', $this->shipmentId))
            ->assertOk()
            ->assertSee('CBM (m³)', false)
            ->assertSee('Total CBM')
            ->assertSee('0,073 m³', false)
            ->assertDontSee('0,073 m³*', false)
            ->assertDontSee('belum memiliki dimensi koli', false);

        $this->get(route('admin.transfergudang.surat-jalan.show', $this->trId))
            ->assertOk()
            ->assertSee('CBM (m³)', false)
            ->assertSee('0,073');

        $this->get(route('admin.riwayat-pengiriman.export-excel', $this->shipmentId))
            ->assertOk()
            ->assertDownload('Surat-Jalan-SHIP-20261008-0001.xlsx');

        $shipment = Shipment::with(['itemDetails.item'])->findOrFail($this->shipmentId);
        $html = view('admin.riwayat_pengiriman.export_excel', [
            'shipment' => $shipment, 'referenceCode' => 'TR-20261008-0001',
            'fromWarehouse' => 'Gudang Nanggewer', 'toWarehouse' => 'Gudang Seha',
        ])->render();
        $this->assertStringContainsString('CBM (m³)', $html);
        $this->assertStringContainsString('0,073 m³', $html);
    }

    public function test_document_without_any_dimensions_still_shows_cbm_total(): void
    {
        DB::table('items')->update(['panjang_koli' => null, 'lebar_koli' => null, 'tinggi_koli' => null]);

        $this->get(route('admin.riwayat-pengiriman.show', $this->shipmentId))
            ->assertOk()
            ->assertSee('Total CBM')
            ->assertSee('0,000 m³', false)
            ->assertDontSee('belum memiliki dimensi koli', false);

        $this->get(route('admin.stok-masuk.request-transfer.show', $this->trId))
            ->assertOk()
            ->assertSee('0,000')
            ->assertDontSee('belum memiliki dimensi koli', false);
    }

    public function test_edit_pengiriman_exposes_cbm_per_koli(): void
    {
        $this->get(route('admin.riwayat-pengiriman.edit', $this->shipmentId))
            ->assertOk()
            ->assertSee('data-cbm-koli="0.0732"', false)
            ->assertSee('id="cbm-summary"', false);
    }

    public function test_pengadaan_pages_show_cbm(): void
    {
        $sioId = DB::table('stock_in_orders')->insertGetId([
            'code' => 'IN-20261008-0001', 'date' => '2026-10-08', 'warehouse_id' => $this->to,
            'type' => 'import', 'status' => 'requested',
        ]);
        DB::table('stock_in_order_items')->insert([
            ['stock_in_order_id' => $sioId, 'item_id' => $this->itemWithDims, 'quantity' => 120, 'koli' => 10,
                'remaining_quantity' => 120, 'remaining_koli' => 10],
            ['stock_in_order_id' => $sioId, 'item_id' => $this->itemWithoutDims, 'quantity' => 10, 'koli' => 1,
                'remaining_quantity' => 10, 'remaining_koli' => 1],
        ]);

        $this->get(route('admin.stok-masuk.pengadaan.show', $sioId))
            ->assertOk()
            ->assertSee('CBM (m³)', false)
            ->assertSee('0,732')
            ->assertDontSee('belum memiliki dimensi koli', false);

        $this->get(route('admin.stok-masuk.pengadaan.create'))
            ->assertOk()
            ->assertSee('id="cbm-summary"', false);

        $this->get(route('admin.stok-masuk.pengadaan.edit', $sioId))
            ->assertOk()
            ->assertSee('data-cbm-koli="0.0732"', false);
    }
}
