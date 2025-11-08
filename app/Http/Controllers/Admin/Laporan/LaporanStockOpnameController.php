<?php

namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanStockOpnameController extends Controller
{
    public function index()
    {
        $warehouses = auth()->user()->warehouse_id ? collect() : Warehouse::orderBy('name')->get();
        return view('admin.laporan.stock-opname', compact('warehouses'));
    }

    public function summary(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request->input('date_range'));
        $warehouseId = $this->resolveWarehouseId($request);

        $baseQuery = StockOpname::where('status', 'completed');

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('completed_date', [$startDate, $endDate]);
        }

        if ($warehouseId) {
            $baseQuery->where('warehouse_id', $warehouseId);
        }

        $opnameIds = (clone $baseQuery)->pluck('id');

        if ($opnameIds->isEmpty()) {
            return response()->json([
                'total_sessions' => 0,
                'total_items' => 0,
                'surplus_qty' => 0,
                'shortage_qty' => 0,
                'net_qty' => 0,
                'top_items' => [],
                'recent_sessions' => [],
            ]);
        }

        $totalSessions = $opnameIds->count();
        $itemsQuery = StockOpnameItem::whereIn('stock_opname_id', $opnameIds);

        $totalItems = (clone $itemsQuery)->count();
        $surplusQty = (clone $itemsQuery)->where('discrepancy_quantity', '>', 0)->sum('discrepancy_quantity');
        $shortageQty = (clone $itemsQuery)->where('discrepancy_quantity', '<', 0)->sum('discrepancy_quantity') * -1;
        $netQty = $surplusQty - $shortageQty;

        $topItems = (clone $itemsQuery)
            ->join('items as i', 'stock_opname_items.item_id', '=', 'i.id')
            ->select(
                'i.sku',
                DB::raw('COALESCE(i.nama_barang, i.sku) as nama_barang'),
                DB::raw('SUM(stock_opname_items.discrepancy_quantity) as total_discrepancy')
            )
            ->groupBy('i.id', 'i.sku', 'i.nama_barang')
            ->orderByRaw('ABS(SUM(stock_opname_items.discrepancy_quantity)) DESC')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'label' => trim("{$row->sku} - {$row->nama_barang}"),
                    'value' => (float) $row->total_discrepancy,
                ];
            });

        $recentSessions = $this->recentSessions($startDate, $endDate, $warehouseId);

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_items' => $totalItems,
            'surplus_qty' => (float) $surplusQty,
            'shortage_qty' => (float) $shortageQty,
            'net_qty' => (float) $netQty,
            'top_items' => $topItems,
            'recent_sessions' => $recentSessions,
        ]);
    }

    public function data(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request->input('date_range'));
        $warehouseId = $this->resolveWarehouseId($request);
        $searchValue = $request->input('search.value', '');

        $columns = [
            0 => 'so.code',
            1 => 'so.completed_date',
            2 => 'warehouse_name',
            3 => 'items_count',
            4 => 'surplus_qty',
            5 => 'shortage_qty',
        ];

        $orderColIndex = (int) $request->input('order.0.column', 1);
        $orderDir = $request->input('order.0.dir', 'desc');
        $orderColumn = $columns[$orderColIndex] ?? 'so.completed_date';

        $baseQuery = StockOpname::from('stock_opnames as so')
            ->leftJoin('warehouses as w', 'so.warehouse_id', '=', 'w.id')
            ->where('so.status', 'completed');

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('so.completed_date', [$startDate, $endDate]);
        }

        if ($warehouseId) {
            $baseQuery->where('so.warehouse_id', $warehouseId);
        }

        $totalRecords = (clone $baseQuery)->count();

        if (!empty($searchValue)) {
            $baseQuery->where(function ($q) use ($searchValue) {
                $q->where('so.code', 'LIKE', "%{$searchValue}%")
                    ->orWhere('w.name', 'LIKE', "%{$searchValue}%");
            });
        }

        $totalFiltered = (clone $baseQuery)->count();

        $dataQuery = $baseQuery
            ->leftJoin('stock_opname_items as soi', 'soi.stock_opname_id', '=', 'so.id')
            ->select(
                'so.id',
                'so.code',
                'so.completed_date',
                'w.name as warehouse_name',
                DB::raw('COALESCE(COUNT(soi.id), 0) as items_count'),
                DB::raw('COALESCE(SUM(CASE WHEN soi.discrepancy_quantity > 0 THEN soi.discrepancy_quantity ELSE 0 END), 0) as surplus_qty'),
                DB::raw('COALESCE(SUM(CASE WHEN soi.discrepancy_quantity < 0 THEN ABS(soi.discrepancy_quantity) ELSE 0 END), 0) as shortage_qty'),
                DB::raw('COALESCE(SUM(soi.discrepancy_quantity), 0) as net_qty')
            )
            ->groupBy('so.id', 'so.code', 'so.completed_date', 'w.name')
            ->orderBy($orderColumn, $orderDir)
            ->skip((int) $request->input('start', 0))
            ->take((int) $request->input('length', 10))
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $dataQuery,
        ]);
    }

    private function recentSessions($startDate, $endDate, $warehouseId)
    {
        $sessions = StockOpname::with('warehouse:id,name')
            ->where('status', 'completed');

        if ($startDate && $endDate) {
            $sessions->whereBetween('completed_date', [$startDate, $endDate]);
        }

        if ($warehouseId) {
            $sessions->where('warehouse_id', $warehouseId);
        }

        $sessions = $sessions
            ->orderByDesc('completed_date')
            ->limit(5)
            ->get();

        $stats = StockOpnameItem::whereIn('stock_opname_id', $sessions->pluck('id'))
            ->select(
                'stock_opname_id',
                DB::raw('COUNT(*) as items_count'),
                DB::raw('SUM(discrepancy_quantity) as net_qty'),
                DB::raw('SUM(CASE WHEN discrepancy_quantity > 0 THEN discrepancy_quantity ELSE 0 END) as surplus_qty'),
                DB::raw('SUM(CASE WHEN discrepancy_quantity < 0 THEN ABS(discrepancy_quantity) ELSE 0 END) as shortage_qty')
            )
            ->groupBy('stock_opname_id')
            ->get()
            ->keyBy('stock_opname_id');

        return $sessions->map(function ($session) use ($stats) {
            $stat = $stats->get($session->id);
            $completedDisplay = $session->completed_date
                ? Carbon::parse($session->completed_date)->format('d M Y')
                : '-';
            return [
                'code' => $session->code,
                'warehouse' => $session->warehouse->name ?? '-',
                'completed_date' => $completedDisplay,
                'items_count' => $stat->items_count ?? 0,
                'surplus_qty' => (float) ($stat->surplus_qty ?? 0),
                'shortage_qty' => (float) ($stat->shortage_qty ?? 0),
                'net_qty' => (float) ($stat->net_qty ?? 0),
                'detail_url' => route('admin.manajemenstok.stok-opname.show', $session->id),
            ];
        });
    }

    private function resolveDateRange(?string $dateRange): array
    {
        $defaultEnd = Carbon::now()->endOfDay();
        $defaultStart = Carbon::now()->subDays(29)->startOfDay();

        if ($dateRange && strpos($dateRange, ' to ') !== false) {
            [$start, $end] = explode(' to ', $dateRange);
            $startDate = Carbon::parse($start)->startOfDay();
            $endDate = Carbon::parse($end)->endOfDay();
            return [$startDate, $endDate];
        }

        return [$defaultStart, $defaultEnd];
    }

    private function resolveWarehouseId(Request $request): ?int
    {
        if (auth()->user()->warehouse_id) {
            return auth()->user()->warehouse_id;
        }

        $warehouse = $request->input('warehouse_id');
        if (empty($warehouse) || $warehouse === 'semua') {
            return null;
        }

        return (int) $warehouse;
    }

    public function surplusData(Request $request)
    {
        return $this->discrepancyData($request, 'surplus');
    }

    public function shortageData(Request $request)
    {
        return $this->discrepancyData($request, 'shortage');
    }

    private function discrepancyData(Request $request, string $type)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request->input('date_range'));
        $warehouseId = $this->resolveWarehouseId($request);
        $searchValue = $request->input('search.value', '');

        $baseQuery = StockOpnameItem::query()
            ->join('stock_opnames as so', 'so.id', '=', 'stock_opname_items.stock_opname_id')
            ->join('items as i', 'stock_opname_items.item_id', '=', 'i.id')
            ->where('so.status', 'completed');

        if ($startDate && $endDate) {
            $baseQuery->whereBetween('so.completed_date', [$startDate, $endDate]);
        }

        if ($warehouseId) {
            $baseQuery->where('so.warehouse_id', $warehouseId);
        }

        if ($type === 'surplus') {
            $baseQuery->where('stock_opname_items.discrepancy_quantity', '>', 0);
        } else {
            $baseQuery->where('stock_opname_items.discrepancy_quantity', '<', 0);
        }

        $select = [
            DB::raw('i.id as item_id'),
            DB::raw('i.sku as sku'),
            DB::raw('COALESCE(i.nama_barang, i.sku) as nama_barang'),
            DB::raw('SUM(stock_opname_items.discrepancy_quantity) as total_qty'),
            DB::raw('COUNT(*) as opname_count'),
        ];

        if (!empty($searchValue)) {
            $baseQuery->where(function ($q) use ($searchValue) {
                $q->where('i.sku', 'LIKE', "%{$searchValue}%")
                    ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%");
            });
        }

        $totalRecords = (clone $baseQuery)->distinct('i.id')->count('i.id');

        $dataQuery = $baseQuery
            ->select($select)
            ->groupBy('i.id', 'i.sku', 'i.nama_barang');

        $totalFiltered = (clone $dataQuery)->get()->count();

        $column = $request->input('order.0.column', 3);
        $dir = $request->input('order.0.dir', 'desc');
        $columns = [
            0 => 'sku',
            1 => 'nama_barang',
            2 => 'opname_count',
            3 => 'total_qty',
        ];
        $orderBy = $columns[$column] ?? 'total_qty';

        if ($orderBy === 'total_qty') {
            $dataQuery->orderByRaw('total_qty ' . $dir);
        } else {
            $dataQuery->orderBy($orderBy, $dir);
        }

        $data = $dataQuery
            ->skip((int) $request->input('start', 0))
            ->take((int) $request->input('length', 5))
            ->get()
            ->map(function ($row) use ($type) {
                $qty = (float) $row->total_qty;
                if ($type === 'shortage') {
                    $qty = abs($qty);
                }

                return [
                    'sku' => $row->sku,
                    'nama_barang' => $row->nama_barang,
                    'opname_count' => $row->opname_count,
                    'total_qty' => $qty,
                ];
            });

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }
}
