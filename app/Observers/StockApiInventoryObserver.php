<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Support\StockApiSyncService;
use Illuminate\Support\Facades\DB;

class StockApiInventoryObserver
{
    private function sync(Inventory $inventory): void
    {
        if ($inventory->item_id) DB::afterCommit(fn () => StockApiSyncService::syncItem((int) $inventory->item_id));
    }
    public function saved(Inventory $inventory): void { $this->sync($inventory); }
    public function deleted(Inventory $inventory): void { $this->sync($inventory); }
}
