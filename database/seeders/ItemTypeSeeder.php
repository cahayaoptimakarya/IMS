<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ItemType;

class ItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure default ID 1 exists for 'Import'
        ItemType::updateOrCreate(
            ['id' => 1],
            ['name' => 'Import', 'description' => null]
        );

        ItemType::updateOrCreate(
            ['name' => 'Produksi'],
            ['description' => null]
        );
    }
}

