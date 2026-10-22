<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockApiItemTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['stock_api.enabled' => true, 'stock_api.token' => 'test-token', 'stock_api.source_warehouse_name' => 'Gudang Nanggewer']);
        DB::table('warehouses')->insert(['id' => 1, 'name' => 'Gudang Nanggewer']);
        DB::table('uoms')->insert(['id' => 1, 'name' => 'Pieces', 'symbol' => 'pcs']);
        DB::table('item_types')->insert(['id' => 1, 'name' => 'Barang Jadi']);
        DB::table('item_categories')->insert(['id' => 1, 'name' => 'Umum']);
        DB::table('stock_api_allowed_ips')->insert(['ip_address' => '127.0.0.1', 'is_active' => true]);
    }

    private function createItem(): Item
    {
        return Item::create(['sku' => 'SKU-1', 'nama_barang' => 'Barang 1', 'koli' => 10, 'uom_id' => 1,
            'item_type_id' => 1, 'item_category_id' => 1, 'is_active' => 1]);
    }

    private function getStocks(string $query = '')
    {
        return $this->withHeaders(['Authorization' => 'Bearer test-token', 'Accept' => 'application/json'])
            ->get('/api/v1/stocks'.$query);
    }

    public function test_stocks_response_includes_item_type(): void
    {
        $this->createItem();

        $this->getStocks()->assertOk()->assertJsonPath('data.0.sku', 'SKU-1')->assertJsonPath('data.0.item_type', 'Barang Jadi');
    }

    public function test_renaming_item_type_resyncs_items(): void
    {
        $this->createItem();
        ItemType::find(1)->update(['name' => 'Bahan Baku']);

        $this->getStocks()->assertOk()->assertJsonPath('data.0.item_type', 'Bahan Baku');
    }
}
