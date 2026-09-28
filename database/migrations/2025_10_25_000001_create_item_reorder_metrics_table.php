<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('item_reorder_metrics')) {
            return;
        }

        Schema::create('item_reorder_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('warehouse_id');

            $table->integer('lead_time_po')->default(7); // hari
            $table->integer('lead_time_safety_stock')->default(1); // hari buffer
            $table->decimal('safety_stock_qty', 18, 6)->default(0);
            $table->decimal('rop_qty', 18, 6)->default(0);
            $table->decimal('rop_koli', 18, 6)->default(0);

            $table->timestamps();

            $table->unique(['item_id', 'warehouse_id'], 'uq_item_warehouse');
            $table->index(['warehouse_id', 'item_id'], 'idx_wh_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_reorder_metrics');
    }
};
