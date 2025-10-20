<?php

namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\TransferRequest;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LaporanTransferGudangController extends Controller
{
    public function index(Request $request)
    {
        // Date filter default last 30 days
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $dateRange = $request->input('date_range');
        $statusFilter = $request->input('status_filter', 'semua');
        $fromWarehouseFilter = $request->input('from_warehouse_filter');
        $toWarehouseFilter = $request->input('to_warehouse_filter');
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) === 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                } catch (\Exception $e) {}
            }
        }

        $baseQuery = TransferRequest::query()->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);

        // If user is tied to a specific warehouse, show only related transfers (from or to)
        if (auth()->user() && auth()->user()->warehouse_id) {
            $wid = auth()->user()->warehouse_id;
            $baseQuery->where(function($q) use ($wid) {
                $q->where('from_warehouse_id', $wid)->orWhere('to_warehouse_id', $wid);
            });
        }

        // Filters
        if (!empty($statusFilter) && $statusFilter !== 'semua') {
            $baseQuery->where('status', $statusFilter);
        }
        if (!empty($fromWarehouseFilter)) {
            $baseQuery->where('from_warehouse_id', $fromWarehouseFilter);
        }
        if (!empty($toWarehouseFilter)) {
            $baseQuery->where('to_warehouse_id', $toWarehouseFilter);
        }

        // --- Additional aggregations for transfer metrics ---
        $totalTransfers = (clone $baseQuery)->count();
        $totalCompleted = (clone $baseQuery)->where('status', 'completed')->count();
        $successRate = $totalTransfers ? round(($totalCompleted / $totalTransfers) * 100, 2) : 0;

        // Average fulfillment time in days (request date -> completed_at)
        $avgFulfillmentDays = (clone $baseQuery)
            ->whereNotNull('completed_at')
            ->where('status', 'completed')
            ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(SECOND, date, completed_at)) / 86400, 0) as avg_days')
            ->value('avg_days');

        // Top origin-destination pairs
        $topOriginDestination = (clone $baseQuery)
            ->select('from_warehouse_id', 'to_warehouse_id', DB::raw('count(*) as transfer_count'))
            ->groupBy('from_warehouse_id', 'to_warehouse_id')
            ->orderByDesc('transfer_count')
            ->limit(5)
            ->get();

        // Average items per transfer (approx) — compute by loading counts per transfer
        $avgItemsPerTransfer = (clone $baseQuery)->withCount('items')->get()->avg('items_count') ?: 0;

        // Aging for pending: average days since request for pending transfers
        $pendingTransfers = (clone $baseQuery)->where('status', 'pending')->get();
        $agingPending = 0;
        if ($pendingTransfers->count() > 0) {
            $agingPending = round($pendingTransfers->map(function($t){ return Carbon::now()->diffInDays(Carbon::parse($t->date)); })->avg(), 2);
        }

        // Stats
        $stats = [
            'total_transfers'   => (clone $baseQuery)->count(),
            // Align with migration: no 'shipped' status; use 'on_progress'
            'total_on_progress' => (clone $baseQuery)->where('status', 'on_progress')->count(),
            'total_completed'   => (clone $baseQuery)->where('status', 'completed')->count(),
            // Pending card aggregates 'pending' + 'approved'
            'total_pending'     => (clone $baseQuery)->whereIn('status', ['pending', 'approved'])->count(),
            'total_rejected'    => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        // Trend (count per date)
        $trendResult = (clone $baseQuery)
            ->select(DB::raw('date as transfer_date'), DB::raw('count(*) as total'))
            ->groupBy('transfer_date')
            ->orderBy('transfer_date', 'asc')
            ->get();
        $trendData = [
            'dates' => $trendResult->pluck('transfer_date')->map(fn($d) => Carbon::parse($d)->format('d M')),
            'totals' => $trendResult->pluck('total'),
        ];

        // Pie by status
        $pieResult = (clone $baseQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
        $pieChartData = [
            'labels' => $pieResult->pluck('status')->map(fn($s) => ucwords(str_replace('_', ' ', $s))),
            'series' => $pieResult->pluck('count'),
        ];

        $warehouses = auth()->user() && auth()->user()->warehouse_id
            ? Warehouse::where('id', auth()->user()->warehouse_id)->get()
            : Warehouse::all();

        return view('admin.laporan.laporan-transfer-gudang', compact(
            'stats', 'trendData', 'pieChartData', 'warehouses', 'dateRange', 'statusFilter', 'fromWarehouseFilter', 'toWarehouseFilter',
            'successRate', 'avgFulfillmentDays', 'topOriginDestination', 'avgItemsPerTransfer', 'agingPending'
        ));
    }

    public function transferGudangData(Request $request)
    {
        $searchValue = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 0);

        $dateRange = $request->input('date_range');
        $statusFilter = $request->input('status_filter', 'semua');
        $fromWarehouseFilter = $request->input('from_warehouse_filter');
        $toWarehouseFilter = $request->input('to_warehouse_filter');

        $query = TransferRequest::query()->from('transfer_requests as tr')->select([
            'tr.id',
            'tr.date',
            'tr.code',
            'tr.status',
            'fw.name as from_warehouse_name',
            'tw.name as to_warehouse_name',
            'u.name as requester_name',
            DB::raw('(SELECT COUNT(*) FROM transfer_request_items tri WHERE tri.transfer_request_id = tr.id) as items_count'),
        ])
        ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
        ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
        ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id');

        // User warehouse restriction
        if (auth()->user() && auth()->user()->warehouse_id) {
            $wid = auth()->user()->warehouse_id;
            $query->where(function($q) use ($wid) {
                $q->where('tr.from_warehouse_id', $wid)->orWhere('tr.to_warehouse_id', $wid);
            });
        }

        // Date range filter
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) === 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();
                    $query->whereBetween('tr.date', [$startDate->toDateString(), $endDate->toDateString()]);
                } catch (\Exception $e) {}
            }
        }

        // Other filters
        if (!empty($statusFilter) && $statusFilter !== 'semua') {
            if ($statusFilter === 'pending_approved') {
                $query->whereIn('tr.status', ['pending', 'approved']);
            } else {
                $query->where('tr.status', $statusFilter);
            }
        }
        if (!empty($fromWarehouseFilter)) {
            $query->where('tr.from_warehouse_id', $fromWarehouseFilter);
        }
        if (!empty($toWarehouseFilter)) {
            $query->where('tr.to_warehouse_id', $toWarehouseFilter);
        }

        $totalRecords = (clone $query)->count();

        // Global search
        if (!empty($searchValue)) {
            $query->where(function($q) use ($searchValue) {
                $like = "%{$searchValue}%";
                $q->where('tr.code', 'LIKE', $like)
                  ->orWhere('fw.name', 'LIKE', $like)
                  ->orWhere('tw.name', 'LIKE', $like)
                  ->orWhere('tr.status', 'LIKE', $like)
                  ->orWhere('u.name', 'LIKE', $like);
            });
        }

        $totalFiltered = (clone $query)->count();

        // Ordering
        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columnsMap = [
            0 => 'tr.date',
            1 => 'tr.code',
            2 => 'fw.name',
            3 => 'tw.name',
            4 => 'tr.status',
            5 => DB::raw('items_count'),
            6 => 'u.name',
        ];
        $orderBy = $columnsMap[$orderColumnIndex] ?? 'tr.date';

        // Fetch data page
        $rows = $query
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function ($row) {
                $row->date = Carbon::parse($row->date)->format('d M Y');
                return $row;
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $rows,
        ]);
    }
}
