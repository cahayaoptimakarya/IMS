<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InventoryExport;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = trim((string) $request->input('search.value', ''));
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $warehouseFilter = $request->input('warehouse_id'); // 'all' or specific id
            $categoryFilter = $request->input('category_id'); // 'all' or specific id

            // Default warehouse when user has no warehouse: Gudang Nanggewer
            if (($warehouseFilter === null || $warehouseFilter === '') && (auth()->user()?->warehouse_id === null)) {
                $defaultWarehouseId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');
                if ($defaultWarehouseId) {
                    $warehouseFilter = (string) $defaultWarehouseId;
                }
            }

            $query = Inventory::query()
                ->join('items', 'inventories.item_id', '=', 'items.id')
                ->leftJoin('warehouses as w', 'inventories.warehouse_id', '=', 'w.id')
                ->leftJoin('uoms as u', 'items.uom_id', '=', 'u.id');

            // Scope to user's warehouse by default if set and no explicit filter
            if (auth()->user() && auth()->user()->warehouse_id && ($warehouseFilter === null || $warehouseFilter === '')) {
                $query->where('inventories.warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($warehouseFilter) && $warehouseFilter !== 'all') {
                $query->where('inventories.warehouse_id', $warehouseFilter);
            }

            if (!empty($categoryFilter) && $categoryFilter !== 'all') {
                $query->where('items.item_category_id', $categoryFilter);
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('items.nama_barang', 'like', "%{$searchValue}%")
                      ->orWhere('items.sku', 'like', "%{$searchValue}%");
                });
            }

            // Aggregate across all warehouses only when explicitly 'all'
            $aggregateAll = ($warehouseFilter === 'all');

            // Build base (no search) and filtered (with search) for correct counts
            $baseForCount = (clone $query);
            $filteredForCount = (clone $query);
            if (!empty($searchValue)) {
                $filteredForCount->where(function ($q) use ($searchValue) {
                    $q->where('items.nama_barang', 'like', "%{$searchValue}%")
                      ->orWhere('items.sku', 'like', "%{$searchValue}%");
                });
            }

            if ($aggregateAll) {
                $dataQuery = (clone $query)
                    ->select([
                        'items.id as item_id',
                        'items.sku',
                        'items.nama_barang as item_name',
                        'items.koli as per_koli',
                        DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                        DB::raw('SUM(inventories.quantity) as quantity'),
                        DB::raw('SUM(inventories.koli) as koli'),
                    ])
                    ->groupBy('items.id', 'items.sku', 'items.nama_barang', 'items.koli');
            } else {
                // Specific warehouse: sum per item within that warehouse
                $dataQuery = (clone $query)
                    ->select([
                        'items.id as item_id',
                        'items.sku',
                        'items.nama_barang as item_name',
                        'items.koli as per_koli',
                        DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                        DB::raw('SUM(inventories.quantity) as quantity'),
                        DB::raw('SUM(inventories.koli) as koli'),
                        DB::raw('MAX(w.name) as warehouse_name'),
                    ])
                    ->groupBy('items.id', 'items.sku', 'items.nama_barang', 'items.koli');
            }

            // counts by distinct items to align with grouping
            $totalRecords = (clone $baseForCount)->distinct('items.id')->count('items.id');
            $filteredRecords = (clone $filteredForCount)->distinct('items.id')->count('items.id');
            $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $rows = $dataQuery
                ->orderBy('items.nama_barang', $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            // Compute average daily turnover per item (Gudang Nanggewer only)
            $itemIds = $rows->pluck('item_id')->map(fn($v) => (int)$v)->unique()->values()->all();
            $avgTurnoverMap = $this->avgDailyTurnoverNanggewer($itemIds);

            $data = $rows->map(function ($r) use ($aggregateAll, $avgTurnoverMap) {
                return [
                    'item_id' => (int) $r->item_id,
                    'item_name' => $r->item_name,
                    'sku' => $r->sku,
                    'per_koli' => (int) ($r->per_koli ?? 0),
                    'uom_symbol' => $r->uom_symbol ?? null,
                    'quantity' => (float) ($r->quantity ?? 0),
                    'koli' => (float) ($r->koli ?? 0),
                    'avg_daily_turnover' => (float) ($avgTurnoverMap[(int)$r->item_id] ?? 0),
                    'warehouse' => $aggregateAll ? 'Semua Gudang' : ($r->warehouse_name ?? '-'),
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        }

        $warehouses = \App\Models\Warehouse::orderBy('name')->get();
        $categories = ItemCategory::orderBy('name')->get();
        $defaultWarehouseId = auth()->user()?->warehouse_id ?? Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        return view('admin.manajemenstok.masterstok.index', compact('warehouses', 'categories', 'defaultWarehouseId'));
    }

    /**
     * Hitung rata-rata perputaran harian per SKU untuk Gudang Nanggewer saja.
     * Definisi: total OUT (quantity keluar) sejak tanggal movement pertama / jumlah hari (inklusif) hingga hari ini.
     * @param array<int> $itemIds
     * @return array<int,float> item_id => avg per day
     */
    protected function avgDailyTurnoverNanggewer(array $itemIds): array
    {
        if (empty($itemIds)) return [];

        $warehouseId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        if (!$warehouseId) return array_fill_keys($itemIds, 0.0);

        $rows = DB::table('stock_movements')
            ->select([
                'item_id',
                DB::raw('MIN(date) as first_date'),
                // Utamakan type='stock_out'. Fallback: quantity < 0 jika type tidak konsisten.
                DB::raw("SUM(CASE WHEN type = 'stock_out' THEN ABS(quantity) WHEN (type IS NULL OR type = '') AND quantity < 0 THEN -quantity ELSE 0 END) as total_out")
            ])
            ->where('warehouse_id', $warehouseId)
            ->whereIn('item_id', $itemIds)
            ->groupBy('item_id')
            ->get();

        $today = Carbon::today();
        $map = [];
        foreach ($rows as $row) {
            $firstDate = $row->first_date ? Carbon::parse($row->first_date) : null;
            $days = $firstDate ? $firstDate->diffInDays($today) + 1 : 0; // inclusive
            $totalOut = (float) ($row->total_out ?? 0);
            $avg = ($days > 0) ? ($totalOut / $days) : 0.0;
            $map[(int) $row->item_id] = $avg;
        }

        // Ensure keys for all requested items
        foreach ($itemIds as $id) {
            if (!array_key_exists($id, $map)) {
                $map[$id] = 0.0;
            }
        }

        return $map;
    }

    public function exportExcel(Request $request)
    {
        $searchValue = trim((string) $request->input('search', ''));
        $warehouseFilter = $request->input('warehouse_id'); // 'all' or specific id
        $categoryFilter = $request->input('category_id'); // 'all' or specific id

        if (($warehouseFilter === null || $warehouseFilter === '') && (auth()->user()?->warehouse_id === null)) {
            $defaultWarehouseId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');
            if ($defaultWarehouseId) {
                $warehouseFilter = (string) $defaultWarehouseId;
            }
        }

        $query = Inventory::query()
            ->join('items', 'inventories.item_id', '=', 'items.id')
            ->leftJoin('warehouses as w', 'inventories.warehouse_id', '=', 'w.id')
            ->leftJoin('uoms as u', 'items.uom_id', '=', 'u.id');

        if (auth()->user() && auth()->user()->warehouse_id && ($warehouseFilter === null || $warehouseFilter === '')) {
            $query->where('inventories.warehouse_id', auth()->user()->warehouse_id);
        }

        if (!empty($warehouseFilter) && $warehouseFilter !== 'all') {
            $query->where('inventories.warehouse_id', $warehouseFilter);
        }

        if (!empty($categoryFilter) && $categoryFilter !== 'all') {
            $query->where('items.item_category_id', $categoryFilter);
        }

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('items.nama_barang', 'like', "%{$searchValue}%")
                  ->orWhere('items.sku', 'like', "%{$searchValue}%");
            });
        }

        $aggregateAll = ($warehouseFilter === 'all');

        if ($aggregateAll) {
            $dataQuery = (clone $query)
                ->select([
                    'items.id as item_id',
                    'items.sku',
                    'items.nama_barang as item_name',
                    'items.koli as per_koli',
                    DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                    DB::raw('SUM(inventories.quantity) as quantity'),
                    DB::raw('SUM(inventories.koli) as koli'),
                ])
                ->groupBy('items.id', 'items.sku', 'items.nama_barang', 'items.koli');
        } else {
            $dataQuery = (clone $query)
                ->select([
                    'items.id as item_id',
                    'items.sku',
                    'items.nama_barang as item_name',
                    'items.koli as per_koli',
                    DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                    DB::raw('SUM(inventories.quantity) as quantity'),
                    DB::raw('SUM(inventories.koli) as koli'),
                    DB::raw('MAX(w.name) as warehouse_name'),
                ])
                ->groupBy('items.id', 'items.sku', 'items.nama_barang', 'items.koli');
        }

        $rows = $dataQuery->orderBy('items.nama_barang', 'asc')->get();

        $itemIds = $rows->pluck('item_id')->map(fn($v) => (int)$v)->unique()->values()->all();
        $avgTurnoverMap = $this->avgDailyTurnoverNanggewer($itemIds);

        $headings = [
            'SKU',
            'Nama Barang',
            'Per Koli',
            'Quantity',
            'Koli',
            'Avg OUT/Hari (Gudang Nanggewer)',
            'Gudang',
        ];

        $dataRows = [];
        foreach ($rows as $r) {
            $perKoli = (int) ($r->per_koli ?? 0);
            $uom = $r->uom_symbol ?? '';
            $perKoliDisplay = $uom ? ($perKoli . ' ' . $uom) : (string) $perKoli;
            $dataRows[] = [
                $r->sku,
                $r->item_name,
                $perKoliDisplay,
                (float) ($r->quantity ?? 0),
                (float) ($r->koli ?? 0),
                round((float) ($avgTurnoverMap[(int)$r->item_id] ?? 0), 2),
                $aggregateAll ? 'Semua Gudang' : ($r->warehouse_name ?? '-'),
            ];
        }

        $fileName = 'master_stok_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new \App\Exports\InventoryExport($dataRows, $headings), $fileName);
    }
}
