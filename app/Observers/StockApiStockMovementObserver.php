<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Support\StockApiSyncService;
use Illuminate\Support\Facades\DB;

class StockApiStockMovementObserver
{
    private function sync(StockMovement $movement): void
    {
        if ($movement->item_id) DB::afterCommit(fn () => StockApiSyncService::syncItem((int) $movement->item_id));
    }

    public function created(StockMovement $movement): void { $this->sync($movement); }
    public function updated(StockMovement $movement): void { $this->sync($movement); }
    public function deleted(StockMovement $movement): void { $this->sync($movement); }
}
