<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // Jenis/tipe kendaraan (mengacu pada kolom vehicle_type pada shipments)
            $table->string('type')->nullable();
            // Nomor polisi (mengacu pada kolom license_plate pada shipments)
            $table->string('license_plate')->unique();
            // Merek & model (opsional)
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

