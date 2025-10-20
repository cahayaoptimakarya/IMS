<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Jabatan; // Import Jabatan model
use App\Models\Warehouse;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $developerJabatan = Jabatan::where('name', 'Developer')->first();
        $adminJabatan = Jabatan::where('name', 'Admin')->first();

        // Get warehouses
        $gudangSeha = Warehouse::where('name', 'Gudang Seha')->first();
        $gudangNanggewer = Warehouse::where('name', 'Gudang Nanggewer')->first();
        $gudangSurabaya = Warehouse::where('name', 'Gudang Surabaya')->first();

        User::updateOrCreate(
            ['email' => 'Abdillahrah94@gmail.com'],
            [
                'name' => 'Abdillah',
                'password' => Hash::make('Password!2'),
                'jabatan_id' => $developerJabatan->id ?? null,
            ]
        );
        User::updateOrCreate(
            ['email' => 'eganugrahaid@gmail.com'],
            [
                'name' => 'Ega Nugraha',
                'password' => Hash::make('Password!2'),
                'jabatan_id' => $developerJabatan->id ?? null,
            ]
        );

        if ($gudangSeha) {
            User::updateOrCreate(
                ['email' => 'badrukasep58@gmail.com'],
                [
                    'name' => 'Badru',
                    'password' => Hash::make('Password!2'),
                    'jabatan_id' => $adminJabatan->id ?? null,
                    'warehouse_id' => $gudangSeha->id
                ]
            );
        }

        if ($gudangNanggewer) {
            User::updateOrCreate(
                ['email' => 'Adi.saksana310376@gmail.com'],
                [
                    'name' => 'Adi Saksana',
                    'password' => Hash::make('Password!2'),
                    'jabatan_id' => $adminJabatan->id ?? null,
                    'warehouse_id' => $gudangNanggewer->id
                ]
            );
        }

        if ($gudangSurabaya) {
            User::updateOrCreate(
                ['email' => 'surabaya2025@gmail.com'],
                [
                    'name' => 'Surabaya 2025',
                    'password' => Hash::make('Password!2'),
                    'jabatan_id' => $adminJabatan->id ?? null,
                    'warehouse_id' => $gudangSurabaya->id
                ]
            );
        }
    }
}
