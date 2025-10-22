<?php

namespace App\Http\Controllers\Admin\StokKeluar;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use App\Models\StockMovement;
use App\Models\TransferRequestItem;
use App\Models\UserActivity;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Employee;
use Auth;
use Illuminate\Http\Request;
use App\Models\TransferRequest;
use App\Models\Warehouse;
use App\Notifications\TransferRequestApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PermintaanBarangController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $toWarehouseFilter = $request->input('to_warehouse_id');
            $fromWarehouseFilter = $request->input('from_warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 'tr.code',
                1 => 'tr.date',
                2 => 'from_warehouse_name',
                3 => 'to_warehouse_name',
                4 => 'items_list',
                5 => 'tr.status',
                6 => 'requester_name',
                7 => 'tr.id',
            ];
            // Default sort by date desc to show latest first
            $orderByColumnIndex = $request->input('order.0.column', 1);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'desc');

            $totalRecordsQuery = TransferRequest::query();
            if (auth()->user()->warehouse_id) {
                $totalRecordsQuery->where('from_warehouse_id', auth()->user()->warehouse_id);
            }
            $totalRecords = $totalRecordsQuery->count();

            $query = TransferRequest::query()
                ->from('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('users as u', 'tr.requested_by', '=', 'u.id')
                ->leftJoin('transfer_request_items as tri', 'tr.id', '=', 'tri.transfer_request_id')
                ->leftJoin('items', 'tri.item_id', '=', 'items.id');

            if (auth()->user()->warehouse_id) {
                $query->where('tr.from_warehouse_id', auth()->user()->warehouse_id);
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.status', 'LIKE', "%{$searchValue}%")
                        ->orWhere('items.sku', 'LIKE', "%{$searchValue}%");
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

            $totalFiltered = $query->distinct('tr.id')->count();

            $data = $query->select(
                'tr.id',
                'tr.code',
                'tr.date',
                'tr.status',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name',
                'u.name as requester_name',
                DB::raw("GROUP_CONCAT(items.sku, ' (', FORMAT(tri.quantity,0), ')' SEPARATOR ', ') as items_list")
            )
                ->groupBy('tr.id', 'tr.code', 'tr.date', 'tr.status', 'fw.name', 'tw.name', 'u.name')
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            foreach ($data as $row) {
                $row->action = '<a href="' . route('admin.stok-keluar.permintaan-barang.show', $row->id) . '" class="btn btn-info btn-sm">Detail</a>';
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $warehouses = Warehouse::all();
        $employees = Employee::all();
        $vehicles = Vehicle::all();
        return view('admin.stok-keluar.permintaan-barang.index', compact('warehouses', 'employees', 'vehicles'));
    }

    public function getStatusCounts(Request $request)
    {
        $toWarehouseFilter = $request->input('to_warehouse_id');
        $fromWarehouseFilter = $request->input('from_warehouse_id');
        $dateFilter = $request->input('date');

        $baseQuery = TransferRequest::query();

        if (auth()->user()->warehouse_id) {
            $baseQuery->where('from_warehouse_id', auth()->user()->warehouse_id);
        }

        if ($fromWarehouseFilter && $fromWarehouseFilter !== 'semua') {
            $baseQuery->where('from_warehouse_id', $fromWarehouseFilter);
        }

        if ($toWarehouseFilter && $toWarehouseFilter !== 'semua') {
            $baseQuery->where('to_warehouse_id', $toWarehouseFilter);
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $baseQuery->whereDate('date', $dateFilter);
        }

        $statusCounts = [
            'requested' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'on_progress' => (clone $baseQuery)->where('status', 'on_progress')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        return response()->json($statusCounts);
    }

    public function show(TransferRequest $transferRequest)
    {
        // Load dasar TR + items
        $transferRequest->load([
            'fromWarehouse',
            'toWarehouse',
            'requester',
            'items.item',
        ]);

        // Alur data shipment: ShipmentItemDetail -> TransferRequestItem -> TransferRequest
        $transferRequestItemIds = $transferRequest->items->pluck('id')->all();

        $shipments = Shipment::query()
            ->whereHas('itemDetails', function ($q) use ($transferRequestItemIds) {
                $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                  ->whereIn('reference_id', $transferRequestItemIds);
            })
            ->with(['itemDetails' => function ($q) use ($transferRequestItemIds) {
                $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                  ->whereIn('reference_id', $transferRequestItemIds)
                  ->with('item');
            }])
            ->orderByDesc('shipping_date')
            ->get();

        // Hitung total agar tersedia di view
        $totalQuantity = $transferRequest->items->sum('quantity');
        $totalKoli = $transferRequest->items->sum('koli');

        return view(
            'admin.stok-keluar.permintaan-barang.show',
            compact('transferRequest', 'totalQuantity', 'totalKoli', 'shipments')
        );
    }

    public function updateStatus(Request $request, TransferRequest $transferRequest)
    {
        $request->validate([
            'status' => 'required|in:approved',
        ]);

        $newStatus = $request->status;

        if ($newStatus === 'approved' && $transferRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan dengan status pending yang bisa disetujui.'], 422);
        }

        try {
            DB::beginTransaction();

            $transferRequest->approved_by = Auth::id();
            $transferRequest->approved_at = now();
            $transferRequest->status = $newStatus;
            $transferRequest->save();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'Permintaan Barang',
                'description' => 'Menyetujui Permintaan Barang menjadi '.$newStatus.'dengan kode ' . $transferRequest->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            // Notify requesting warehouse users that the request was approved
            try {
                $recipients = User::where('warehouse_id', $transferRequest->to_warehouse_id)->get();
                foreach ($recipients as $user) {
                    $user->notify(new TransferRequestApproved($transferRequest));
                }
            } catch (\Throwable $e) {
                // Do not block the response on notification failures
            }

            return response()->json(['success' => true, 'message' => 'Status berhasil diperbarui.']);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Gagal memperbarui status: ' . $e->getMessage()], 500);
        }
    }

    public function getItemsToShip(TransferRequest $transferRequest)
    {
        $items = $transferRequest->items()
            ->where('ship_remaining_quantity', '>', 0)
            ->with('item:id,nama_barang,koli,sku')
            ->get();

        return response()->json($items);
    }

    // Page: lists of items not yet shipped across transfer requests
    public function unshippedIndex(Request $request)
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $userWarehouseId = auth()->user()->warehouse_id ?? null;

        // Build server-rendered list so print includes all rows neatly
        $query = DB::table('transfer_request_items as tri')
            ->join('transfer_requests as tr', 'tri.transfer_request_id', '=', 'tr.id')
            ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
            ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
            ->leftJoin('items as i', 'tri.item_id', '=', 'i.id')
            ->where(function($q){
                $q->where('tri.ship_remaining_quantity', '>', 0)
                  ->orWhere('tri.ship_remaining_koli', '>', 0);
            });

        if ($userWarehouseId) {
            $query->where('tr.to_warehouse_id', $userWarehouseId);
        } else {
            $fromWh = $request->query('from_warehouse_id');
            $toWh = $request->query('to_warehouse_id');
            if (!empty($fromWh)) { $query->where('tr.from_warehouse_id', $fromWh); }
            if (!empty($toWh)) { $query->where('tr.to_warehouse_id', $toWh); }
        }

        $pendingItems = $query->select(
                'tr.id as tr_id',
                'tr.code',
                'tr.date',
                'i.sku',
                'i.nama_barang',
                'tri.ship_remaining_quantity',
                'tri.ship_remaining_koli',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name'
            )
            ->orderByDesc('tr.date')
            ->orderByDesc('tr.id')
            ->get();

        $selectedFrom = $request->query('from_warehouse_id');
        $selectedTo = $request->query('to_warehouse_id');

        return view('admin.stok-keluar.permintaan-barang.unshipped', compact('warehouses', 'userWarehouseId', 'pendingItems', 'selectedFrom', 'selectedTo'));
    }

    // Data provider for unshipped items table (server-side DataTables)
    public function unshippedData(Request $request)
    {
        $searchValue = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 0);

        $query = DB::table('transfer_request_items as tri')
            ->join('transfer_requests as tr', 'tri.transfer_request_id', '=', 'tr.id')
            ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
            ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
            ->leftJoin('items as i', 'tri.item_id', '=', 'i.id')
            ->where(function($q){
                $q->where('tri.ship_remaining_quantity', '>', 0)
                  ->orWhere('tri.ship_remaining_koli', '>', 0);
            });

        if (auth()->user() && auth()->user()->warehouse_id) {
            // When user tied to a warehouse, show requests incoming to user's warehouse
            $query->where('tr.to_warehouse_id', auth()->user()->warehouse_id);
        } else {
            // Optional filters for origin and destination warehouses
            $fromWh = $request->input('from_warehouse_id');
            $toWh = $request->input('to_warehouse_id');
            if (!empty($fromWh)) {
                $query->where('tr.from_warehouse_id', $fromWh);
            }
            if (!empty($toWh)) {
                $query->where('tr.to_warehouse_id', $toWh);
            }
        }

        $totalRecords = (clone $query)->count();

        if (!empty($searchValue)) {
            $like = "%{$searchValue}%";
            $query->where(function($q) use ($like){
                $q->where('tr.code', 'LIKE', $like)
                  ->orWhere('i.sku', 'LIKE', $like)
                  ->orWhere('i.nama_barang', 'LIKE', $like)
                  ->orWhere('fw.name', 'LIKE', $like)
                  ->orWhere('tw.name', 'LIKE', $like);
            });
        }

        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columns = [
            0 => 'tr.date',
            1 => 'tr.code',
            2 => 'i.sku',
            3 => 'i.nama_barang',
            4 => 'tri.ship_remaining_quantity',
            5 => 'tri.ship_remaining_koli',
            6 => 'fw.name',
            7 => 'tw.name',
        ];
        $orderBy = $columns[$orderColumnIndex] ?? 'tr.date';

        $rows = $query->select(
                'tr.id as tr_id',
                'tr.date',
                'tr.code',
                'i.sku',
                'i.nama_barang',
                'tri.ship_remaining_quantity',
                'tri.ship_remaining_koli',
                'fw.name as from_warehouse_name',
                'tw.name as to_warehouse_name'
            )
            ->orderBy($orderBy, $orderDir)
            ->offset($start)
            ->limit($length)
            ->get()
            ->map(function($r){
                $r->date = \Carbon\Carbon::parse($r->date)->format('Y-m-d');
                return $r;
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $rows,
        ]);
    }

    public function getPackingShipments(TransferRequest $transferRequest)
    {
        $shipments = Shipment::query()
            ->where('status', 'packing')
            ->where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            ->where('from_warehouse_id', $transferRequest->from_warehouse_id)
            ->where('to_warehouse_id', $transferRequest->to_warehouse_id)
            ->select(
                'id',
                'code',
                'shipping_date',
                'vehicle_type',
                'license_plate',
                'driver_name',
                'driver_contact',
                'description',
                'status'
            )
            ->orderByDesc('shipping_date')
            ->get();

        return response()->json($shipments);
    }

    public function createShipment(Request $request, TransferRequest $transferRequest)
    {
        $validated = $request->validate([
            'shipping_date' => 'required|date',
            'vehicle_type' => 'required|string|max:255',
            'license_plate' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'driver_contact' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'shipment_id' => 'nullable|exists:shipments,id',
            'items' => 'required|array',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.description' => 'nullable|string|max:255',
        ]);

        if (!in_array($transferRequest->status, ['approved', 'on_progress'])) {
            return response()->json(['success' => false, 'message' => 'Hanya permintaan yang sudah disetujui atau sedang dalam proses yang bisa dikirim.'], 422);
        }

        $itemsToShip = array_filter($validated['items'], function ($item) {
            return ($item['quantity'] > 0 || $item['koli'] > 0);
        });

        if (empty($itemsToShip)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada item yang dipilih untuk dikirim.'], 422);
        }

        try {
            DB::transaction(function () use ($validated, $transferRequest, $itemsToShip, $request) {
                // 1. Validasi Stok dan Kuantitas
                foreach ($itemsToShip as $id => $itemData) {
                    $transferRequestItem = TransferRequestItem::findOrFail($id);
                    $quantityToShip = $itemData['quantity'] ?? 0;
                    $koliToShip = $itemData['koli'] ?? 0;

                    if ($quantityToShip > $transferRequestItem->ship_remaining_quantity || $koliToShip > $transferRequestItem->ship_remaining_koli) {
                        throw ValidationException::withMessages(['items' => 'Kuantitas kirim untuk item ' . $transferRequestItem->item->name . ' melebihi sisa yang diminta.']);
                    }

                    $inventory = Inventory::where('warehouse_id', $transferRequest->from_warehouse_id)
                        ->where('item_id', $transferRequestItem->item_id)
                        ->first();

                    if (!$inventory || $inventory->quantity < $quantityToShip) {
                        throw ValidationException::withMessages(['items' => 'Stok untuk item ' . $transferRequestItem->item->name . ' tidak mencukupi di gudang asal.']);
                    }
                }

                // 2. Ambil atau buat Shipment Header
                if (!empty($validated['shipment_id'])) {
                    $shipment = Shipment::where('id', $validated['shipment_id'])
                        ->where('status', 'packing')
                        ->where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                        ->where('from_warehouse_id', $transferRequest->from_warehouse_id)
                        ->where('to_warehouse_id', $transferRequest->to_warehouse_id)
                        ->firstOrFail();
                    // Perbarui info umum jika diisi
                    $shipment->shipping_date = $validated['shipping_date'];
                    $shipment->vehicle_type = $validated['vehicle_type'];
                    $shipment->license_plate = $validated['license_plate'];
                    $shipment->driver_name = $validated['driver_name'];
                    $shipment->driver_contact = $validated['driver_contact'];
                    $shipment->description = $validated['description'];
                    $shipment->shipped_by = auth()->id();
                    $shipment->save();
                } else {
                    $shipment = Shipment::create([
                        'code' => 'SHIP-' . date('Ymd') . '-' . str_pad(Shipment::count() + 1, 4, '0', STR_PAD_LEFT),
                        'reference_type' => Shipment::REFERENCE_TYPE_TRANSFER_REQUEST,
                        'from_warehouse_id' => $transferRequest->from_warehouse_id,
                        'to_warehouse_id' => $transferRequest->to_warehouse_id,
                        'shipping_date' => $validated['shipping_date'],
                        'vehicle_type' => $validated['vehicle_type'],
                        'license_plate' => $validated['license_plate'],
                        'driver_name' => $validated['driver_name'],
                        'driver_contact' => $validated['driver_contact'],
                        'description' => $validated['description'],
                        'status' => 'packing',
                        'shipped_by' => auth()->id(),
                    ]);
                }

                // 3. Proses setiap item
                foreach ($itemsToShip as $id => $itemData) {
                    $transferRequestItem = TransferRequestItem::findOrFail($id);
                    $quantityToShip = $itemData['quantity'] ?? 0;
                    $koliToShip = $itemData['koli'] ?? 0;

                    // Buat Shipment Item Detail
                    $shipmentDetail = ShipmentItemDetail::create([
                        'shipment_id' => $shipment->id,
                        'reference_id' => $transferRequestItem->id,
                        'reference_type' => 'transfer_request_items',
                        'item_id' => $transferRequestItem->item_id,
                        'quantity_shipped' => $quantityToShip,
                        'koli_shipped' => $koliToShip,
                        'description' => $itemData['description'] ?? null,
                    ]);

                    // Kurangi stok dari gudang asal
                    $fromInventory = Inventory::where('warehouse_id', $transferRequest->from_warehouse_id)
                        ->where('item_id', $transferRequestItem->item_id)
                        ->first();

                    $stockBefore = $fromInventory->quantity;
                    $fromInventory->quantity -= $quantityToShip;
                    $fromInventory->koli -= $koliToShip;
                    $fromInventory->save();

                    // Buat Catatan Pergerakan Stok
                    StockMovement::create([
                        'item_id' => $transferRequestItem->item_id,
                        'date' => $validated['shipping_date'],
                        'warehouse_id' => $transferRequest->from_warehouse_id,
                        'quantity' => -$quantityToShip,
                        'stock_before' => $stockBefore,
                        'description' => 'Pengiriman permintaan transfer ' . $transferRequest->code,
                        'stock_after' => $fromInventory->quantity,
                        'koli' => -$koliToShip,
                        'type' => 'stock_out',
                        'user_id' => auth()->id(),
                        // Mengacu langsung ke baris shipment detail sebagai referensi gerak stok
                        'reference_id' => $shipmentDetail->id,
                        'reference_type' => 'shipment_item_details',
                    ]);

                    // Update sisa kuantitas di item permintaan transfer
                    $transferRequestItem->ship_remaining_quantity -= $quantityToShip;
                    $transferRequestItem->ship_remaining_koli -= $koliToShip;
                    $transferRequestItem->save();
                }

                // 4. Update Status Transfer Request to 'on_progress'
                $transferRequest->status = 'on_progress';
                $transferRequest->shipped_at = now();
                $transferRequest->shipped_by = auth()->id();
                $transferRequest->save();

                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'shipped',
                    'menu' => 'Permintaan Barang',
                    'description' => 'Membuat pengiriman parsial/penuh untuk kode ' . $transferRequest->code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Data pengiriman berhasil disimpan.']);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat data pengiriman: ' . $e->getMessage()], 500);
        }
    }
}
