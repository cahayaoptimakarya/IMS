<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemReorderMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReorderPointService
{
    // Defaults can be moved to config later
    protected int $defaultWindowDays = 90; // rolling window length
    protected int $defaultLeadTimeDays = 7;

    /**
     * Compute and upsert ROP for one item in one warehouse.
     */
    public function compute(int $itemId, int $warehouseId): void
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays($this->defaultWindowDays - 1); // inclusive window
        $windowStartStr = $windowStart->toDateString();

        // Resolve per-item settings (lead time), fallback to defaults
        $item = Item::find($itemId);
        $perKoli = max(1, (int)($item->koli ?? 1));
        $leadTimePo = (int) ($item->lead_time_po_days ?? $this->defaultLeadTimeDays);
        $leadTimeSafety = (int) ($item->lead_time_safety_days ?? 1);

        // Determine first movement date (lifetime)
        $firstRow = DB::table('stock_movements as sm')
            ->selectRaw('MIN(DATE(sm.date)) as first_date')
            ->where('sm.warehouse_id', $warehouseId)
            ->where('sm.item_id', $itemId)
            ->first();

        $firstLife = $firstRow && $firstRow->first_date ? Carbon::parse($firstRow->first_date) : null;
        // Period start: max(first movement, windowStart). If no movement at all, keep null
        $periodStart = $firstLife ? max($firstLife, $windowStart) : null;

        // Aggregate total OUT within period and count days in period (calendar days)
        $totalOutPeriod = 0.0;
        $daysPeriod = 0;
        if ($periodStart) {
            $periodStartStr = $periodStart->toDateString();
            $totalOutPeriod = (float) DB::table('stock_movements as sm')
                ->where('sm.warehouse_id', $warehouseId)
                ->where('sm.item_id', $itemId)
                ->whereDate('sm.date', '>=', $periodStartStr)
                ->sum(DB::raw("CASE WHEN (sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0) THEN ABS(sm.quantity) ELSE 0 END"));
            $daysPeriod = max(1, $periodStart->diffInDays($today) + 1); // min 1 day to avoid div by zero
            // Cap to 90 days implicitly by periodStart definition (max(first, windowStart))
        }

        $avgDailyOut = ($daysPeriod > 0) ? ($totalOutPeriod / $daysPeriod) : 0.0;

        // Safety stock with simple formula using lead time safety days
        $safetyStock = max(0, $avgDailyOut * max(0, $leadTimeSafety));

        $ropQty = ($avgDailyOut * max(0, $leadTimePo)) + max(0, $safetyStock);
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

    // No extra helpers needed for simple safety-stock formula
}
