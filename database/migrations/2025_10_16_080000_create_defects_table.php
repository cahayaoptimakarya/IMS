<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignId('item_id')->constrained('items')->onDelete('restrict');
            $table->decimal('quantity', 20, 2);
            $table->decimal('koli', 20, 2)->nullable();
            $table->text('notes')->comment('Alasan atau catatan mengapa barang ini dianggap rusak');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Penanganan setelah defect disetujui (disposal/retur/perbaikan/dll)
            $table->enum('action', [
                'disposal',           // buang / scrap
                'rework',             // perbaikan/perakitan ulang
                'return_to_stock',    // layak -> kembali ke stok baik
                'other',
            ])->nullable()->comment('Jenis tindakan penanganan defect');
            $table->enum('action_status', ['pending', 'in_progress', 'completed', 'cancelled'])
                ->default('pending')
                ->comment('Status proses penanganan');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('action_notes')->nullable()->comment('Catatan proses penanganan');

            // Index untuk query umum
            $table->index(['status']);
            $table->index(['action']);
            $table->index(['action_status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
