<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'type',
        'license_plate',
        'brand',
        'model',
        'color',
        'active',
        'description',
    ];
}

