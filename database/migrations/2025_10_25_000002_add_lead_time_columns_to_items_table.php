<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'lead_time_po_days')) {
                $table->integer('lead_time_po_days')->default(7)->after('low_stock_threshold');
            }
            if (!Schema::hasColumn('items', 'lead_time_safety_days')) {
                $table->integer('lead_time_safety_days')->default(1)->after('lead_time_po_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'lead_time_po_days')) {
                $table->dropColumn('lead_time_po_days');
            }
            if (Schema::hasColumn('items', 'lead_time_safety_days')) {
                $table->dropColumn('lead_time_safety_days');
            }
        });
    }
};

