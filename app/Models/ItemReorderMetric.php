<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemReorderMetric extends Model
{
    protected $table = 'item_reorder_metrics';

    protected $fillable = [
        'item_id', 'warehouse_id',
        'lead_time_po', 'lead_time_safety_stock',
        'safety_stock_qty', 'rop_qty', 'rop_koli',
    ];
}
