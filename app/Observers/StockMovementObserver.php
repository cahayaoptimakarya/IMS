<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\ReorderPointService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockMovementObserver
{
    protected function recompute(StockMovement $sm): void
    {
        if (!$sm->item_id || !$sm->warehouse_id) return;

        // Debounce via cache lock (5s) to avoid storms on bulk ops
        $lockKey = sprintf('rop:recompute:item:%d:wh:%d', $sm->item_id, $sm->warehouse_id);
        $lock = Cache::lock($lockKey, 5);
        if (!$lock->get()) {
            return; // another recompute is running
        }

        DB::afterCommit(function () use ($sm, $lock) {
            try {
                /** @var ReorderPointService $svc */
                $svc = App::make(ReorderPointService::class);
                $svc->compute((int)$sm->item_id, (int)$sm->warehouse_id);
            } finally {
                optional($lock)->release();
            }
        });
    }

    public function created(StockMovement $sm): void { $this->recompute($sm); }
    public function updated(StockMovement $sm): void { $this->recompute($sm); }
    public function deleted(StockMovement $sm): void { $this->recompute($sm); }
}

