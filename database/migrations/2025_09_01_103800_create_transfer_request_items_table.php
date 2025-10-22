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
        Schema::create('transfer_request_items', function (Blueprint $table) {
            $table->id();
            $table->integer('transfer_request_id');
            $table->integer('item_id');
            $table->decimal('quantity', 20, 2)->default(0);
            $table->decimal('koli', 20, 2)->default(0); 
            $table->decimal('received_quantity', 20, 2)->default(0);
            $table->decimal('received_koli', 20, 2)->default(0);
            $table->decimal('ship_remaining_quantity', 20, 2)->default(0);
            $table->decimal('ship_remaining_koli', 20, 2)->default(0);
            $table->mediumText('description')->nullable();
            $table->enum('status', ['pending', 'on_progress', 'completed', 'cancel'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_request_items');
    }
};
