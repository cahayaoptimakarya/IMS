<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\ItemReorderMetric;
use App\Models\Warehouse;
use App\Models\ItemCategory;
use App\Models\ItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReorderPointExport;
use App\Services\ReorderPointService;

class ReorderPointController extends Controller
{
    public function index(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $categories = ItemCategory::orderBy('name')->get();
        $itemTypes = ItemType::orderBy('name')->get();
        $defaultWarehouseId = auth()->user()?->warehouse_id ?? Warehouse::where('name', 'Gudang Nanggewer')->value('id');
        return view('admin.manajemenstok.reorderpoint.index', compact('warehouses', 'categories', 'itemTypes', 'defaultWarehouseId'));
    }

    public function data(Request $request)
    {
        $draw = (int) $request->input('draw', 0);
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $search = trim((string) data_get($request->input('search', []), 'value', ''));
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $typeId = $request->input('item_type_id');
        $status = $request->input('status'); // all|reorder|ok
        // Remove only_reorder filter: always show all rows

        $query = ItemReorderMetric::query()
            ->from('item_reorder_metrics as m')
            ->join('items as i', 'm.item_id', '=', 'i.id')
            ->leftJoin('inventories as inv', function($j){ $j->on('inv.item_id', '=', 'm.item_id')->on('inv.warehouse_id', '=', 'm.warehouse_id'); })
            ->leftJoin('uoms as u', 'i.uom_id', '=', 'u.id')
            ->leftJoin('warehouses as w', 'm.warehouse_id', '=', 'w.id')
            ->select([
                'm.item_id','m.warehouse_id','m.lead_time_po','m.lead_time_safety_stock','m.safety_stock_qty','m.rop_qty','m.rop_koli',
                'i.sku','i.nama_barang','i.koli as per_koli',
                DB::raw('inv.quantity as onhand_qty'),
                DB::raw('inv.koli as onhand_koli'),
                DB::raw('COALESCE(u.symbol, u.name) as uom_symbol'),
                'w.name as warehouse_name',
                // Expose raw totals for 30/60/90 days and age; compute avg in PHP
                DB::raw('COALESCE(s.total_out_30,0) as total_out_30'),
                DB::raw('COALESCE(s.total_out_60,0) as total_out_60'),
                DB::raw('COALESCE(s.total_out_90,0) as total_out_90'),
                DB::raw('COALESCE(s.days_since_first,0) as days_since_first'),
                DB::raw('COALESCE(oo.on_order_qty,0) as on_order_qty')
            ]);

        // Build subquery totals for 30/60/90 and age
        $today = now()->startOfDay();
        $start30 = $today->copy()->subDays(30 - 1)->toDateString();
        $start60 = $today->copy()->subDays(60 - 1)->toDateString();
        $start90 = $today->copy()->subDays(90 - 1)->toDateString();
        $sub = DB::table('stock_movements as sm')
            ->select([
                'sm.item_id',
                'sm.warehouse_id',
                DB::raw("MIN(DATE(sm.date)) as first_life_date"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start30}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_30"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start60}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_60"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start90}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_90"),
                DB::raw("CASE WHEN MIN(DATE(sm.date)) IS NULL THEN 0 ELSE DATEDIFF(CURDATE(), MIN(DATE(sm.date))) + 1 END as days_since_first"),
            ])
            ->groupBy('sm.item_id', 'sm.warehouse_id');

        $query->leftJoinSub($sub, 's', function($j){
            $j->on('s.item_id', '=', 'm.item_id')->on('s.warehouse_id', '=', 'm.warehouse_id');
        });

        // On-order qty per item+warehouse from Stock In Orders (requested/on_shipping; item pending/on_progress)
        // Using remaining_quantity so orders counted even if distributions belum dibuat
        $onOrder = DB::table('stock_in_order_items as si')
            ->join('stock_in_orders as sio', 'si.stock_in_order_id', '=', 'sio.id')
            ->select([
                'si.item_id',
                'sio.warehouse_id as warehouse_id',
                DB::raw('SUM(COALESCE(si.remaining_quantity, 0)) as on_order_qty'),
            ])
            ->whereIn('sio.status', ['requested', 'on_shipping'])
            ->whereIn('si.status', ['pending', 'on_progress'])
            ->groupBy('si.item_id', 'sio.warehouse_id');

