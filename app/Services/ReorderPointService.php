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
        $today = Carbon::today();

        // Resolve per-item settings (lead time), fallback to defaults
        $item = Item::find($itemId);
        $perKoli = max(1, (int)($item->koli ?? 1));
        $leadTimePo = (int) ($item->lead_time_po_days ?? $this->defaultLeadTimeDays);
        $leadTimeSafety = (int) ($item->lead_time_safety_days ?? 1);

        // Compute OUT since first movement date (inclusive), no per-day grouping
        $agg = DB::table('stock_movements as sm')
            ->selectRaw('MIN(DATE(sm.date)) as first_date, SUM(CASE WHEN (sm.type IN (\'stock_out\', \'transfer_out\')) OR ((sm.type IS NULL OR sm.type=\'\') AND sm.quantity < 0) THEN ABS(sm.quantity) ELSE 0 END) as total_out')
            ->where('sm.warehouse_id', $warehouseId)
            ->where('sm.item_id', $itemId)
            ->first();

        $firstDate = $agg && $agg->first_date ? Carbon::parse($agg->first_date) : null;
        $days = $firstDate ? max(1, $firstDate->diffInDays($today) + 1) : 0;
        $totalOutQty = (float) ($agg->total_out ?? 0);
        $avgDailyOut = $days > 0 ? ($totalOutQty / $days) : 0.0;
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
