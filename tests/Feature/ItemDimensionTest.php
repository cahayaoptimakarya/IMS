<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionMiddleware;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemDimensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('uoms')->insert(['id' => 1, 'name' => 'Pieces', 'symbol' => 'pcs']);
        DB::table('item_types')->insert(['id' => 1, 'name' => 'Barang']);
        DB::table('item_categories')->insert(['id' => 1, 'name' => 'Umum']);

        $this->withoutMiddleware(PermissionMiddleware::class);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'sku' => 'SKU-1',
            'nama_barang' => 'Barang 1',
            'koli' => 12,
            'uom_id' => 1,
            'item_category_id' => 1,
            'is_active' => 1,
        ];
    }

    public function test_store_saves_dimensions_and_computes_cbm(): void
    {
        $this->post(route('admin.masterdata.items.store'), $this->payload([
            'panjang_koli' => '60', 'lebar_koli' => '40', 'tinggi_koli' => '30.5',
        ]))->assertRedirect(route('admin.masterdata.items.index'))->assertSessionHasNoErrors();

        $item = Item::where('sku', 'SKU-1')->firstOrFail();
        $this->assertSame(60.0, $item->panjang_koli);
        $this->assertSame(30.5, $item->tinggi_koli);
        $this->assertEqualsWithDelta(0.0732, $item->cbm_per_koli, 1e-9);     // 60 x 40 x 30,5 / 1.000.000
        $this->assertEqualsWithDelta(0.0061, $item->cbm_per_unit, 1e-9);     // / 12 pcs per koli
    }

    public function test_dimensions_are_optional(): void
    {
        $this->post(route('admin.masterdata.items.store'), $this->payload([
            'panjang_koli' => '', 'lebar_koli' => '', 'tinggi_koli' => '',
        ]))->assertSessionHasNoErrors();

        $item = Item::where('sku', 'SKU-1')->firstOrFail();
        $this->assertNull($item->panjang_koli);
        $this->assertNull($item->cbm_per_koli);
        $this->assertNull($item->cbm_per_unit);
    }

    public function test_dimensions_must_be_complete_and_positive(): void
    {
        $this->post(route('admin.masterdata.items.store'), $this->payload(['panjang_koli' => '60']))
            ->assertSessionHasErrors(['lebar_koli', 'tinggi_koli']);

        $this->post(route('admin.masterdata.items.store'), $this->payload([
            'panjang_koli' => '0', 'lebar_koli' => '40', 'tinggi_koli' => '30',
        ]))->assertSessionHasErrors(['panjang_koli']);

        $this->assertSame(0, Item::count());
    }

    public function test_update_changes_and_clears_dimensions(): void
    {
        $item = Item::create($this->payload(['panjang_koli' => 10, 'lebar_koli' => 10, 'tinggi_koli' => 10]));

        $this->put(route('admin.masterdata.items.update', $item->id), $this->payload([
            'panjang_koli' => '50', 'lebar_koli' => '20', 'tinggi_koli' => '10',
        ]))->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.01, $item->fresh()->cbm_per_koli, 1e-9);

        $this->put(route('admin.masterdata.items.update', $item->id), $this->payload([
            'panjang_koli' => '', 'lebar_koli' => '', 'tinggi_koli' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->cbm_per_koli);
    }

    public function test_data_endpoint_and_views_include_dimensions(): void
    {
        $item = Item::create($this->payload(['panjang_koli' => 60, 'lebar_koli' => 40, 'tinggi_koli' => 30]));

        $row = $this->getJson(route('admin.masterdata.items.data', ['draw' => 1]))->assertOk()->json('data.0');
        $this->assertEquals(60, $row['panjang_koli']);
        $this->assertEqualsWithDelta(0.072, $row['cbm_per_koli'], 1e-9);

        $this->get(route('admin.masterdata.items.edit', $item->id))->assertOk()
            ->assertSee('name="panjang_koli"', false)->assertSee('value="60"', false);
        $this->get(route('admin.masterdata.items.create'))->assertOk()->assertSee('name="tinggi_koli"', false);
        $this->get(route('admin.masterdata.items.show', $item->id))->assertOk()
            ->assertSee('60 &times; 40 &times; 30 cm', false)->assertSee('0,072 m³ per koli', false);
    }

    public function test_csv_import_reads_optional_dimension_columns(): void
    {
        $csv = implode("\n", [
            'SKU-A;Barang A;10;100;1;1;;;Rak 1;60;40,5;30',   // lengkap, desimal koma
            'SKU-B;Barang B;10;100;1;1;;;;60;;30',           // tidak lengkap -> diabaikan
            'SKU-C;Barang C;10;100;1;1',                     // tanpa kolom dimensi
        ]);
        $file = UploadedFile::fake()->createWithContent('items.csv', $csv);

        $this->post(route('admin.masterdata.items.import'), ['csv_file' => $file])
            ->assertRedirect(route('admin.masterdata.items.index'));

        $a = Item::where('sku', 'SKU-A')->firstOrFail();
        $this->assertSame(40.5, $a->lebar_koli);
        $this->assertEqualsWithDelta(0.0729, $a->cbm_per_koli, 1e-9);
        $this->assertNull(Item::where('sku', 'SKU-B')->firstOrFail()->panjang_koli);
        $this->assertNull(Item::where('sku', 'SKU-C')->firstOrFail()->cbm_per_koli);
    }
}