        $query->leftJoinSub($onOrder, 'oo', function($j){
            $j->on('oo.item_id', '=', 'm.item_id')->on('oo.warehouse_id', '=', 'm.warehouse_id');
        });

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
        if (!empty($typeId) && $typeId !== 'all') {
            $query->where('i.item_type_id', $typeId);
        }
        // Status filter
        if ($status === 'reorder') {
            $query->whereRaw('COALESCE(inv.quantity,0) < m.rop_qty');
        } elseif ($status === 'ok') {
            $query->whereRaw('COALESCE(inv.quantity,0) >= m.rop_qty');
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
            $age = (int)($r->days_since_first ?? 0);
            $bucket = $age < 30 ? 30 : ($age < 60 ? 60 : 90);
            $total = $bucket === 30 ? (float)($r->total_out_30 ?? 0) : ($bucket === 60 ? (float)($r->total_out_60 ?? 0) : (float)($r->total_out_90 ?? 0));
            $avgDaily = $bucket > 0 ? ($total / $bucket) : 0.0;
            $avgSource = $bucket.'d';
            return [
                'sku' => $r->sku,
                'nama_barang' => $r->nama_barang,
                'per_koli' => (int)($r->per_koli ?? 0),
                'warehouse' => $r->warehouse_name,
                'lead_time_po' => (int)$r->lead_time_po,
                'lead_time_safety_stock' => (int)$r->lead_time_safety_stock,
                'avg_daily_out' => $avgDaily,
                'total_out_qty' => $total,
                'total_days' => $bucket,
                'avg_source' => $avgSource,
                'on_order_qty' => (float)($r->on_order_qty ?? 0),
                'safety_stock_qty' => (float)$r->safety_stock_qty,
                'rop_qty' => (float)$r->rop_qty,
                'onhand_qty' => (float)($r->onhand_qty ?? 0),
                'rop_koli' => (float)$r->rop_koli,
                'onhand_koli' => (float)($r->onhand_koli ?? 0),
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

    public function statusCounts(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $typeId = $request->input('item_type_id');

        $q = ItemReorderMetric::query()
            ->from('item_reorder_metrics as m')
            ->join('items as i', 'm.item_id', '=', 'i.id')
            ->leftJoin('inventories as inv', function($j){ $j->on('inv.item_id', '=', 'm.item_id')->on('inv.warehouse_id', '=', 'm.warehouse_id'); })
            ->when(!empty($warehouseId) && $warehouseId !== 'all', fn($qq)=>$qq->where('m.warehouse_id', $warehouseId))
            ->when(!empty($categoryId) && $categoryId !== 'all', fn($qq)=>$qq->where('i.item_category_id', $categoryId))
            ->when(!empty($typeId) && $typeId !== 'all', fn($qq)=>$qq->where('i.item_type_id', $typeId));

        $total = (clone $q)->count();
        $reorder = (clone $q)->whereRaw('COALESCE(inv.quantity,0) < m.rop_qty')->count();
        $ok = $total - $reorder;

        return response()->json([
            'total' => $total,
            'reorder' => $reorder,
            'ok' => $ok,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $categoryId = $request->input('category_id');
        $typeId = $request->input('item_type_id');
        $status = $request->input('status');
        $search = trim((string)$request->input('search', ''));

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
                'w.name as warehouse_name',
                DB::raw('COALESCE(s.total_out_30,0) as total_out_30'),
                DB::raw('COALESCE(s.total_out_60,0) as total_out_60'),
                DB::raw('COALESCE(s.total_out_90,0) as total_out_90'),
                DB::raw('COALESCE(s.days_since_first,0) as days_since_first'),
            ]);

        // Build 30/60/90 totals for export
        $today = now()->startOfDay();
        $start30 = $today->copy()->subDays(30 - 1)->toDateString();
        $start60 = $today->copy()->subDays(60 - 1)->toDateString();
        $start90 = $today->copy()->subDays(90 - 1)->toDateString();
        $sub = DB::table('stock_movements as sm')
            ->select([
                'sm.item_id',
                'sm.warehouse_id',
                DB::raw("MIN(DATE(sm.date)) as first_life_date"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start30}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_30"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start60}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_60"),
                DB::raw("SUM(CASE WHEN DATE(sm.date) >= '{$start90}' AND ((sm.type IN ('stock_out','transfer_out')) OR ((sm.type IS NULL OR sm.type='') AND sm.quantity < 0)) THEN ABS(sm.quantity) ELSE 0 END) as total_out_90"),
                DB::raw("CASE WHEN MIN(DATE(sm.date)) IS NULL THEN 0 ELSE DATEDIFF(CURDATE(), MIN(DATE(sm.date))) + 1 END as days_since_first"),
            ])
            ->groupBy('sm.item_id', 'sm.warehouse_id');
        $query->leftJoinSub($sub, 's', function($j){
            $j->on('s.item_id', '=', 'm.item_id')->on('s.warehouse_id', '=', 'm.warehouse_id');
        });

        if (!empty($warehouseId) && $warehouseId !== 'all') {
            $query->where('m.warehouse_id', $warehouseId);
        }
        if (!empty($categoryId) && $categoryId !== 'all') {
            $query->where('i.item_category_id', $categoryId);
        }
        if (!empty($typeId) && $typeId !== 'all') {
            $query->where('i.item_type_id', $typeId);
        }
        if ($status === 'reorder') {
            $query->whereRaw('COALESCE(inv.quantity,0) < m.rop_qty');
        } elseif ($status === 'ok') {
            $query->whereRaw('COALESCE(inv.quantity,0) >= m.rop_qty');
        }
        if ($search !== '') {
            $query->where(function($q) use ($search){
                $q->where('i.sku', 'like', "%{$search}%")
                  ->orWhere('i.nama_barang', 'like', "%{$search}%")
                  ->orWhere('w.name', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderBy('i.nama_barang')->get();

        $dataRows = [];
        foreach ($rows as $r) {
            $age = (int)($r->days_since_first ?? 0);
            $bucket = $age < 30 ? 30 : ($age < 60 ? 60 : 90);
            $usedTotal = $bucket === 30 ? (float)($r->total_out_30 ?? 0) : ($bucket === 60 ? (float)($r->total_out_60 ?? 0) : (float)($r->total_out_90 ?? 0));
            $avgDaily = $bucket > 0 ? ($usedTotal / $bucket) : 0;
            $gapQty = ((float)$r->rop_qty) - ((float)($r->onhand_qty ?? 0));
            $statusTxt = $gapQty > 0 ? 'Reorder' : 'Aman';
            $dataRows[] = [
                $r->sku,
                $r->nama_barang,
                $r->warehouse_name,
                number_format($avgDaily, 2, ',', '.'),
                number_format($usedTotal, 2, ',', '.'),
                (int)$bucket,
                (int)$r->lead_time_po,
                (int)$r->lead_time_safety_stock,
                number_format((float)$r->safety_stock_qty, 2, ',', '.'),
                number_format((float)$r->rop_qty, 2, ',', '.'),
                number_format((float)($r->onhand_qty ?? 0), 2, ',', '.'),
                number_format((float)$r->rop_koli, 2, ',', '.'),
                number_format((float)($r->onhand_koli ?? 0), 2, ',', '.'),
                number_format($gapQty, 2, ',', '.'),
                $statusTxt,
            ];
        }

        $headings = [
            'SKU','Nama Barang','Gudang','Avg OUT / hari','Total OUT (Qty)','Hari Perhitungan',
            'Lead Time PO (hari)','Lead Time Safety (hari)','Safety Stock','ROP (Qty)','On-hand (Qty)',
            'ROP (Koli)','On-hand (Koli)','Selisih (Qty)','Status'
        ];

        $fileName = 'reorder_point_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new ReorderPointExport($dataRows, $headings), $fileName);
    }

    // Recalculate ROP metrics for all known item-warehouse pairs
    public function recalc(Request $request, ReorderPointService $service)
    {
        try {
            // Build union of pairs from movements, inventories, and existing metrics
            $pairs = \Illuminate\Support\Facades\DB::query()
                ->fromSub(function($q){
                    $q->from('stock_movements as sm')->select('sm.item_id','sm.warehouse_id')->distinct();
                }, 't1')
                ->union(\Illuminate\Support\Facades\DB::table('inventories as inv')->select('inv.item_id','inv.warehouse_id')->distinct())
                ->union(\Illuminate\Support\Facades\DB::table('item_reorder_metrics as m')->select('m.item_id','m.warehouse_id')->distinct())
                ->get();

            $count = 0;
            foreach ($pairs as $p) {
                if (empty($p->item_id) || empty($p->warehouse_id)) continue;
                try { $service->compute((int)$p->item_id, (int)$p->warehouse_id); $count++; } catch (\Throwable $e) { /* skip individual errors */ }
            }

            return response()->json(['success' => true, 'processed' => $count, 'message' => 'Recalc ROP selesai.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal recalculation: '.$e->getMessage()], 500);
        }
    }
}
