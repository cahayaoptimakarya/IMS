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
 * Jika tipe item dipilih, klasifikasi dihitung hanya di antara item bertipe tersebut.
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

    // Pita days cover (hari): kritis < 7, waspada 7 - <30, aman 30 - 90, berlebih > 90
    public const COVER_CRITICAL = 'kritis';
    public const COVER_WARNING = 'waspada';
    public const COVER_SAFE = 'aman';
    public const COVER_EXCESS = 'berlebih';
    public const COVER_NONE = 'tidak_ada';

    public const COVER_CRITICAL_DAYS = 7;
    public const COVER_WARNING_DAYS = 30;
    public const COVER_EXCESS_DAYS = 90;

    public const COVER_LABELS = [
        self::COVER_CRITICAL => 'Kritis (< 7 hari)',
        self::COVER_WARNING => 'Waspada (7 - 30 hari)',
        self::COVER_SAFE => 'Aman (30 - 90 hari)',
        self::COVER_EXCESS => 'Berlebih (> 90 hari)',
        self::COVER_NONE => 'Tidak ada (non moving)',
    ];

    // Days cover needed default untuk rekomendasi order (hari), dibatasi maksimal setahun
    public const DEFAULT_DAYS_NEEDED = 30;
    public const MAX_DAYS_NEEDED = 365;

    public static function coverStatus(?float $daysCover): string
    {
        return match (true) {
            $daysCover === null => self::COVER_NONE,
            $daysCover < self::COVER_CRITICAL_DAYS => self::COVER_CRITICAL,
            $daysCover < self::COVER_WARNING_DAYS => self::COVER_WARNING,
            $daysCover <= self::COVER_EXCESS_DAYS => self::COVER_SAFE,
            default => self::COVER_EXCESS,
        };
    }

    /**
     * @return array{rows: Collection, summary: array, period_days: int, total_out: float}
     */
    public function analyze(int $warehouseId, Carbon $startDate, Carbon $endDate, ?int $itemTypeId = null): array
    {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->endOfDay();

        // Hari efektif untuk rata-rata tidak melebihi hari ini.
        $effectiveEnd = $endDate->copy()->min(Carbon::today()->endOfDay());
        $periodDays = max(1, (int) $startDate->diffInDays($effectiveEnd->copy()->startOfDay()) + 1);

        $outRows = $this->shippedBaseQuery($warehouseId, $itemTypeId)
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
        $lastOutDates = $this->shippedBaseQuery($warehouseId, $itemTypeId)
            ->where('s.shipping_date', '<=', $endDate->toDateString())
            ->groupBy('sid.item_id')
            ->select(['sid.item_id', DB::raw('MAX(s.shipping_date) as last_out_date')])
            ->pluck('last_out_date', 'sid.item_id');

        $stocks = DB::table('inventories')
            ->where('warehouse_id', $warehouseId)
            ->when($itemTypeId, fn ($q) => $q->whereIn('item_id', DB::table('items')->where('item_type_id', $itemTypeId)->select('id')))
            ->groupBy('item_id')
            ->select(['item_id', DB::raw('SUM(quantity) as stock')])
            ->pluck('stock', 'item_id');

        $movingIds = $outRows->filter(fn ($r) => (float) $r->qty_out > 0)->keys();
        $stockedIds = $stocks->filter(fn ($qty) => (float) $qty > 0)->keys();
        $itemIds = $movingIds->merge($stockedIds)->unique()->values();

        $items = DB::table('items as i')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.uom_id')
            ->whereIn('i.id', $itemIds)
            ->select(['i.id', 'i.sku', 'i.nama_barang', 'i.koli', DB::raw('COALESCE(u.symbol, u.name) as uom')])
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
                'per_koli' => max(1, (int) ($item->koli ?? 1)),
                'stock' => $stock,
                'qty_out' => $qtyOut,
                'avg_per_day' => $avgPerDay,
                'frequency' => $out ? (int) $out->frequency : 0,
                'days_cover' => $avgPerDay > 0 ? max(0, $stock) / $avgPerDay : null,
                'last_out_date' => $lastOut ? Carbon::parse($lastOut)->toDateString() : null,
            ];
        })->values();

        $rows = $this->classify($rows, $totalOut)->map(function ($row) {
            $row['cover_status'] = self::coverStatus($row['days_cover']);
            return $row;
        });

        return [
            'rows' => $rows,
            'summary' => $this->summarize($rows, $totalOut),
            'period_days' => $periodDays,
            'total_out' => $totalOut,
        ];
    }

    /**
     * Tambahkan rekomendasi order agar stok mencukupi $daysNeeded hari ke depan.
     *
     * - Target stok   = rata-rata keluar / hari x days cover needed
     * - Stok tersedia = stok gudang + qty dalam order (Stock In Order requested / on_shipping yang belum diterima)
     * - Kebutuhan     = max(0, target - stok tersedia)
     * - Rekomendasi   = kebutuhan dibulatkan ke atas per koli (isi per koli dari master item)
     * Item non moving (rata-rata 0) tidak direkomendasikan order.
     */
    public function withOrderRecommendation(Collection $rows, int $warehouseId, float $daysNeeded): Collection
    {
        $onOrder = $this->onOrderQuantities($warehouseId, $rows->pluck('item_id')->all());

        return $rows->map(function ($row) use ($onOrder, $daysNeeded) {
            $onOrderQty = (float) ($onOrder->get($row['item_id']) ?? 0);
            $available = max(0, $row['stock']) + $onOrderQty;
            $target = $row['avg_per_day'] * $daysNeeded;
            $need = max(0, $target - $available);
            // Toleransi pembulatan float agar kebutuhan 20,0000001 tidak menjadi 3 koli
            $orderKoli = $need > 1e-6 ? (int) ceil(round($need / $row['per_koli'], 6)) : 0;
            $orderQty = $orderKoli * $row['per_koli'];

            $row['days_needed'] = $daysNeeded;
            $row['on_order_qty'] = $onOrderQty;
            $row['target_qty'] = $target;
            $row['need_qty'] = $need;
            $row['order_koli'] = $orderKoli;
            $row['order_qty'] = $orderQty;
            $row['cover_after_order'] = $row['avg_per_day'] > 0
                ? ($available + $orderQty) / $row['avg_per_day']
                : null;

            return $row;
        });
    }

    /**
     * Qty yang masih dalam perjalanan ke gudang (belum diterima) per item.
     */
    public function onOrderQuantities(int $warehouseId, array $itemIds): Collection
    {
        if (empty($itemIds)) {
            return collect();
        }

        return DB::table('stock_in_order_items as si')
            ->join('stock_in_orders as sio', 'sio.id', '=', 'si.stock_in_order_id')
            ->where('sio.warehouse_id', $warehouseId)
            ->whereIn('sio.status', ['requested', 'on_shipping'])
            ->whereIn('si.status', ['pending', 'on_progress'])
            ->whereIn('si.item_id', $itemIds)
            ->groupBy('si.item_id')
            ->select(['si.item_id', DB::raw('SUM(COALESCE(si.remaining_quantity, 0)) as qty')])
            ->pluck('qty', 'si.item_id')
            ->map(fn ($qty) => max(0, (float) $qty));
    }

    /**
     * Ringkasan rekomendasi order untuk sekumpulan baris hasil withOrderRecommendation().
     */
    public function summarizeOrder(Collection $rows): array
    {
        $ordered = $rows->filter(fn ($row) => $row['order_qty'] > 0);

        return [
            'sku_count' => $ordered->count(),
            'order_qty' => (float) $ordered->sum('order_qty'),
            'order_koli' => (int) $ordered->sum('order_koli'),
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

    /**
     * Ringkasan per klasifikasi untuk sekumpulan baris; qty_share dihitung terhadap $totalOut.
     */
    public function summarize(Collection $rows, float $totalOut): array
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

    /**
     * Qty keluar per tanggal kirim, dipecah per klasifikasi item (dari hasil analyze()).
     * Periode > 92 hari dikelompokkan per minggu (Senin) agar tetap terbaca di chart.
     *
     * @return array{granularity: string, buckets: array<string, array<string, float>>}
     */
    public function outTrend(int $warehouseId, Carbon $startDate, Carbon $endDate, Collection $rows, ?int $itemTypeId = null): array
    {
        $startDate = $startDate->copy()->startOfDay();
        $endDate = $endDate->copy()->startOfDay();
        $weekly = $startDate->diffInDays($endDate) + 1 > 92;
        $classByItem = $rows->pluck('classification', 'item_id');
        $emptyBucket = array_fill_keys([self::FAST, self::MEDIUM, self::SLOW], 0.0);

        $buckets = [];
        for ($day = $startDate->copy(); $day->lte($endDate); $day->addDay()) {
            $key = ($weekly ? $day->copy()->startOfWeek(Carbon::MONDAY) : $day)->toDateString();
            $buckets[$key] ??= $emptyBucket;
        }

        $daily = $this->shippedBaseQuery($warehouseId, $itemTypeId)
            ->whereBetween('s.shipping_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('s.shipping_date', 'sid.item_id')
            ->select(['s.shipping_date', 'sid.item_id', DB::raw('SUM(sid.quantity_shipped) as qty')])
            ->get();

        foreach ($daily as $row) {
            $class = $classByItem->get((int) $row->item_id);
            if (!isset($emptyBucket[$class])) {
                continue;
            }
            $date = Carbon::parse($row->shipping_date);
            $key = ($weekly ? $date->startOfWeek(Carbon::MONDAY) : $date)->toDateString();
            if (isset($buckets[$key])) {
                $buckets[$key][$class] += (float) $row->qty;
            }
        }

        return ['granularity' => $weekly ? 'weekly' : 'daily', 'buckets' => $buckets];
    }

    protected function shippedBaseQuery(int $warehouseId, ?int $itemTypeId = null)
    {
        return DB::table('shipment_item_details as sid')
            ->when($itemTypeId, fn ($q) => $q->join('items as it', 'it.id', '=', 'sid.item_id')->where('it.item_type_id', $itemTypeId))
            ->join('shipments as s', 's.id', '=', 'sid.shipment_id')
            ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
            ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            // shipments.from_warehouse_id bertipe string
            ->where('s.from_warehouse_id', (string) $warehouseId);
    }
}
