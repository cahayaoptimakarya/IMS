<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $table = 'notifications';

    /**
     * Scope: unread notifications only.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: filter by target warehouse id inside data payload.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->whereRaw("JSON_EXTRACT(data, '$.to_warehouse_id') = ?", [$warehouseId])
              ->orWhereRaw("JSON_EXTRACT(data, '$.from_warehouse_id') = ?", [$warehouseId]);
        });
    }
}

