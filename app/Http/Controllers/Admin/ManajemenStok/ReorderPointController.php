<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\ItemReorderMetric;
use App\Models\Warehouse;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReorderPointController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $categories = ItemCategory::orderBy('name')->get();
        $defaultWarehouseId = auth()->user()?->warehouse_id ?? Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        return view('admin.manajemenstok.reorderpoint.index', compact('warehouses', 'categories', 'defaultWarehouseId'));
    }

    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) data_get($request->input('search', []), 'value', ''));
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $onlyNeedsReorder = filter_var($request->input('only_reorder'), FILTER_VALIDATE_BOOLEAN);

        $query = ItemReorderMetric::query()
            ->from('item_reorder_metrics as m')
            ->join('items as i', 'm.item_id', '=', 'i.id')
            ->leftJoin('inventories as inv', function($j){ $j->on('inv.item_id', '=', 'm.item_id')->on('inv.warehouse_id', '=', 'm.warehouse_id'); })
            ->leftJoin('uoms as u', 'i.uom_id', '=', 'u.id')
            ->leftJoin('warehouses as w', 'm.warehouse_id', '=', 'w.id')
            ->select([
                'm.item_id','m.warehouse_id','m.lead_time_po','m.lead_time_safety_stock','m.safety_stock_qty','m.rop_qty','m.rop_koli',
                'i.sku','i.nama_barang','i.koli as per_koli',
                DB::raw('COALESCE(inv.quantity,0) as onhand_qty'),
                DB::raw('COALESCE(inv.koli,0) as onhand_koli'),
                DB::raw('COALESCE(u.symbol, u.name) as uom_symbol'),
                'w.name as warehouse_name'
            ]);

        // default warehouse behavior
        if (empty($warehouseId)) {
            $warehouseId = auth()->user()?->warehouse_id ?? Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        }
        if (!empty($warehouseId) && $warehouseId !== 'all') {
            $query->where('m.warehouse_id', $warehouseId);
        }
        if (!empty($categoryId) && $categoryId !== 'all') {
            $query->where('i.item_category_id', $categoryId);
        }
        if ($onlyNeedsReorder) {
            $query->whereRaw('COALESCE(inv.quantity,0) < m.rop_qty');
        }
        if ($search !== '') {
            $query->where(function($q) use ($search){
                $q->where('i.sku', 'like', "%{$search}%")
                  ->orWhere('i.nama_barang', 'like', "%{$search}%")
                  ->orWhere('w.name', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();
        $rows = $query->orderBy('i.nama_barang')
            ->offset($start)
            ->limit($length)
            ->get();

        $data = $rows->map(function($r){
            $gapQty = (float)$r->rop_qty - (float)$r->onhand_qty;
            $status = $gapQty > 0 ? 'reorder' : 'ok';
            return [
                'sku' => $r->sku,
                'nama_barang' => $r->nama_barang,
                'warehouse' => $r->warehouse_name,
                'lead_time_po' => (int)$r->lead_time_po,
                'lead_time_safety_stock' => (int)$r->lead_time_safety_stock,
                'safety_stock_qty' => (float)$r->safety_stock_qty,
                'rop_qty' => (float)$r->rop_qty,
                'onhand_qty' => (float)$r->onhand_qty,
                'rop_koli' => (float)$r->rop_koli,
                'onhand_koli' => (float)$r->onhand_koli,
                'gap_qty' => $gapQty,
                'status' => $status,
                'uom' => $r->uom_symbol,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }
}
