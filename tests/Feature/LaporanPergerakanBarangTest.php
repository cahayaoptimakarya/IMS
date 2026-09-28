<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanPergerakanBarangTest extends TestCase
{
    use RefreshDatabase;

    private int $nanggewer;
    private int $seha;
    private int $trSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-28 10:00:00');

        $this->seha = DB::table('warehouses')->insertGetId(['name' => 'Gudang Seha']);
        $this->nanggewer = DB::table('warehouses')->insertGetId(['name' => 'Gudang Nanggewer']);
        DB::table('uoms')->insert(['id' => 1, 'name' => 'Pieces', 'symbol' => 'pcs']);
        DB::table('item_types')->insert(['id' => 1, 'name' => 'Barang']);

        $this->withoutMiddleware(PermissionMiddleware::class);
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function item(string $sku, float $stock = 0, ?int $warehouseId = null): int
    {
        $id = DB::table('items')->insertGetId([
            'sku' => $sku, 'nama_barang' => 'Item ' . $sku, 'uom_id' => 1, 'item_type_id' => 1,
        ]);
        DB::table('inventories')->insert([
            'item_id' => $id, 'warehouse_id' => $warehouseId ?? $this->nanggewer, 'quantity' => $stock,
        ]);

        return $id;
    }

    /** Satu dokumen permintaan barang dengan satu pengiriman. */
    private function ship(int $itemId, float $qty, string $date, ?int $fromWarehouse = null, ?int $trId = null): int
    {
        $from = $fromWarehouse ?? $this->nanggewer;
        $trId ??= DB::table('transfer_requests')->insertGetId([
            'code' => 'TR-' . (++$this->trSeq), 'date' => $date,
            'from_warehouse_id' => $from, 'to_warehouse_id' => $this->seha, 'status' => 'on_progress',
        ]);
        $triId = DB::table('transfer_request_items')->insertGetId([
            'transfer_request_id' => $trId, 'item_id' => $itemId, 'quantity' => $qty,
        ]);
        $shipmentId = DB::table('shipments')->insertGetId([
            'code' => 'SHIP-' . $triId, 'reference_type' => 'transfer request', 'shipping_date' => $date,
            'from_warehouse_id' => (string) $from, 'to_warehouse_id' => (string) $this->seha, 'status' => 'packing',
        ]);
        DB::table('shipment_item_details')->insert([
            'shipment_id' => $shipmentId, 'item_id' => $itemId, 'reference_id' => $triId,
            'reference_type' => 'transfer_request_items', 'quantity_shipped' => $qty,
        ]);

        return $trId;
    }

    private function fetch(array $params = []): array
    {
        return $this->getJson(route('admin.laporan.pergerakanBarang.data', $params + [
            'date_range' => '30/08/2026 - 28/09/2026',
            'length' => 100,
        ]))->assertOk()->json();
    }

    public function test_classifies_by_cumulative_contribution_and_defaults_to_nanggewer(): void
    {
        $a = $this->item('A', 300);
        $b = $this->item('B', 50);
        $c = $this->item('C', 10);
        $d = $this->item('D', 5);
        $this->item('E', 40);   // ada stok tanpa keluar -> non moving
        $this->item('F', 0);    // tanpa stok & tanpa keluar -> tidak ditampilkan

        $tr = $this->ship($a, 600, '2026-09-10');
        $this->ship($a, 150, '2026-09-20', null, $tr); // dokumen sama -> frequency tetap 1
        $this->ship($b, 150, '2026-09-15');
        $this->ship($c, 80, '2026-09-16');
        $this->ship($d, 20, '2026-09-17');
        $this->ship($d, 999, '2026-09-17', $this->seha); // gudang lain diabaikan
        $this->ship($c, 999, '2026-07-01');             // di luar periode diabaikan

        $json = $this->fetch();
        $rows = collect($json['data'])->keyBy('sku');

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], collect($json['data'])->pluck('sku')->all());
        // total 1000: A 75% (mulai di 0% -> fast), B 15% (mulai 75%), C 8% (mulai 90%), D 2%
        $this->assertSame('fast', $rows['A']['classification']);
        $this->assertSame('medium', $rows['B']['classification']);
        $this->assertSame('slow', $rows['C']['classification']);
        $this->assertSame('slow', $rows['D']['classification']);
        $this->assertSame('non_moving', $rows['E']['classification']);

        $this->assertEquals(750, $rows['A']['qty_out']);
        $this->assertEquals(75, $rows['A']['contribution']);
        $this->assertEquals(1, $rows['A']['frequency']);
        $this->assertEquals(25, $rows['A']['avg_per_day']);      // 750 / 30 hari
        $this->assertEquals(12, $rows['A']['days_cover']);       // 300 / 25
        $this->assertSame('2026-09-20', $rows['A']['last_out_date']);
        $this->assertEquals(300, $rows['A']['stock']);
        $this->assertSame('2026-09-16', $rows['C']['last_out_date']);
        $this->assertNull($rows['E']['last_out_date']);
        $this->assertNull($rows['E']['days_cover']);
        $this->assertEquals(0, $rows['E']['frequency']);

        $this->assertEquals(1000, $json['summary']['total_out']);
        $this->assertEquals(1, $json['summary']['classes']['fast']['sku_count']);
        $this->assertEquals(1, $json['summary']['classes']['non_moving']['sku_count']);
    }

    public function test_filters_by_classification_warehouse_and_search(): void
    {
        $a = $this->item('A', 10);
        $b = $this->item('B', 10);
        $this->item('E', 10);
        $this->ship($a, 90, '2026-09-10');
        $this->ship($b, 10, '2026-09-11');

        $slow = $this->fetch(['class_filter' => 'slow']);
        $this->assertSame(['B'], collect($slow['data'])->pluck('sku')->all());
        $this->assertSame(3, $slow['recordsTotal']);

        $search = $this->fetch(['search' => ['value' => 'item e']]);
        $this->assertSame(['E'], collect($search['data'])->pluck('sku')->all());

        $seha = $this->fetch(['warehouse_filter' => $this->seha]);
        $this->assertSame([], $seha['data']);
    }

    public function test_filters_by_days_cover_band_and_custom_range(): void
    {
        // periode 30 hari -> rata-rata/hari = qty keluar / 30
        $critical = $this->item('KRITIS', 10);   // avg 3 -> 3,3 hari
        $warning = $this->item('WASPADA', 60);   // avg 3 -> 20 hari
        $safe = $this->item('AMAN', 150);        // avg 3 -> 50 hari
        $excess = $this->item('LEBIH', 600);     // avg 3 -> 200 hari
        $this->item('DIAM', 10);                 // non moving
        foreach ([$critical, $warning, $safe, $excess] as $id) {
            $this->ship($id, 90, '2026-09-10');
        }

        $skus = fn (array $params) => collect($this->fetch($params)['data'])->pluck('sku')->sort()->values()->all();

        $this->assertSame(['KRITIS'], $skus(['cover_filter' => 'kritis']));
        $this->assertSame(['WASPADA'], $skus(['cover_filter' => 'waspada']));
        $this->assertSame(['AMAN'], $skus(['cover_filter' => 'aman']));
        $this->assertSame(['LEBIH'], $skus(['cover_filter' => 'berlebih']));
        $this->assertSame(['DIAM'], $skus(['cover_filter' => 'tidak_ada']));
        $this->assertSame(['AMAN', 'WASPADA'], $skus(['cover_filter' => 'custom', 'cover_min' => 20, 'cover_max' => 50]));
        $this->assertSame(['AMAN', 'LEBIH'], $skus(['cover_filter' => 'custom', 'cover_min' => 21]));
        $this->assertCount(5, $skus(['cover_filter' => 'custom']));
    }

    public function test_summary_cards_follow_days_cover_filter_but_not_class_filter(): void
    {
        $a = $this->item('A', 10);    // 750 keluar -> fast, avg 25/hari -> 0,4 hari (kritis)
        $b = $this->item('B', 1000);  // 250 keluar -> medium, avg 8,3/hari -> 120 hari (berlebih)
        $this->item('E', 40);         // non moving
        $this->ship($a, 750, '2026-09-10');
        $this->ship($b, 250, '2026-09-11');

        $all = $this->fetch()['summary'];
        $this->assertFalse($all['cover_filtered']);
        $this->assertSame(1, $all['classes']['fast']['sku_count']);
        $this->assertSame(1, $all['classes']['medium']['sku_count']);
        $this->assertSame(1, $all['classes']['non_moving']['sku_count']);

        $critical = $this->fetch(['cover_filter' => 'kritis'])['summary'];
        $this->assertTrue($critical['cover_filtered']);
        $this->assertSame(1, $critical['classes']['fast']['sku_count']);
        $this->assertSame(0, $critical['classes']['medium']['sku_count']);
        $this->assertSame(0, $critical['classes']['non_moving']['sku_count']);
        $this->assertEquals(75, $critical['classes']['fast']['qty_share']); // tetap terhadap total 1000
        $this->assertEquals(1000, $critical['total_out']);
        $this->assertSame(3, $critical['total_sku']);

        // Filter klasifikasi hanya mempersempit tabel, kartu lain tidak menjadi 0
        $fastOnly = $this->fetch(['class_filter' => 'fast']);
        $this->assertCount(1, $fastOnly['data']);
        $this->assertFalse($fastOnly['summary']['cover_filtered']);
        $this->assertSame(1, $fastOnly['summary']['classes']['medium']['sku_count']);
    }

    public function test_exports_excel_report_with_sheets_and_charts(): void
    {
        $a = $this->item('A', 300);
        $b = $this->item('B', 5);
        $this->item('E', 40);
        $this->ship($a, 750, '2026-09-10');
        $this->ship($b, 250, '2026-09-15');

        $response = $this->get(route('admin.laporan.pergerakanBarang.export', [
            'date_range' => '30/08/2026 - 28/09/2026',
        ]));
        $response->assertOk();
        $this->assertStringContainsString('laporan-pergerakan-barang_gudang-nanggewer_20260830_20260928.xlsx', $response->headers->get('content-disposition'));

        $path = tempnam(sys_get_temp_dir(), 'pb') . '.xlsx';
        file_put_contents($path, $response->streamedContent() ?: $response->getFile()->getContent());
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $book = $reader->load($path);
        @unlink($path);

        $this->assertSame(
            ['Ringkasan', 'Detail Item', 'Pareto', 'Tren Keluar', 'Perlu Perhatian', 'Keterangan'],
            $book->getSheetNames()
        );
        $this->assertSame(3, $book->getSheetByName('Ringkasan')->getChartCount());
        $this->assertSame(2, $book->getSheetByName('Pareto')->getChartCount());
        $this->assertSame(2, $book->getSheetByName('Tren Keluar')->getChartCount());

        $detail = $book->getSheetByName('Detail Item');
        $this->assertSame('A', $detail->getCell('B5')->getValue());
        $this->assertSame('Fast Moving', $detail->getCell('F5')->getValue());
        $this->assertSame('Medium Moving', $detail->getCell('F6')->getValue()); // mulai di kumulatif 75%
        $this->assertSame('Non Moving', $detail->getCell('F7')->getValue());
        $this->assertEquals(75, $detail->getCell('I5')->getValue());

        $summary = $book->getSheetByName('Ringkasan');
        $this->assertEquals(1000, $summary->getCell('A6')->getValue());
        $this->assertEquals(1, $summary->getCell('C10')->getValue()); // SKU fast

        // B: avg 250/30 = 8,33/hari, stok 5 -> kritis, masuk daftar risiko
        $attention = $book->getSheetByName('Perlu Perhatian');
        $this->assertSame('B', $attention->getCell('B7')->getValue());
    }

    public function test_page_renders_with_nanggewer_selected(): void
    {
        $this->get(route('admin.laporan.pergerakanBarang'))
            ->assertOk()
            ->assertSee('value="' . $this->nanggewer . '" selected', false);
    }
}
