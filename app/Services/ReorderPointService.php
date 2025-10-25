<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemReorderMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReorderPointService
{
    // Defaults can be moved to config later
    protected int $defaultWindowDays = 90;
    protected int $minDataDays = 30;
    protected int $defaultLeadTimeDays = 7;
    protected float $fallbackSafetyDays = 0.5; // 1/2 hari permintaan rata-rata

    /**
     * Compute and upsert ROP for one item in one warehouse.
     */
    public function compute(int $itemId, int $warehouseId): void
    {
        $windowEnd = Carbon::today();
        $windowStart = (clone $windowEnd)->copy()->subDays($this->defaultWindowDays - 1);

        // Resolve per-item settings (lead time), fallback to defaults
        $item = Item::find($itemId);
        $perKoli = max(1, (int)($item->koli ?? 1));
        $leadTimePo = (int) ($item->lead_time_po_days ?? $this->defaultLeadTimeDays);
        $leadTimeSafety = (int) ($item->lead_time_safety_days ?? 1);

        // Daily OUT by date within window
        $daily = DB::table('stock_movements as sm')
            ->selectRaw('DATE(sm.date) as d, SUM(ABS(sm.quantity)) as out_qty, SUM(CASE WHEN sm.koli < 0 THEN -sm.koli ELSE 0 END) as out_koli')
            ->where('sm.warehouse_id', $warehouseId)
            ->where('sm.item_id', $itemId)
            ->whereBetween('sm.date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->where(function ($q) {
                $q->whereIn('sm.type', ['stock_out', 'transfer_out'])
                  ->orWhere(function ($qq) {
                      $qq->whereNull('sm.type')->orWhere('sm.type', '=','');
                  })
                  ->where('sm.quantity', '<', 0);
            })
            ->groupBy('d')
            ->get();

        // If not enough days, extend to first movement date
        $movementDays = $daily->count();
        if ($movementDays < $this->minDataDays) {
            $first = DB::table('stock_movements')
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $itemId)
                ->min('date');
            if ($first) {
                $windowStart = Carbon::parse($first)->startOfDay();
                $daily = DB::table('stock_movements as sm')
                    ->selectRaw('DATE(sm.date) as d, SUM(ABS(sm.quantity)) as out_qty, SUM(CASE WHEN sm.koli < 0 THEN -sm.koli ELSE 0 END) as out_koli')
                    ->where('sm.warehouse_id', $warehouseId)
                    ->where('sm.item_id', $itemId)
                    ->whereBetween('sm.date', [$windowStart->toDateString(), $windowEnd->toDateString()])
                    ->where(function ($q) {
                        $q->whereIn('sm.type', ['stock_out', 'transfer_out'])
                          ->orWhere(function ($qq) {
                              $qq->whereNull('sm.type')->orWhere('sm.type', '=','');
                          })
                          ->where('sm.quantity', '<', 0);
                    })
                    ->groupBy('d')
                    ->get();
            }
        }

        $movementDays = $daily->count();
        $totalOutQty = (float) $daily->sum('out_qty');
        $avgDailyOut = $movementDays > 0 ? ($totalOutQty / $movementDays) : 0.0;
        $safetyStock = max(0, $avgDailyOut * max(0, $leadTimeSafety));
        $ropQty = ($avgDailyOut * max(0, $leadTimePo)) + $safetyStock;
        $ropKoli = $ropQty / $perKoli;

        ItemReorderMetric::updateOrCreate(
            ['item_id' => $itemId, 'warehouse_id' => $warehouseId],
            [
                'lead_time_po' => $leadTimePo,
                'lead_time_safety_stock' => $leadTimeSafety,
                'safety_stock_qty' => $safetyStock,
                'rop_qty' => $ropQty,
                'rop_koli' => $ropKoli,
            ]
        );
    }
}
