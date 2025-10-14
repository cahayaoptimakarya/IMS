<?php

namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\ItemCategory;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanStokController extends Controller
{
    public function index(Request $request)
    {
        // Datatable request
        if ($request->ajax() && $request->has('draw')) {
            $userWarehouseId = auth()->user()->warehouse_id;
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $warehouseFilter = $request->input('warehouse_filter', $userWarehouseId);
            $categoryFilter = $request->input('category_filter');
            $itemFilter = $request->input('item_filter');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if (empty($startDate) || empty($endDate)) {
                $endDate = Carbon::now()->format('Y-m-d');
                $startDate = Carbon::now()->subDays(29)->format('Y-m-d');
            }

            $query = Inventory::with(['item.uom', 'item.itemCategory', 'warehouse'])
                ->select('inventories.*');

            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $query->where('inventories.warehouse_id', $warehouseFilter);
            }

            if ($categoryFilter && $categoryFilter !== 'semua') {
                $query->whereHas('item', function ($q) use ($categoryFilter) {
                    $q->where('item_category_id', $categoryFilter);
                });
            }
            if (!empty($itemFilter)) {
                $query->where('inventories.item_id', $itemFilter);
            }
            
            $totalRecords = (clone $query)->count();

            if (!empty($searchValue)) {
                $query->whereHas('item', function ($itemQuery) use ($searchValue) {
                    $itemQuery->where('nama_barang', 'like', "%{$searchValue}%")
                              ->orWhere('sku', 'like', "%{$searchValue}%");
                });
            }

            // Enrich with movement metrics based on stock_movements schema
            // Opening balance before startDate
            $query->selectSub(function ($q) use ($startDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_in' THEN quantity ELSE -quantity END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->where('date', '<', $startDate);
            }, 'opening_qty');

            // In-range movement summaries (quantity)
            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_in' THEN quantity ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'moved_in_qty');

            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_out' THEN quantity ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'moved_out_qty');

            // Koli metrics
            $query->selectSub(function ($q) use ($startDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_in' THEN koli ELSE -koli END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->where('date', '<', $startDate);
            }, 'opening_koli');

            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_in' THEN koli ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'moved_in_koli');

            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'stock_out' THEN koli ELSE 0 END), 0)")
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'moved_out_koli');

            // Movement count & last movement date in range
            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'movements_count');

            $query->selectSub(function ($q) use ($startDate, $endDate) {
                $q->from('stock_movements')
                    ->selectRaw('MAX(date)')
                    ->whereColumn('stock_movements.item_id', 'inventories.item_id')
                    ->whereColumn('stock_movements.warehouse_id', 'inventories.warehouse_id')
                    ->whereBetween('date', [$startDate, $endDate]);
            }, 'last_movement_date');

            // Note: closing_qty computed below after fetch to avoid alias dependency issues

            // Note: removed 'has_movement' filter per request

            $totalFiltered = (clone $query)->count();

            $data = $query->orderBy('id', 'desc')
                ->offset($start)
                ->limit($length)
                ->get();

            // Post-process computed fields depending on prior subselects
            $data->transform(function ($row) {
                $opening = (float)($row->opening_qty ?? 0);
                $in = (float)($row->moved_in_qty ?? 0);
                $out = (float)($row->moved_out_qty ?? 0);
                $closing = $opening + $in - $out;
                $row->closing_qty = $closing;

                // Simple turnover metric: out / avg stock
                $avgStock = ($opening + $closing) / 2.0;
                $row->stock_turnover = $avgStock > 0 ? round($out / $avgStock, 4) : 0.0;

                // Koli
                $openingK = (float)($row->opening_koli ?? 0);
                $inK = (float)($row->moved_in_koli ?? 0);
                $outK = (float)($row->moved_out_koli ?? 0);
                $closingK = $openingK + $inK - $outK;
                $row->closing_koli = $closingK;
                return $row;
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        // Summary data request
        if ($request->ajax()) {
            $userWarehouseId = auth()->user()->warehouse_id;
            $warehouseFilter = $request->input('warehouse_filter', $userWarehouseId);
            $categoryFilter = $request->input('category_filter');
            $itemFilter = $request->input('item_filter');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $referenceFilter = $request->input('reference_type'); // optional breakdown filter

            if (empty($startDate) || empty($endDate)) {
                $endDate = Carbon::now()->format('Y-m-d');
                $startDate = Carbon::now()->subDays(29)->format('Y-m-d');
            }

            $totalUniqueItems = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                $q->where('item_id', $itemFilter);
            })
            ->distinct('item_id')->count();

            $totalStockQuantity = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                $q->where('item_id', $itemFilter);
            })
            ->sum('quantity');

            // Low stock count using per-item threshold
            $lowStockItems = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                $q->where('item_id', $itemFilter);
            })
            ->whereHas('item', function($q){
                $q->whereColumn('inventories.quantity', '<=', 'items.low_stock_threshold');
            })
            ->count();

            // Trends based on schema: use 'date' column and type in ('stock_in','stock_out')
            $stockMovements = StockMovement::select(
                DB::raw('date as date'),
                DB::raw("SUM(CASE WHEN type = 'stock_in' THEN quantity ELSE 0 END) as stock_in"),
                DB::raw("SUM(CASE WHEN type = 'stock_out' THEN quantity ELSE 0 END) as stock_out")
            )
                ->when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', $warehouseFilter);
                })
                ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                    $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                        $itemQuery->where('item_category_id', $categoryFilter);
                    });
                })
                ->when($referenceFilter, function ($q) use ($referenceFilter) {
                    $q->where('reference_type', $referenceFilter);
                })
                ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                    $q->where('item_id', $itemFilter);
                })
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $trendData = [
                'dates' => $stockMovements->pluck('date')->map(fn($d) => Carbon::parse($d)->format('d M')),
                'stock_in' => $stockMovements->pluck('stock_in'),
                'stock_out' => $stockMovements->pluck('stock_out'),
            ];

            $topItems = Inventory::select('item_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('item:id,nama_barang')
                ->when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', $warehouseFilter);
                })
                ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                    $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                        $itemQuery->where('item_category_id', $categoryFilter);
                    });
                })
                ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                    $q->where('item_id', $itemFilter);
                })
                ->groupBy('item_id')
                ->orderBy('total_quantity', 'desc')
                ->take(5)
                ->get();

            $topItemsData = [
                'names' => $topItems->map(function($inventory) { return $inventory->item->nama_barang ?? 'N/A'; }),
                'quantities' => $topItems->pluck('total_quantity'),
            ];

            // Extra breakdowns by reference_type within range (useful quick metrics)
            $breakdowns = StockMovement::select(
                'reference_type',
                DB::raw("SUM(CASE WHEN type = 'stock_in' THEN quantity ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type = 'stock_out' THEN quantity ELSE 0 END) as total_out")
            )
                ->when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                    $q->where('warehouse_id', $warehouseFilter);
                })
                ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                    $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                        $itemQuery->where('item_category_id', $categoryFilter);
                    });
                })
                ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                    $q->where('item_id', $itemFilter);
                })
                ->whereBetween('date', [$startDate, $endDate])
                ->groupBy('reference_type')
                ->get();

            // Total koli for summary card
            $totalStockKoli = Inventory::when($warehouseFilter && $warehouseFilter !== 'semua', function ($q) use ($warehouseFilter) {
                $q->where('warehouse_id', $warehouseFilter);
            })
            ->when($categoryFilter && $categoryFilter !== 'semua', function($q) use ($categoryFilter) {
                $q->whereHas('item', function($itemQuery) use ($categoryFilter) {
                    $itemQuery->where('item_category_id', $categoryFilter);
                });
            })
            ->when(!empty($itemFilter), function ($q) use ($itemFilter) {
                $q->where('item_id', $itemFilter);
            })
            ->sum('koli');

            return response()->json([
                'totalUniqueItems' => $totalUniqueItems,
                'totalStockQuantity' => number_format($totalStockQuantity, 2, ",", "."),
                'totalStockKoli' => number_format($totalStockKoli, 2, ",", "."),
                'lowStockItems' => $lowStockItems,
                'trendData' => $trendData,
                'topItemsData' => $topItemsData,
                'breakdowns' => $breakdowns,
            ]);
        }

        $userWarehouseId = auth()->user()->warehouse_id;
        $hideWarehouseFilter = !is_null($userWarehouseId);
        $warehouses = $userWarehouseId ? Warehouse::where('id', $userWarehouseId)->get() : Warehouse::all();
        $categories = ItemCategory::all();
        $items = Item::select('id','sku','nama_barang')->orderBy('nama_barang')->get();
        $referenceTypes = StockMovement::select('reference_type')->distinct()->pluck('reference_type');

        return view('admin.laporan.laporan-stok.index', compact(
            'warehouses',
            'categories',
            'items',
            'referenceTypes',
            'hideWarehouseFilter'
        ));
    }

    public function lowStockReport(Request $request)
    {
        $userWarehouseId = auth()->user()->warehouse_id;
        $hideWarehouseFilter = !is_null($userWarehouseId);

        $warehouses = $userWarehouseId ? Warehouse::where('id', $userWarehouseId)->get() : Warehouse::all();
        $selectedWarehouseId = $request->input('warehouse_filter', $userWarehouseId);
        $searchQuery = $request->input('search');

        $inventories = Inventory::with(['item.uom', 'item.itemCategory', 'warehouse'])
            ->when($selectedWarehouseId && $selectedWarehouseId !== 'semua', function ($q) use ($selectedWarehouseId) {
                $q->where('warehouse_id', $selectedWarehouseId);
            })
            ->when($searchQuery, function ($q) use ($searchQuery) {
                $q->whereHas('item', function ($itemQuery) use ($searchQuery) {
                    $itemQuery->where('nama_barang', 'like', "%{$searchQuery}%")
                              ->orWhere('sku', 'like', "%{$searchQuery}%");
                });
            })
            ->whereHas('item', function($q){
                $q->whereColumn('inventories.quantity', '<=', 'items.low_stock_threshold');
            })
            ->select('inventories.*')
            ->paginate(10);

        return view('admin.laporan.laporan-stok.menipis', compact(
            'inventories',
            'warehouses',
            'selectedWarehouseId',
            'hideWarehouseFilter'
        ));
    }
}
