<?php

namespace App\Observers;

use App\Models\Item;
use App\Services\ReorderPointService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class ItemObserver
{
    public function updated(Item $item): void
    {
        // If Koli or lead time fields change, recompute for all related warehouses
        $dirty = $item->getDirty();
        $keys = array_keys($dirty);
        if (!array_intersect($keys, ['koli', 'lead_time_po_days', 'lead_time_safety_days'])) {
            return;
        }

        DB::afterCommit(function () use ($item) {
            $warehouseIds = DB::table('inventories')
                ->where('item_id', $item->id)
                ->distinct()
                ->pluck('warehouse_id')
                ->filter()
                ->values();

            /** @var ReorderPointService $svc */
            $svc = App::make(ReorderPointService::class);
            foreach ($warehouseIds as $wid) {
                $svc->compute((int)$item->id, (int)$wid);
            }
        });
    }
}
