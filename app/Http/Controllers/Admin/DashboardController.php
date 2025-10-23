<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockInOrder;
use App\Models\StockOut;
use App\Models\TransferRequest;
use App\Models\StockMovement;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $warehouseId = $user->warehouse_id;
        $overMultiplier = 5;

        if ($warehouseId) {
            // Data for users with a warehouse
            $recentActivities = UserActivity::where('user_id', $user->id)
                ->latest()
                ->take(5)
                ->get();

            // Stock-in today
            $todayStockIn = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_in')
                ->whereDate('date', today())
                ->sum('quantity');
            $todayStockInKoli = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_in')
                ->whereDate('date', today())
                ->sum('koli');

            // Stock-out today: sum only from movements linked to stock_out_items
            $todayStockOut = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_out')
                ->where('reference_type', 'stock_out_items')
                ->whereDate('date', today())
                ->sum('quantity');
            $todayStockOutKoli = StockMovement::where('warehouse_id', $warehouseId)
                ->where('type', 'stock_out')
                ->where('reference_type', 'stock_out_items')
                ->whereDate('date', today())
                ->sum('koli');

            // Incoming transfers en route to this warehouse
            $pendingTransfers = TransferRequest::where('to_warehouse_id', $warehouseId)
                ->where('status', 'on_progress')
                ->count();

            // Stock reminders (warehouse scoped) using per-item low_stock_threshold
            $invJoin = Inventory::query()
                ->join('items','inventories.item_id','=','items.id')
                ->where('inventories.warehouse_id', $warehouseId);
            $zeroStockItems = (clone $invJoin)->where('inventories.koli','<=',0)->count();
            $lowStockItems = (clone $invJoin)
                ->where('inventories.koli','>',0)
                ->whereColumn('inventories.koli','<=','items.low_stock_threshold')
                ->count();
            $optimalStockItems = (clone $invJoin)
                ->whereColumn('inventories.koli','>','items.low_stock_threshold')
                ->whereRaw('inventories.koli <= (items.low_stock_threshold * ?)', [$overMultiplier])
                ->count();
            $overStockItems = (clone $invJoin)
                ->whereRaw('inventories.koli > (items.low_stock_threshold * ?)', [$overMultiplier])
                ->count();

            $stockChartData = $this->getStockChartData($warehouseId);

            // Stock summary (warehouse scoped)
            $invScope = Inventory::where('warehouse_id', $warehouseId);
            $totalSkus = (clone $invScope)->distinct('item_id')->count('item_id');

            // Stock In status counts (warehouse scoped)
            $stockInRequested = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'requested')->count();
            $stockInShipped = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'on_progress')->count();
            $stockInCompleted = StockInOrder::where('warehouse_id', $warehouseId)->where('status', 'completed')->count();

            // Recent movements & low stock list
            $recentMovements = StockMovement::with('item')
                ->where('warehouse_id', $warehouseId)
                ->latest('id')
                ->take(5)
                ->get();
            $topLowStocks = Inventory::with('item')
                ->where('warehouse_id', $warehouseId)
                ->orderBy('koli', 'asc')
                ->take(5)
                ->get();

            return view('admin.dashboard.index', compact(
                'user',
                'recentActivities',
                'todayStockIn',
                'todayStockOut',
                'todayStockInKoli',
                'todayStockOutKoli',
                'pendingTransfers',
                'lowStockItems',
                'zeroStockItems',
                'optimalStockItems',
                'overStockItems',
                'stockChartData',
                'totalSkus',
                'stockInRequested',
                'stockInShipped',
                'stockInCompleted',
                'recentMovements',
                'topLowStocks'
            ));
        } else {
            // Data for users without a warehouse (system-wide)
            $totalUsers = User::count();
            $totalWarehouses = Warehouse::count();
            $todayStockIn = StockMovement::where('type', 'stock_in')
                ->whereDate('date', today())
                ->sum('quantity');
            $todayStockInKoli = StockMovement::where('type', 'stock_in')
                ->whereDate('date', today())
                ->sum('koli');
            $todayStockOut = StockMovement::where('type', 'stock_out')
                ->whereDate('date', today())
                ->sum('quantity');
            $todayStockOutKoli = StockMovement::where('type', 'stock_out')
                ->whereDate('date', today())
                ->sum('koli');
            $recentActivities = UserActivity::with('user')->latest()->take(5)->get();

            // Stock reminders (system-wide) using per-item low_stock_threshold
            $invAll = Inventory::query()->join('items','inventories.item_id','=','items.id');
            $zeroStockItems = (clone $invAll)->where('inventories.koli','<=',0)->count();
            $lowStockItems = (clone $invAll)->where('inventories.koli','>',0)->whereColumn('inventories.koli','<=','items.low_stock_threshold')->count();
            $optimalStockItems = (clone $invAll)
                ->whereColumn('inventories.koli','>','items.low_stock_threshold')
                ->whereRaw('inventories.koli <= (items.low_stock_threshold * ?)', [$overMultiplier])
                ->count();
            $overStockItems = (clone $invAll)->whereRaw('inventories.koli > (items.low_stock_threshold * ?)', [$overMultiplier])->count();
            $stockChartData = $this->getStockChartData(null);

            // Stock summary (system-wide)
            $totalSkus = Inventory::distinct('item_id')->count('item_id');

            $stockInRequested = StockInOrder::where('status', 'requested')->count();
            $stockInShipped = StockInOrder::where('status', 'on_progress')->count();
            $stockInCompleted = StockInOrder::where('status', 'completed')->count();

            $recentMovements = StockMovement::with('item')->latest('id')->take(5)->get();
            $topLowStocks = Inventory::with('item')->orderBy('koli', 'asc')->take(5)->get();

            return view('admin.dashboard.index', compact(
                'user',
                'totalUsers',
                'totalWarehouses',
                'todayStockIn',
                'todayStockOut',
                'todayStockInKoli',
                'todayStockOutKoli',
                'recentActivities',
                'zeroStockItems',
                'lowStockItems',
                'optimalStockItems',
                'overStockItems',
                'stockChartData',
                'totalSkus',
                'stockInRequested',
                'stockInShipped',
                'stockInCompleted',
                'recentMovements',
                'topLowStocks'
            ));
        }
    }

    private function getStockChartData($warehouseId)
    {
        $dates = collect();
        for ($i = 6; $i >= 0; $i--) {
            $dates->push(today()->subDays($i)->toDateString());
        }

        // Use completed Goods Receipts to represent actual stock-in events
        $stockInQuery = GoodsReceipt::where('status', GoodsReceipt::STATUS_COMPLETED)
            ->whereBetween('completed_at', [today()->subDays(6), today()->endOfDay()]);

        $stockOutQuery = StockOut::whereBetween('date', [today()->subDays(6), today()->endOfDay()]);

        if ($warehouseId) {
            $stockInQuery->where('warehouse_id', $warehouseId);
            $stockOutQuery->where('warehouse_id', $warehouseId);
        }

        $stockIn = $stockInQuery->select(DB::raw('DATE(completed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(completed_at)'))
            ->pluck('count', 'date');

        $stockOut = $stockOutQuery->select(DB::raw('DATE(date) as date'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('DATE(date)'))
            ->pluck('count', 'date');

        $chartData = [
            'labels' => $dates->map(function ($date) {
                return date('d M', strtotime($date));
            }),
            'stock_in' => $dates->map(function ($date) use ($stockIn) {
                return $stockIn->get($date, 0);
            }),
            'stock_out' => $dates->map(function ($date) use ($stockOut) {
                return $stockOut->get($date, 0);
            }),
        ];

        return $chartData;
    }

    public function stockBuckets(Request $request)
    {
        $user = Auth::user();
        $warehouseId = $user->warehouse_id;
        $overMultiplier = 5;

        $base = Inventory::query()
            ->join('items','inventories.item_id','=','items.id')
            ->leftJoin('warehouses','inventories.warehouse_id','=','warehouses.id')
            ->select(
                'inventories.item_id','inventories.warehouse_id','inventories.quantity','inventories.koli',
                'items.nama_barang','items.sku','items.low_stock_threshold',
                DB::raw('COALESCE(warehouses.name, "-") as warehouse_name')
            );
        if ($warehouseId) {
            $base->where('inventories.warehouse_id', $warehouseId);
        }

        $mapFn = function($row){
            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'nama_barang' => $row->nama_barang,
                'warehouse' => $row->warehouse_name,
                'qty' => (float) $row->quantity,
                'koli' => (float) ($row->koli ?? 0),
                'threshold' => (int) ($row->low_stock_threshold ?? 0),
            ];
        };

        $out = (clone $base)->where('inventories.koli','<=',0)->get()->map($mapFn)->values();
        $low = (clone $base)
            ->where('inventories.koli','>',0)
            ->whereColumn('inventories.koli','<=','items.low_stock_threshold')
            ->get()->map($mapFn)->values();
        $safe = (clone $base)
            ->whereColumn('inventories.koli','>','items.low_stock_threshold')
            ->whereRaw('inventories.koli <= (items.low_stock_threshold * ?)', [$overMultiplier])
            ->get()->map($mapFn)->values();
        $over = (clone $base)
            ->whereRaw('inventories.koli > (items.low_stock_threshold * ?)', [$overMultiplier])
            ->get()->map($mapFn)->values();

        return response()->json([
            'out_of_stock' => ['count' => $out->count(), 'items' => $out],
            'low_stock' => ['count' => $low->count(), 'items' => $low],
            'safe' => ['count' => $safe->count(), 'items' => $safe],
            'over_stock' => ['count' => $over->count(), 'items' => $over],
        ]);
    }
}
