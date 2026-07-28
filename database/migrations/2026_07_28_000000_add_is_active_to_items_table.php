<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan penanda master data tanpa mengubah stok, movement, atau dokumen
     * transaksi yang sudah ada. Semua item lama tetap aktif secara default.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
