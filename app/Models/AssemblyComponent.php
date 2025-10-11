<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssemblyComponent extends Model
{
    protected $table = 'assembly_components';

    protected $fillable = [
        'assembly_id',
        'item_id',
        'quantity_recipes',
        'quantity_plan',
        'quantity_real',
        'quantity_rejected',
        'koli_plan',
        'koli_real',
    ];

    protected $casts = [
        'quantity_recipes' => 'decimal:2',
        'quantity_plan' => 'decimal:2',
        'quantity_real' => 'decimal:2',
        'quantity_rejected' => 'decimal:2',
        'koli_plan' => 'decimal:2',
        'koli_real' => 'decimal:2',
    ];

    public function assembly()
    {
        return $this->belongsTo(Assembly::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
