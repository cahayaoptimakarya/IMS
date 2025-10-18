<?php

namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanPengadaanController extends Controller
{
    public function index(Request $request)
    {
        // Date range default last 30 days
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $dateRange = $request->input('date_range');
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) === 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                } catch (\Exception $e) {
                    // ignore invalid date
                }
            }
        }

        // Base SIO query filter
        $baseSio = DB::table('stock_in_orders as sio')->whereBetween('sio.date', [$startDate->toDateString(), $endDate->toDateString()]);

        // Enforce user warehouse or use filter
        $warehouseFilter = $request->input('warehouse_filter');
        if (auth()->user() && auth()->user()->warehouse_id) {
            $baseSio->where('sio.warehouse_id', auth()->user()->warehouse_id);
        } elseif (!empty($warehouseFilter)) {
            $baseSio->where('sio.warehouse_id', $warehouseFilter);
        }

        // Optional status/type filters
        if ($request->filled('status_filter') && $request->status_filter !== 'semua') {
            $baseSio->where('sio.status', $request->status_filter);
        }
        if ($request->filled('type_filter') && $request->type_filter !== 'semua') {
            $baseSio->where('sio.type', $request->type_filter);
        }

        // Cloneable base for aggregations
        $sioIdsQuery = (clone $baseSio)->select('sio.id');

        // Status counts and total orders
        $statusCounts = (clone $baseSio)
            ->select('sio.status', DB::raw('count(*) as cnt'))
            ->groupBy('sio.status')
            ->pluck('cnt', 'status');
        $totalOrders = (clone $baseSio)->count();

        // Totals from SIO items
        $totals = DB::table('stock_in_order_items as si')
            ->joinSub($sioIdsQuery, 's', function ($j) {
                $j->on('si.stock_in_order_id', '=', 's.id');
            })
            ->select(
                DB::raw('COALESCE(SUM(si.quantity),0) as ordered_qty'),
                DB::raw('COALESCE(SUM(si.koli),0) as ordered_koli'),
                DB::raw('COALESCE(SUM(si.remaining_quantity),0) as remaining_qty'),
                DB::raw('COALESCE(SUM(si.remaining_koli),0) as remaining_koli')
            )
            ->first();

        // Distributions (approved/completed) within date range
        $distTotals = DB::table('stock_in_order_item_distributions as d')
            ->join('stock_in_order_items as si', 'd.stock_in_order_item_id', '=', 'si.id')
            ->joinSub($sioIdsQuery, 's', function ($j) {
                $j->on('si.stock_in_order_id', '=', 's.id');
            })
            ->whereBetween('d.date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select(
                DB::raw('COALESCE(SUM(d.quantity),0) as distributed_qty'),
                DB::raw('COALESCE(SUM(d.koli),0) as distributed_koli')
            )
            ->first();

        // Goods receipts accepted totals for pengadaan
        $receiptTotals = DB::table('goods_receipt_details as grd')
            ->join('goods_receipts as gr', 'grd.goods_receipt_id', '=', 'gr.id')
            ->join('shipments as sh', 'gr.shipment_id', '=', 'sh.id')
            ->joinSub($sioIdsQuery, 's', function ($j) {
                $j->on('sh.reference_id', '=', 's.id');
            })
            ->where('sh.reference_type', 'stock in order')
            ->where('gr.type', 'pengadaan')
            ->whereBetween('gr.receipt_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select(
                DB::raw('COALESCE(SUM(grd.accepted_quantity),0) as accepted_qty'),
                DB::raw('COALESCE(SUM(grd.accepted_koli),0) as accepted_koli')
            )
            ->first();

        $orderedQty = (float) ($totals->ordered_qty ?? 0);
        $remainingQty = (float) ($totals->remaining_qty ?? 0);
        $completedQty = max(0.0, $orderedQty - $remainingQty);
        $progressPct = $orderedQty > 0 ? round(($completedQty / $orderedQty) * 100, 2) : 0.0;

        $stats = [
            'total_orders' => (int) $totalOrders,
            'status_counts' => [
                'requested' => (int) ($statusCounts['requested'] ?? 0),
                'on_shipping' => (int) ($statusCounts['on_shipping'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'rejected' => (int) ($statusCounts['rejected'] ?? 0),
            ],
            'ordered_qty' => $orderedQty,
            'ordered_koli' => (float) ($totals->ordered_koli ?? 0),
            'remaining_qty' => $remainingQty,
            'remaining_koli' => (float) ($totals->remaining_koli ?? 0),
            'distributed_qty' => (float) ($distTotals->distributed_qty ?? 0),
            'distributed_koli' => (float) ($distTotals->distributed_koli ?? 0),
            'accepted_qty' => (float) ($receiptTotals->accepted_qty ?? 0),
            'accepted_koli' => (float) ($receiptTotals->accepted_koli ?? 0),
            'progress_pct' => $progressPct,
        ];

        if ($request->ajax()) {
            // Provide JSON for AJAX filter updates
            return response()->json([
                'success' => true,
                'stats' => $stats,
            ]);
        }

        $warehouses = auth()->user() && auth()->user()->warehouse_id
            ? Warehouse::where('id', auth()->user()->warehouse_id)->get(['id','name'])
            : Warehouse::orderBy('name')->get(['id','name']);

        return view('admin.laporan.pengadaan', compact('stats', 'warehouses', 'dateRange'));
    }

    public function data(Request $request)
    {
        $searchValue = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 0);

        // Filters
        $dateRange = $request->input('date_range');
        $statusFilter = $request->input('status_filter');
        $typeFilter = $request->input('type_filter');
        $warehouseFilter = $request->input('warehouse_filter');

        $query = DB::table('stock_in_orders as sio')
            ->leftJoin('warehouses as w', 'sio.warehouse_id', '=', 'w.id')
            ->leftJoin('stock_in_order_items as si', 'si.stock_in_order_id', '=', 'sio.id')
            ->select(
                'sio.id', 'sio.code', 'sio.date', 'sio.type', 'sio.status', 'w.name as warehouse_name',
                DB::raw('COUNT(si.id) as items_count'),
                DB::raw('COALESCE(SUM(si.quantity),0) as ordered_qty'),
                DB::raw('COALESCE(SUM(si.remaining_quantity),0) as remaining_qty'),
                DB::raw('COALESCE(SUM(si.koli),0) as ordered_koli'),
                DB::raw('COALESCE(SUM(si.remaining_koli),0) as remaining_koli')
            )
            ->groupBy('sio.id', 'sio.code', 'sio.date', 'sio.type', 'sio.status', 'w.name');

        // Date range
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) === 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                    $query->whereBetween('sio.date', [$startDate->toDateString(), $endDate->toDateString()]);
                } catch (\Exception $e) {
                    // ignore
                }
            }
        }

        // Warehouse restriction
        if (auth()->user() && auth()->user()->warehouse_id) {
            $query->where('sio.warehouse_id', auth()->user()->warehouse_id);
        } elseif (!empty($warehouseFilter)) {
            $query->where('sio.warehouse_id', $warehouseFilter);
        }

        if ($statusFilter && $statusFilter !== 'semua') {
            $query->where('sio.status', $statusFilter);
        }
        if ($typeFilter && $typeFilter !== 'semua') {
            $query->where('sio.type', $typeFilter);
        }

        $totalRecords = (clone $query)->count();

        // Search
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('sio.code', 'LIKE', "%{$searchValue}%")
                  ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sio.status', 'LIKE', "%{$searchValue}%")
                  ->orWhere('sio.type', 'LIKE', "%{$searchValue}%");
            });
        }

        // Ordering map
        $columns = [
            0 => 'sio.code',
            1 => 'sio.date',
            2 => 'sio.type',
            3 => 'w.name',
            4 => 'items_count',
            5 => 'ordered_qty',
            6 => 'remaining_qty',
            7 => 'sio.status',
        ];
        $orderByColumnIndex = (int) $request->input('order.0.column', 1);
        $orderByColumnName = $columns[$orderByColumnIndex] ?? 'sio.date';
        $orderDirection = $request->input('order.0.dir', 'desc');

        $data = $query
            ->orderBy($orderByColumnName, $orderDirection)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($row) {
                $ordered = (float) $row->ordered_qty;
                $remaining = (float) $row->remaining_qty;
                $completed = max(0.0, $ordered - $remaining);
                $progress = $ordered > 0 ? round(($completed / $ordered) * 100, 2) : 0.0;
                return [
                    'id' => $row->id,
                    'code' => $row->code,
                    'date' => $row->date,
                    'type' => $row->type,
                    'warehouse_name' => $row->warehouse_name,
                    'items_count' => (int) $row->items_count,
                    'ordered_qty' => $ordered,
                    'remaining_qty' => $remaining,
                    'ordered_koli' => (float) $row->ordered_koli,
                    'remaining_koli' => (float) $row->remaining_koli,
                    'progress' => $progress,
                    'status' => $row->status,
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }
}
