<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensi per koli/karton (cm) untuk perhitungan CBM. Nullable: item lama belum memiliki data.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('panjang_koli', 10, 2)->nullable()->after('koli')->comment('Panjang per koli/karton (cm)');
            $table->decimal('lebar_koli', 10, 2)->nullable()->after('panjang_koli')->comment('Lebar per koli/karton (cm)');
            $table->decimal('tinggi_koli', 10, 2)->nullable()->after('lebar_koli')->comment('Tinggi per koli/karton (cm)');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['panjang_koli', 'lebar_koli', 'tinggi_koli']);
        });
    }
};
