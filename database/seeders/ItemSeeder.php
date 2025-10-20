<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Uom;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $elektronikCategory = ItemCategory::where('name', 'Elektronik')->first();
        $peralatanRumahCategory = ItemCategory::where('name', 'Peralatan Rumah')->first();
        $piecesUom = Uom::where('name', 'Pieces')->first();

        // $items = [
        //     // Elektronik
        //     [
        //         'nama_barang' => 'Laptop Super Canggih',
        //         'sku' => 'EL-LAP-001',
        //         'item_category_id' => $elektronikCategory->id,
        //         'uom_id' => $piecesUom->id,
        //         'koli' => 22
        //     ],
        //     [
        //         'nama_barang' => 'Smartphone Keren',
        //         'sku' => 'EL-SMA-001',
        //         'item_category_id' => $elektronikCategory->id,
        //         'uom_id' => $piecesUom->id,
        //         'koli' => 15
        //     ],
        //     // Peralatan Rumah
        //     [
        //         'nama_barang' => 'Panci Anti Lengket',
        //         'sku' => 'PR-PAN-001',
        //         'item_category_id' => $peralatanRumahCategory->id,
        //         'uom_id' => $piecesUom->id,
        //         'koli' => 12
        //     ],
        //     [
        //         'nama_barang' => 'Sapu Otomatis',
        //         'sku' => 'PR-SAP-001',
        //         'item_category_id' => $peralatanRumahCategory->id,
        //         'uom_id' => $piecesUom->id,
        //         'koli' => 4
        //     ],
        //     [
        //         'nama_barang' => 'Setrika Uap',
        //         'sku' => 'PR-SET-001',
        //         'item_category_id' => $peralatanRumahCategory->id,
        //         'uom_id' => $piecesUom->id,
        //         'koli' => 2
        //     ],
        // ];

        // foreach ($items as $item) {
        //     Item::updateOrCreate(['sku' => $item['sku']], $item);
        // }
    }
}
