<?php

namespace App\Observers;

use App\Models\Item;
use App\Support\StockApiSyncService;
use Illuminate\Support\Facades\DB;

class StockApiItemObserver
{
    public function saved(Item $item): void { DB::afterCommit(fn () => StockApiSyncService::syncItem($item->id)); }
    public function deleting(Item $item): void { StockApiSyncService::markDeleted($item); }
}
