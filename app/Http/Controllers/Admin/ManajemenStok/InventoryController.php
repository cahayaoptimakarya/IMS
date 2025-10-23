<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

            $query = Inventory::query()
                ->join('items', 'inventories.item_id', '=', 'items.id')
                ->leftJoin('warehouses as w', 'inventories.warehouse_id', '=', 'w.id');

            // Scope to user's warehouse by default if set and no explicit filter
            if (auth()->user() && auth()->user()->warehouse_id && ($warehouseFilter === null || $warehouseFilter === '')) {
                $query->where('inventories.warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($warehouseFilter) && $warehouseFilter !== 'all') {
                $query->where('inventories.warehouse_id', $warehouseFilter);
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('items.nama_barang', 'like', "%{$searchValue}%")
                      ->orWhere('items.sku', 'like', "%{$searchValue}%");
                });
            }

            // Aggregate: if all warehouses selected (or no filter and user has no warehouse), sum across all warehouses per item
            $aggregateAll = (empty($warehouseFilter) || $warehouseFilter === 'all') && (auth()->user()->warehouse_id === null);

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

            $data = $rows->map(function ($r) use ($aggregateAll) {
                return [
                    'item_id' => (int) $r->item_id,
                    'item_name' => $r->item_name,
                    'sku' => $r->sku,
                    'per_koli' => (int) ($r->per_koli ?? 0),
                    'quantity' => (float) ($r->quantity ?? 0),
                    'koli' => (float) ($r->koli ?? 0),
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
        return view('admin.manajemenstok.masterstok.index', compact('warehouses'));
    }
}
