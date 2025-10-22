<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferRequestItem extends Model
{
    protected $table = 'transfer_request_items';
    protected $fillable = [
        'transfer_request_id',
        'item_id',
        'quantity',
        'koli',
        'received_quantity',
        'received_koli',
        'ship_remaining_quantity',
        'ship_remaining_koli',
        'description',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(TransferRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
