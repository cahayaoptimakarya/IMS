<?php

namespace App\Http\Controllers\Admin\StokMasuk;

use App\Http\Controllers\Controller;
use App\Models\TransferRequest;
use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use App\Models\GoodsReceipt;
use App\Models\TransferRequestItem;
use App\Models\UserActivity;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\Inventory;
use App\Models\User;
use App\Notifications\TransferRequestCreated;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestTransferController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $fromWarehouseFilter = $request->input('from_warehouse_id');
            $toWarehouseFilter = $request->input('to_warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            // We force order by date to avoid column index mismatch with dynamic columns in view
            $orderByColumnName = 'tr.date';
            $orderDirection = $request->input('order.0.dir', 'desc');

            $query = TransferRequest::query()
                ->from('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id')
                ->addSelect(DB::raw("(
                    SELECT GROUP_CONCAT(CONCAT(items.sku, ' (', FORMAT(tri.quantity,0), ')') SEPARATOR ', ')
                    FROM transfer_request_items tri
                    JOIN items ON items.id = tri.item_id
                    WHERE tri.transfer_request_id = tr.id
                ) AS items_list"));

            // Apply user's warehouse filter if not null
            if (auth()->user()->warehouse_id !== null) {
                $query->where(function ($q) {
                    $q->where('tr.to_warehouse_id', auth()->user()->warehouse_id);
                });
            }

            $totalRecords = $query->count();

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.status', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
                $query->where('tr.from_warehouse_id', $fromWarehouseFilter);
            }

            if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
                $query->where('tr.to_warehouse_id', $toWarehouseFilter);
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('tr.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                $query->whereDate('tr.date', $dateFilter);
            }

            $totalFiltered = $query->count();

            $data = $query->addSelect(
                'tr.id',
                'tr.code',
                'tr.date',
                'tr.status',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name',
                'u.name as requester_name'
            )
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $warehouses = Warehouse::all();
        return view('admin.stok_masuk.request_transfer.index', compact('warehouses'));
    }

    public function modalInTransitItems(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            $query = DB::table('shipment_item_details as sid')
                ->join('shipments as s', 's.id', '=', 'sid.shipment_id')
                ->leftJoin('goods_receipt_details as grd', 'grd.shipment_item_id', '=', 'sid.id')
                ->leftJoin('goods_receipts as gr', 'gr.id', '=', 'grd.goods_receipt_id')
                ->leftJoin('items as i', 'i.id', '=', 'sid.item_id')
                ->join('transfer_request_items as tri', function($j){
                    $j->on('tri.id', '=', 'sid.reference_id')
                      ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM);
                })
                ->join('transfer_requests as tr', 'tr.id', '=', 'tri.transfer_request_id')
                ->leftJoin('warehouses as fw', 'fw.id', '=', 's.from_warehouse_id')
                ->leftJoin('warehouses as tw', 'tw.id', '=', 's.to_warehouse_id')
                ->select(
                    'sid.id as shipment_item_id',
                    'i.sku as item_sku',
                    'i.nama_barang as item_name',
                    DB::raw('SUM(COALESCE(grd.received_quantity,0)) as total_received_qty'),
                    DB::raw('SUM(COALESCE(grd.received_koli,0)) as total_received_koli'),
                    DB::raw('MAX(sid.quantity_shipped) as quantity_shipped'),
                    DB::raw('MAX(sid.koli_shipped) as koli_shipped')
                )
                ->groupBy('sid.id','i.sku','i.nama_barang');

            // Only shipments that are in transit and from transfer request
            $query->where('s.status', 'dalam perjalanan')
                  ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST);
            // Exclude rejected transfer requests
            $query->where('tr.status', '!=', 'rejected');

            // Scope to user's warehouse (destination)
            if (!is_null(auth()->user()->warehouse_id)) {
                $query->where('s.to_warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($searchValue)) {
                $query->having(function($q) use ($searchValue){
                    $q->where('s.code', 'LIKE', "%{$searchValue}%")
                      ->orWhere('tr.code', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%")
                      ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                      ->orWhere('tw.name', 'LIKE', "%{$searchValue}%");
                });
            }

            // Only items still in transit: shipped > received
            $rows = $query->get()->map(function($r){
                $receivedQty = (float) $r->total_received_qty;
                $shippedQty = (float) $r->quantity_shipped;
                $qtyOutstanding = max(0, $shippedQty - $receivedQty);
                $receivedKoli = (float) $r->total_received_koli;
                $shippedKoli = (float) $r->koli_shipped;
                $koliOutstanding = max(0, $shippedKoli - $receivedKoli);
                return [
                    'item' => ['sku' => $r->item_sku, 'nama_barang' => $r->item_name],
                    'qty_in_transit' => $qtyOutstanding,
                    'koli_in_transit' => $koliOutstanding,
                ];
            })->filter(function($row){ return ($row['qty_in_transit'] ?? 0) > 0 || ($row['koli_in_transit'] ?? 0) > 0; });

            $total = $rows->count();
            $paged = $rows->slice($start, $length)->values();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $paged,
            ]);
        }

        return response()->json(['data' => []]);
    }

    public function modalPackingItems(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            $query = DB::table('shipment_item_details as sid')
                ->join('shipments as s', 's.id', '=', 'sid.shipment_id')
                ->join('items as i', 'i.id', '=', 'sid.item_id')
                ->join('transfer_request_items as tri', function($j){
                    $j->on('tri.id', '=', 'sid.reference_id')
                      ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM);
                })
                ->join('transfer_requests as tr', 'tr.id', '=', 'tri.transfer_request_id')
                ->select(
                    'sid.id as shipment_item_id',
                    'i.sku as item_sku',
                    'i.nama_barang as item_name',
                    DB::raw('MAX(sid.quantity_shipped) as quantity_shipped'),
                    DB::raw('MAX(sid.koli_shipped) as koli_shipped')
                )
                ->where('s.status', 'packing')
                ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                ->groupBy('sid.id','i.sku','i.nama_barang');

            // Exclude rejected transfer requests
            $query->where('tr.status', '!=', 'rejected');

            if (!is_null(auth()->user()->warehouse_id)) {
                $query->where('s.to_warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue){
                    $q->where('i.sku', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%");
                });
            }

            $rows = $query->offset($start)->limit($length)->get()->map(function($r){
                return [
                    'item' => [ 'sku' => $r->item_sku, 'nama_barang' => $r->item_name ],
                    'qty_packing' => (float) $r->quantity_shipped,
                    'koli_packing' => (float) $r->koli_shipped,
                ];
            });

            $totalRecords = (clone $query)->count();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalRecords,
                'data' => $rows,
            ]);
        }

        return response()->json(['data' => []]);
    }
    public function modalNotShippedItems(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            // Ambil dari transfer_request_items berdasarkan sisa yang belum dikirim
            $query = DB::table('transfer_request_items as tri')
                ->join('transfer_requests as tr', 'tr.id', '=', 'tri.transfer_request_id')
                ->join('items as i', 'i.id', '=', 'tri.item_id')
                ->select(
                    'i.sku as item_sku',
                    'i.nama_barang as item_name',
                    DB::raw('COALESCE(tri.ship_remaining_quantity,0) as remaining_qty'),
                    DB::raw('COALESCE(tri.ship_remaining_koli,0) as remaining_koli')
                )
                ->where(function($q){
                    $q->where('tri.ship_remaining_quantity', '>', 0)
                      ->orWhere('tri.ship_remaining_koli', '>', 0);
                });

            // Exclude rejected transfer requests
            $query->where('tr.status', '!=', 'rejected');

            if (!is_null(auth()->user()->warehouse_id)) {
                $query->where('tr.to_warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($searchValue)) {
                $query->where(function($q) use ($searchValue){
                    $q->where('i.sku', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%");
                });
            }

            $allRows = $query->get()->map(function($r){
                return [
                    'item' => [ 'sku' => $r->item_sku, 'nama_barang' => $r->item_name ],
                    'remaining_qty' => (float) $r->remaining_qty,
                    'remaining_koli' => (float) $r->remaining_koli,
                ];
            })->values();

            $total = $allRows->count();
            $paged = $allRows->slice($start, $length)->values();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $paged,
            ]);
        }

        return response()->json(['data' => []]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::all();
        $code = $this->generateCode();
        return view('admin.stok_masuk.request_transfer.create', compact('warehouses', 'items', 'code'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:transfer_requests,code',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transferRequest = TransferRequest::create([
                'code' => $request->code,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'date' => $request->date,
                'description' => $request->description,
                'status' => 'pending',
                'requested_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transferRequest->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'koli' => $item['koli'] ?? 0,
                    'ship_remaining_quantity' => $item['quantity'],
                    'ship_remaining_koli' => $item['koli'] ?? 0,
                    'description' => $item['description'],
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'Request Transfer',
                'description' => 'Membuat Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            // Notify users at source warehouse (gudang diminta) about new transfer request
            try {
                $recipients = User::where('warehouse_id', $transferRequest->from_warehouse_id)->get();
                foreach ($recipients as $user) {
                    $user->notify(new TransferRequestCreated($transferRequest));
                }
            } catch (\Throwable $e) {
                // swallow notification errors to not block main flow
            }

            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil dibuat.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat request transfer: ' . $e->getMessage())->withInput();
        }
    }

    public function calculateItemValues(Request $request)
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'quantity' => 'nullable|numeric|min:0',
            'koli' => 'nullable|numeric|min:0',
        ]);

        $item = Item::find($request->item_id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $koliPerUnit = $item->koli ?? 1; // Use 'koli' column, default to 1 if null

        $quantity = $request->input('quantity');
        $koli = $request->input('koli');

        if (isset($quantity)) {
            // Calculate koli from quantity
            $calculatedKoli = $koliPerUnit > 0 ? $quantity / $koliPerUnit : 0;
            return response()->json([
                'quantity' => (float) $quantity,
                'koli' => (float) $calculatedKoli,
            ]);
        } elseif (isset($koli)) {
            // Calculate quantity from koli
            $calculatedQuantity = $koli * $koliPerUnit;
            return response()->json([
                'quantity' => (float) $calculatedQuantity,
                'koli' => (float) $koli,
            ]);
        }

        return response()->json(['error' => 'Invalid input. Either quantity or koli must be provided.'], 400);
    }

    public function show(TransferRequest $transferRequest)
    {
        $transferRequest->load(['fromWarehouse', 'toWarehouse', 'items', 'items.item', 'requester']);

        // Fetch shipments linked to this Transfer Request via shipment_item_details -> transfer_request_items
        $trItemIds = $transferRequest->items->pluck('id');
        $shipments = Shipment::query()
            ->where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            ->whereIn('id', function($q) use ($transferRequest) {
                $q->select('sid.shipment_id')
                  ->from('shipment_item_details as sid')
                  ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
                  ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                  ->where('tri.transfer_request_id', $transferRequest->id);
            })
            ->with(['itemDetails' => function($q) use ($trItemIds) {
                $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                  ->whereIn('reference_id', $trItemIds);
            }, 'itemDetails.item'])
            ->orderBy('shipping_date')
            ->get();

        return view('admin.stok_masuk.request_transfer.show', compact('transferRequest', 'shipments'));
    }

    public function edit(TransferRequest $transferRequest)
    {
        if($transferRequest->status != 'pending'){
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Hanya request transfer dengan status pending yang dapat diubah.');
        }
        $warehouses = Warehouse::all();
        $items = Item::all();
        $transferRequest->load(['items', 'items.item']);
        return view('admin.stok_masuk.request_transfer.edit', compact('transferRequest', 'warehouses', 'items'));
    }

    public function update(Request $request, TransferRequest $transferRequest)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transferRequest->update([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'date' => $request->date,
                'description' => $request->description,
                'status' => $request->status ?? $transferRequest->status,
            ]);

            $transferRequest->items()->delete();

            foreach ($request->items as $item) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transferRequest->id,
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'koli' => $item['koli'] ?? 0,
                    'ship_remaining_quantity' => $item['quantity'],
                    'ship_remaining_koli' => $item['koli'] ?? 0,
                    'description' => $item['description'],
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Request Transfer',
                'description' => 'Mengubah Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui request transfer: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy(Request $request, TransferRequest $transferRequest)
    {
        if ($transferRequest->status != 'pending') {
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Hanya request transfer dengan status pending yang dapat dihapus.');
        }

        try {
            DB::beginTransaction();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Request Transfer',
                'description' => 'Menghapus Request Transfer pada kode : ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            $transferRequest->items()->delete();
            $transferRequest->delete();
            DB::commit();
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('success', 'Request transfer berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.stok-masuk.request-transfer.index')->with('error', 'Gagal menghapus request transfer.');
        }
    }

    public function getItemsByWarehouse($warehouse_id)
    {
        $items = Inventory::with('item')
            ->where('warehouse_id', $warehouse_id)
            // ->where('quantity', '>', 0)
            ->get();

        return response()->json($items);
    }

    public function getStatusCounts(Request $request)
    {
        $dateFilter = $request->input('date');
        $fromWarehouseFilter = $request->input('from_warehouse_id');
        $toWarehouseFilter = $request->input('to_warehouse_id');
        $statusFilter = $request->input('status');

        $query = TransferRequest::query();

        if (auth()->user()->warehouse_id !== null) {
            $query->where('to_warehouse_id', auth()->user()->warehouse_id);
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $query->whereDate('date', $dateFilter);
        }

        if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
            $query->where('from_warehouse_id', $fromWarehouseFilter);
        }

        if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
            $query->where('to_warehouse_id', $toWarehouseFilter);
        }
        
        if ($statusFilter && $statusFilter !== 'semua') {
            $query->where('status', $statusFilter);
        }

        $statusCounts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        // Align with migration: statuses are pending, approved, rejected, on_progress, completed
        $result = [
            'requested'    => $statusCounts->get('pending', 0),
            'approved'     => $statusCounts->get('approved', 0),
            'on_progress'  => $statusCounts->get('on_progress', 0),
            'completed'    => $statusCounts->get('completed', 0),
            'rejected'     => $statusCounts->get('rejected', 0),
        ];

        return response()->json($result);
    }

    private function generateCode(): string
    {
        $prefix = 'TR';
        $date = now()->format('Ymd');
        $latest = TransferRequest::where('code', 'LIKE', "{$prefix}-{$date}-%")->latest('id')->first();
        $seq = 1;
        if ($latest && preg_match('/^' . preg_quote($prefix, '/') . '-' . $date . '-(\d{4})$/', $latest->code, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }
}
