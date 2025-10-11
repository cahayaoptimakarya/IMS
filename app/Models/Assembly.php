<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assembly extends Model
{
    protected $table = 'assemblies';

    protected $fillable = [
        'code',
        'warehouse_id',
        'date',
        'assembly_recipe_id',
        'finished_item_id',
        'output_quantity_plan',
        'output_quantity_real',
        'output_koli_plan',
        'output_koli_real',
        'notes',
        'created_by',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'output_quantity_plan' => 'decimal:2',
        'output_quantity_real' => 'decimal:2',
        'output_koli_plan' => 'decimal:2',
        'output_koli_real' => 'decimal:2',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function recipe()
    {
        return $this->belongsTo(AssemblyRecipe::class, 'assembly_recipe_id');
    }

    public function finishedItem()
    {
        return $this->belongsTo(Item::class, 'finished_item_id');
    }

    public function components()
    {
        return $this->hasMany(AssemblyComponent::class);
    }
}
