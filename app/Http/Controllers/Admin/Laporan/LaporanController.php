<?php


namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Item;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function laporanPergerakanBarang(Request $request)
    {
        // --- Date Range Filter ---
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $dateRange = $request->input('date_range');

        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if(count($dates) == 2) {
                $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
            }
        }

        // Resolve active warehouse: user's warehouse if set; otherwise request filter; fallback to Gudang Nanggewer
        $userWarehouseId = auth()->user()?->warehouse_id;
        $requestedWarehouseId = $request->input('warehouse_filter');
        $fallbackWarehouseId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        $activeWarehouseId = $userWarehouseId ?: ($requestedWarehouseId ?: $fallbackWarehouseId);
        $activeWarehouse = $activeWarehouseId ? Warehouse::find($activeWarehouseId) : null;

        // --- Base Query for all data (range-bound) ---
        $baseQuery = StockMovement::whereBetween('date', [$startDate, $endDate])
            ->when($activeWarehouseId, fn($q) => $q->where('warehouse_id', $activeWarehouseId));

        if ($request->filled('type_filter') && $request->input('type_filter') !== 'semua') {
            $baseQuery->where('type', $request->type_filter);
        }
        // Note: warehouse filter is already captured via $activeWarehouseId
        if ($request->filled('item_filter')) {
            $baseQuery->where('item_id', $request->item_filter);
        }

        // Table now uses DataTables (AJAX). No need to fetch paginated data here.

        // --- Data for Statistic Widgets ---
        $stats = [
            'total_movements' => (clone $baseQuery)->count(),
            'total_in' => (clone $baseQuery)->whereIn('type', ['stock_in', 'transfer_in', 'adjustment'])->where('quantity', '>', 0)->sum('quantity'),
            'total_out' => (clone $baseQuery)->whereIn('type', ['stock_out', 'transfer_out', 'adjustment'])->where('quantity', '<', 0)->sum('quantity') * -1,
        ];

        // --- Data for Line Chart (Trend) ---
        $trendResult = (clone $baseQuery)
            ->select(
                DB::raw('DATE(date) as movement_date'),
                DB::raw("SUM(CASE WHEN type IN ('stock_in', 'transfer_in') OR (type = 'adjustment' AND quantity > 0) THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type IN ('stock_out', 'transfer_out') OR (type = 'adjustment' AND quantity < 0) THEN ABS(quantity) ELSE 0 END) as total_out")
            )
            ->groupBy('movement_date')
            ->orderBy('movement_date', 'asc')
            ->get();

        $trendData = [
            'dates' => $trendResult->pluck('movement_date')->map(function ($date) {
                return Carbon::parse($date)->format('d M');
            }),
            'stock_in' => $trendResult->pluck('total_in'),
            'stock_out' => $trendResult->pluck('total_out'),
        ];

        // --- Data for Pie Chart (Movement Type) ---
        $pieChartResult = (clone $baseQuery)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $pieChartData = [
            'labels' => $pieChartResult->pluck('type')->map(function($type) {
                return ucwords(str_replace('_', ' ', $type));
            }),
            'series' => $pieChartResult->pluck('count'),
        ];
        
        // --- Additional useful aggregations ---
        // Top outgoing items (by absolute quantity) within range
        $topOutgoing = (clone $baseQuery)
            ->leftJoin('items as i', 'stock_movements.item_id', '=', 'i.id')
            ->leftJoin('uoms as u', 'i.uom_id', '=', 'u.id')
            ->select(
                'i.id as item_id',
                'i.sku as sku',
                'i.koli as per_koli',
                DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)),0) as total_out'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_movements.koli < 0 THEN -stock_movements.koli ELSE 0 END),0) as total_out_koli')
            )
            ->where('stock_movements.quantity', '<', 0)
            ->groupBy('i.id', 'i.sku', 'i.koli')
            ->orderByDesc('total_out')
            ->limit(10)
            ->get();

        // Top incoming items (by quantity) within range
        $topIncoming = (clone $baseQuery)
            ->leftJoin('items as i', 'stock_movements.item_id', '=', 'i.id')
            ->leftJoin('uoms as u', 'i.uom_id', '=', 'u.id')
            ->select(
                'i.id as item_id',
                'i.sku as sku',
                'i.koli as per_koli',
                DB::raw('MAX(COALESCE(u.symbol, u.name)) as uom_symbol'),
                DB::raw('COALESCE(SUM(stock_movements.quantity),0) as total_in'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_movements.koli > 0 THEN stock_movements.koli ELSE 0 END),0) as total_in_koli')
            )
            ->where('stock_movements.quantity', '>', 0)
            ->groupBy('i.id', 'i.sku', 'i.koli')
            ->orderByDesc('total_in')
            ->limit(10)
            ->get();

        // Negative stock events count
        $negativeEvents = (clone $baseQuery)
            ->where('stock_after', '<', 0)
            ->count();

        // Average daily outflow per SKU (since first movement for that SKU, all-time) on active warehouse
        $avgDailyMovement = $this->avgDailyOutflowPerSku($activeWarehouseId);

        // Movement by warehouse (sum of absolute quantity)
        $warehouseMovement = (clone $baseQuery)
            ->leftJoin('warehouses as w', 'stock_movements.warehouse_id', '=', 'w.id')
            ->select('stock_movements.warehouse_id', 'w.name as warehouse_name', DB::raw('COALESCE(SUM(ABS(stock_movements.quantity)),0) as movement_sum'))
            ->groupBy('stock_movements.warehouse_id', 'w.name')
            ->orderByDesc('movement_sum')
            ->get();
        
        $warehouses = auth()->user() && auth()->user()->warehouse_id
            ? Warehouse::where('id', auth()->user()->warehouse_id)->get()
            : Warehouse::all();
        $items = Item::orderBy('nama_barang')->get(['id','sku','nama_barang']);

        return view('admin.laporan.pergerakan-barang', compact(
            'stats',
            'trendData',
            'pieChartData',
            'warehouses',
            'items',
            'dateRange',
            'topOutgoing',
            'topIncoming',
            'negativeEvents',
            'avgDailyMovement',
            'warehouseMovement',
            'activeWarehouse'
        ));
    }

    public function pergerakanBarangData(Request $request)
    {
        $searchValue = $request->input('search.value', '');
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $draw = $request->input('draw', 0);

        // Filters
        $dateRange = $request->input('date_range');
        $typeFilter = $request->input('type_filter');
        $warehouseFilter = $request->input('warehouse_filter');
        $itemFilter = $request->input('item_filter');

        // Base query with joins
        $query = StockMovement::query()->from('stock_movements as sm')->select([
            'sm.date',
            'i.nama_barang as item_name',
            'i.sku as sku',
            'w.name as warehouse_name',
            'sm.type',
            'sm.description',
            'sm.stock_before',
            'sm.quantity',
            'sm.stock_after',
            'u.name as user_name',
        ])
        ->leftJoin('items as i', 'sm.item_id', '=', 'i.id')
        ->leftJoin('warehouses as w', 'sm.warehouse_id', '=', 'w.id')
        ->leftJoin('users as u', 'sm.user_id', '=', 'u.id');

        // Resolve active warehouse similar to page view
        $userWarehouseId = auth()->user()?->warehouse_id;
        $fallbackWarehouseId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        $activeWarehouseId = $userWarehouseId ?: ($warehouseFilter ?: $fallbackWarehouseId);
        if ($activeWarehouseId) {
            $query->where('sm.warehouse_id', $activeWarehouseId);
        }

        // Date range
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) == 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                    $query->whereBetween('sm.date', [$startDate, $endDate]);
                } catch (\Exception $e) {
                    // ignore invalid date format
                }
            }
        }

        // Other filters
        if ($typeFilter && $typeFilter !== 'semua') {
            $query->where('sm.type', $typeFilter);
        }
        // Warehouse already applied above
        if ($itemFilter) {
            $query->where('sm.item_id', $itemFilter);
        }

        $totalRecords = (clone $query)->count();

        // Search
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('i.nama_barang', 'LIKE', "%{$searchValue}%")
                  ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                  ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sm.description', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sm.type', 'LIKE', "%{$searchValue}%")
                  ->orWhere('u.name', 'LIKE', "%{$searchValue}%");
            });
        }

        $totalFiltered = (clone $query)->count();

        // Ordering
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columnsMap = [
            0 => 'sm.date',
            1 => 'i.nama_barang',
            2 => 'w.name',
            3 => 'sm.type',
            4 => 'sm.description',
            5 => 'sm.stock_before',
            6 => 'sm.quantity',
            7 => 'sm.stock_after',
            8 => 'u.name',
        ];
        $orderBy = $columnsMap[$orderColumnIndex] ?? 'sm.date';

        $data = $query->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($row) {
                $row->date = Carbon::parse($row->date)->format('d M Y, H:i');
                return $row;
            });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    /**
     * Rata-rata keluar harian per SKU pada warehouse tertentu.
     * Definisi per SKU: SUM(OUT quantity) / jumlah hari sejak tanggal movement pertama SKU tsb (inklusif) hingga hari ini.
     * Nilai akhirnya adalah rata-rata dari semua SKU yang memiliki pergerakan OUT (>0 total_out).
     */
    protected function avgDailyOutflowPerSku(?int $warehouseId): float
    {
        if (!$warehouseId) return 0.0;

        $rows = DB::table('stock_movements')
            ->select([
                'item_id',
                DB::raw('MIN(date) as first_date'),
                DB::raw("SUM(CASE WHEN type = 'stock_out' THEN ABS(quantity) WHEN (type IS NULL OR type = '') AND quantity < 0 THEN -quantity ELSE 0 END) as total_out"),
            ])
            ->where('warehouse_id', $warehouseId)
            ->groupBy('item_id')
            ->get();

        $today = Carbon::today();
        $values = [];
        foreach ($rows as $r) {
            $totalOut = (float) ($r->total_out ?? 0);
            if ($totalOut <= 0) continue;
            $firstDate = $r->first_date ? Carbon::parse($r->first_date) : null;
            $days = $firstDate ? max(1, $firstDate->diffInDays($today) + 1) : 0;
            if ($days <= 0) continue;
            $values[] = $totalOut / $days;
        }

        if (empty($values)) return 0.0;
        return round(array_sum($values) / count($values), 2);
    }
}
