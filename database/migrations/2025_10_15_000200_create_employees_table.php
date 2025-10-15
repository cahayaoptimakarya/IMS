<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            // Nama karyawan (contoh peruntukan: sopir)
            $table->string('name');
            // Kontak karyawan (mengacu pada kolom driver_contact pada shipments)
            $table->string('phone')->nullable();
            // Jabatan/posisi (contoh: sopir, admin gudang, dll)
            $table->string('position')->nullable();
            // Nomor SIM atau identitas lain (opsional)
            $table->string('license_number')->nullable();
            // Status aktif/non-aktif
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

