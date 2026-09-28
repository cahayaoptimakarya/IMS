<?php

namespace App\Support;

use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Klasifikasi pergerakan barang (Fast / Medium / Slow / Non Moving) per gudang.
 *
 * Qty keluar diambil dari barang yang dikirim atas Permintaan Barang
 * (shipment_item_details -> transfer_request_items) dengan gudang asal = gudang terpilih.
 * Stok gudang berkurang saat shipment dibuat, dan pembatalan shipment menghapus detailnya,
 * sehingga semua shipment yang masih ada dihitung sebagai barang keluar.
 *
 * Klasifikasi berdasarkan kontribusi kumulatif qty keluar (diurutkan terbesar):
 * - Fast   : lapisan kumulatif 0% - 70%
 * - Medium : lapisan kumulatif 70% - 90%
 * - Slow   : lapisan kumulatif > 90%
 * - Non Moving : memiliki stok di gudang tetapi tidak ada qty keluar pada periode
 * Item yang melintasi batas lapisan masuk ke lapisan tempat kontribusinya dimulai,
 * sehingga item teratas selalu Fast meskipun kontribusinya sendiri > 70%.
 */
class ItemMovementAnalyzer
{
    public const FAST = 'fast';
    public const MEDIUM = 'medium';
    public const SLOW = 'slow';
    public const NON_MOVING = 'non_moving';

    public const FAST_THRESHOLD = 70.0;
    public const MEDIUM_THRESHOLD = 90.0;

    public const LABELS = [
        self::FAST => 'Fast Moving',
        self::MEDIUM => 'Medium Moving',
        self::SLOW => 'Slow Moving',
        self::NON_MOVING => 'Non Moving',
    ];

    /**
     * @return array{rows: Collection, summary: array, period_days: int, total_out: float}
     */
    public function analyze(int $warehouseId, Carbon $startDate, Carbon $endDate): array
    {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->endOfDay();

        // Hari efektif untuk rata-rata tidak melebihi hari ini.
        $effectiveEnd = $endDate->copy()->min(Carbon::today()->endOfDay());
        $periodDays = max(1, (int) $startDate->diffInDays($effectiveEnd->copy()->startOfDay()) + 1);

        $outRows = $this->shippedBaseQuery($warehouseId)
            ->whereBetween('s.shipping_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('sid.item_id')
            ->select([
                'sid.item_id',
                DB::raw('SUM(sid.quantity_shipped) as qty_out'),
                DB::raw('COUNT(DISTINCT tri.transfer_request_id) as frequency'),
            ])
            ->get()
            ->keyBy('item_id');

        // Tanggal terakhir keluar (sepanjang waktu s/d akhir periode) agar non moving tetap informatif.
        $lastOutDates = $this->shippedBaseQuery($warehouseId)
            ->where('s.shipping_date', '<=', $endDate->toDateString())
            ->groupBy('sid.item_id')
            ->select(['sid.item_id', DB::raw('MAX(s.shipping_date) as last_out_date')])
            ->pluck('last_out_date', 'sid.item_id');

        $stocks = DB::table('inventories')
            ->where('warehouse_id', $warehouseId)
            ->groupBy('item_id')
            ->select(['item_id', DB::raw('SUM(quantity) as stock')])
            ->pluck('stock', 'item_id');

        $movingIds = $outRows->filter(fn ($r) => (float) $r->qty_out > 0)->keys();
        $stockedIds = $stocks->filter(fn ($qty) => (float) $qty > 0)->keys();
        $itemIds = $movingIds->merge($stockedIds)->unique()->values();

        $items = DB::table('items as i')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.uom_id')
            ->whereIn('i.id', $itemIds)
            ->select(['i.id', 'i.sku', 'i.nama_barang', DB::raw('COALESCE(u.symbol, u.name) as uom')])
            ->get()
            ->keyBy('id');

        $totalOut = (float) $outRows->sum(fn ($r) => max(0, (float) $r->qty_out));

        $rows = $items->map(function ($item) use ($outRows, $stocks, $lastOutDates, $periodDays) {
            $out = $outRows->get($item->id);
            $qtyOut = $out ? max(0, (float) $out->qty_out) : 0.0;
            $stock = (float) ($stocks->get($item->id) ?? 0);
            $avgPerDay = $qtyOut / $periodDays;
            $lastOut = $lastOutDates->get($item->id);

            return [
                'item_id' => (int) $item->id,
                'sku' => $item->sku,
                'nama_barang' => $item->nama_barang,
                'uom' => $item->uom,
                'stock' => $stock,
                'qty_out' => $qtyOut,
                'avg_per_day' => $avgPerDay,
                'frequency' => $out ? (int) $out->frequency : 0,
                'days_cover' => $avgPerDay > 0 ? max(0, $stock) / $avgPerDay : null,
                'last_out_date' => $lastOut ? Carbon::parse($lastOut)->toDateString() : null,
            ];
        })->values();

        $rows = $this->classify($rows, $totalOut);

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows, $totalOut),
            'period_days' => $periodDays,
            'total_out' => $totalOut,
        ];
    }

    /**
     * Urutkan berdasarkan qty keluar lalu tetapkan kontribusi, kumulatif, dan klasifikasi.
     */
    public function classify(Collection $rows, float $totalOut): Collection
    {
        $sorted = $rows->sort(function ($a, $b) {
            return [$b['qty_out'], $a['sku']] <=> [$a['qty_out'], $b['sku']];
        })->values();

        $cumulative = 0.0;
        $epsilon = 1e-9;

        return $sorted->map(function ($row, $index) use (&$cumulative, $totalOut, $epsilon) {
            $row['rank'] = $index + 1;

            if ($row['qty_out'] <= 0 || $totalOut <= 0) {
                $row['contribution'] = 0.0;
                $row['cumulative'] = null;
                $row['classification'] = self::NON_MOVING;
                return $row;
            }

            $startLayer = $cumulative;
            $row['contribution'] = $row['qty_out'] / $totalOut * 100;
            $cumulative += $row['contribution'];
            $row['cumulative'] = min(100.0, $cumulative);

            if ($startLayer < self::FAST_THRESHOLD - $epsilon) {
                $row['classification'] = self::FAST;
            } elseif ($startLayer < self::MEDIUM_THRESHOLD - $epsilon) {
                $row['classification'] = self::MEDIUM;
            } else {
                $row['classification'] = self::SLOW;
            }

            return $row;
        });
    }

    protected function summarize(Collection $rows, float $totalOut): array
    {
        $summary = [];
        foreach (array_keys(self::LABELS) as $class) {
            $classRows = $rows->where('classification', $class);
            $qty = (float) $classRows->sum('qty_out');
            $summary[$class] = [
                'label' => self::LABELS[$class],
                'sku_count' => $classRows->count(),
                'qty_out' => $qty,
                'qty_share' => $totalOut > 0 ? $qty / $totalOut * 100 : 0.0,
                'stock' => (float) $classRows->sum('stock'),
            ];
        }

        return $summary;
    }

    protected function shippedBaseQuery(int $warehouseId)
    {
        return DB::table('shipment_item_details as sid')
            ->join('shipments as s', 's.id', '=', 'sid.shipment_id')
            ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
            ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            // shipments.from_warehouse_id bertipe string
            ->where('s.from_warehouse_id', (string) $warehouseId);
    }
}
