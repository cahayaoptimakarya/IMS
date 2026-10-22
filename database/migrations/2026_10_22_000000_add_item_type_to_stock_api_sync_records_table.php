<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('stock_api_sync_records', 'item_type')) return;
        Schema::table('stock_api_sync_records', function (Blueprint $table) {
            $table->string('item_type', 255)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stock_api_sync_records', 'item_type')) return;
        Schema::table('stock_api_sync_records', fn (Blueprint $table) => $table->dropColumn('item_type'));
    }
};
