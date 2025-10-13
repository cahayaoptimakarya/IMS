<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('warehouse_id');
            $table->date('date');
            $table->unsignedBigInteger('assembly_recipe_id')->nullable();
            $table->unsignedBigInteger('finished_item_id');
            $table->decimal('output_quantity_plan', 20, 2)->default(0);
            $table->decimal('output_quantity_real', 20, 2)->default(0);
            $table->decimal('output_koli_plan', 20, 2)->default(0);
            $table->decimal('output_koli_real', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->enum('status', ['draft','dalam proses', 'completed'])->default('dalam proses');
            $table->timestamps();
        });

        Schema::create('assembly_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assembly_id');
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity_recipes', 20, 2)->default(0);
            $table->decimal('quantity_plan', 20, 2)->default(0);
            $table->decimal('quantity_real', 20, 2)->default(0);
            $table->decimal('quantity_rejected', 20, 2)->default(0);
            $table->decimal('koli_plan', 20, 2)->default(0);
            $table->decimal('koli_real', 20, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_components');
        Schema::dropIfExists('assemblies');
    }
};
