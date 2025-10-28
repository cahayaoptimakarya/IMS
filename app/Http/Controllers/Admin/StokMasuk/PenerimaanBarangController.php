<?php

namespace App\Http\Controllers\Admin\StokMasuk;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Inventory;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\StockInOrder;
use App\Models\StockInOrderItem;
use App\Models\TransferRequest;
use App\Models\ShipmentItemDetail;
use App\Models\GoodsReceiptDetail;
use App\Models\UserActivity;
use App\Models\Warehouse;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\TransferRequestItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\MenuPermissionResolver;
use App\Notifications\TransferRequestReceived;

class PenerimaanBarangController extends Controller
{
    protected $permissionResolver;

    public function __construct(MenuPermissionResolver $permissionResolver)
    {
        $this->permissionResolver = $permissionResolver;
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $statusFilter = $request->input('status');
            $warehouseFilter = $request->input('warehouse_id');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 'gr.code',
                1 => 'gr.receipt_date',
                2 => 'gr.type',
                3 => 'shipment_code',
                4 => 'warehouse_name',
                5 => 'gr.status',
                6 => 'receiver_name',
                7 => 'gr.id',
            ];

            $query = GoodsReceipt::query()
                ->from('goods_receipts as gr')
                ->leftJoin('shipments as s', 'gr.shipment_id', '=', 's.id')
                ->leftJoin('warehouses as w', 'gr.warehouse_id', '=', 'w.id')
                ->leftJoin('users as u', 'gr.received_by', '=', 'u.id');

            $totalRecords = (clone $query)->count();

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('gr.status', $statusFilter);
            }

            // Apply user's warehouse filter if present, otherwise use request filter
            if ($forcedWarehouseId !== null) {
                $query->where('gr.warehouse_id', $forcedWarehouseId);
            } elseif ($warehouseFilter && $warehouseFilter !== 'semua') {
                $query->where('gr.warehouse_id', $warehouseFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (Str::contains($dateFilter, ' to ')) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('gr.receipt_date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('gr.receipt_date', $dateFilter);
                }
            }

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('gr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('gr.status', 'LIKE', "%{$searchValue}%")
                        ->orWhere('gr.type', 'LIKE', "%{$searchValue}%")
                        ->orWhere('s.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%");
                });
            }

            $totalFiltered = (clone $query)->count();

            // Default sort by receipt_date desc (latest first)
            $orderColumnIndex = (int) $request->input('order.0.column', 1);
            $orderColumnName = $columns[$orderColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'desc');

            $data = $query->select(
                'gr.id',
                'gr.code',
                'gr.receipt_date',
                'gr.type',
                'gr.status',
                's.code as shipment_code',
                'w.name as warehouse_name',
                'u.name as receiver_name'
            )
                ->orderBy($orderColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'code' => $row->code,
                        'receipt_date' => $row->receipt_date ? Carbon::parse($row->receipt_date)->format('Y-m-d') : null,
                        'type' => $row->type,
                        'status' => $row->status,
                        'shipment_code' => $row->shipment_code,
                        'warehouse_name' => $row->warehouse_name,
                        'receiver_name' => $row->receiver_name,
                    ];
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $data,
            ]);
        }

        $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
        $warehouses = Warehouse::orderBy('name')->get();

        $base = GoodsReceipt::query();
        if ($forcedWarehouseId !== null) {
            $base->where('warehouse_id', $forcedWarehouseId);
        }
        $statusCounts = [
            GoodsReceipt::STATUS_DRAFT => (clone $base)->where('status', GoodsReceipt::STATUS_DRAFT)->count(),
            GoodsReceipt::STATUS_PARTIAL => (clone $base)->where('status', GoodsReceipt::STATUS_PARTIAL)->count(),
            GoodsReceipt::STATUS_COMPLETED => (clone $base)->where('status', GoodsReceipt::STATUS_COMPLETED)->count(),
        ];

        return view('admin.stok_masuk.penerimaan_barang.index', compact('warehouses', 'statusCounts'));
    }

    public function getStatusCounts(Request $request)
    {
        $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
        $warehouseFilter = $request->input('warehouse_id');
        $dateFilter = $request->input('date');

        $baseQuery = GoodsReceipt::query();

        // Apply user's warehouse filter if present, otherwise use request filter
        if ($forcedWarehouseId !== null) {
            $baseQuery->where('warehouse_id', $forcedWarehouseId);
        } elseif ($warehouseFilter && $warehouseFilter !== 'semua') {
            $baseQuery->where('warehouse_id', $warehouseFilter);
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            if (Str::contains($dateFilter, ' to ')) {
                [$startDate, $endDate] = explode(' to ', $dateFilter);
                $baseQuery->whereBetween('receipt_date', [$startDate, $endDate]);
            } else {
                $baseQuery->whereDate('receipt_date', $dateFilter);
            }
        }

        return response()->json([
            GoodsReceipt::STATUS_DRAFT => (clone $baseQuery)->where('status', GoodsReceipt::STATUS_DRAFT)->count(),
            GoodsReceipt::STATUS_PARTIAL => (clone $baseQuery)->where('status', GoodsReceipt::STATUS_PARTIAL)->count(),
            GoodsReceipt::STATUS_COMPLETED => (clone $baseQuery)->where('status', GoodsReceipt::STATUS_COMPLETED)->count(),
        ]);
    }

    private function generateGoodsReceiptCode(): string
    {
        $prefix = 'GR';
        $date = now()->format('Ymd');
        $latestReceipt = GoodsReceipt::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

        $sequence = $latestReceipt ? ((int) substr($latestReceipt->code, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    private function generateShipmentCode(): string
    {
        $prefix = 'SJP';
        $date = now()->format('Ymd');
        $latest = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_STOCK_IN_ORDER)
            ->orderByDesc('id')
            ->first();

        $lastSeq = 0;
        if ($latest && preg_match('/(\d{4})$/', $latest->code, $m)) {
            $lastSeq = (int) $m[1];
        }

        $sequence = $lastSeq + 1;
        $code = sprintf('%s-%s-%04d', $prefix, $date, $sequence);

        while (Shipment::where('code', $code)->exists()) {
            $sequence++;
            $code = sprintf('%s-%s-%04d', $prefix, $date, $sequence);
        }

        return $code;
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $newCode = $this->generateGoodsReceiptCode();
        $employees = Employee::where('active', true)->orderBy('name')->get();
        $vehicles = Vehicle::where('active', true)->orderBy('license_plate')->get();

        return view('admin.stok_masuk.penerimaan_barang.create', compact('warehouses', 'newCode', 'employees', 'vehicles'));
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        $rules = [
            'code' => ['required', 'string', 'max:50', 'unique:goods_receipts,code'],
            'type' => ['required', Rule::in(['transfer','pengadaan'])],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_id' => ['required', 'exists:items,id'],
            'details.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.ordered_koli' => ['nullable', 'numeric', 'min:0'],
            'details.*.received_quantity' => ['required', 'integer', 'min:0'],
            'details.*.received_koli' => ['nullable', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
        ];

        if ($type === 'transfer') {
            $rules['shipment_id'] = ['required', 'exists:shipments,id'];
            $rules['details.*.shipment_item_id'] = ['required', 'exists:shipment_item_details,id'];
        } elseif ($type === 'pengadaan') {
            $rules['stock_in_order_id'] = ['required', 'exists:stock_in_orders,id'];
            $rules['manual_shipment.shipping_date'] = ['required','date'];
            $rules['manual_shipment.vehicle_type'] = ['required','string','max:255'];
            $rules['manual_shipment.license_plate'] = ['required','string','max:255'];
            // optional driver fields
            $rules['manual_shipment.driver_name'] = ['nullable','string','max:255'];
            $rules['manual_shipment.driver_contact'] = ['nullable','string','max:255'];
            $rules['manual_shipment.description'] = ['nullable','string'];
            // allow carrying stock_in_order_item reference id if provided by client
            $rules['details.*.order_item_id'] = ['nullable','exists:stock_in_order_items,id'];
        }

        $validated = $request->validate($rules);

        // Enforce user's warehouse if assigned (mirror store())
        $userWarehouseId = auth()->user()->warehouse_id ?? null;
        if ($userWarehouseId !== null) {
            $validated['warehouse_id'] = $userWarehouseId;
        }

        // Validate details against context
        if ($validated['type'] === 'transfer') {
            // Validate that shipment items belong to the selected shipment and quantities do not exceed remaining
            foreach ($validated['details'] as $index => $detail) {
                $shipmentItem = ShipmentItemDetail::find($detail['shipment_item_id']);
                if (!$shipmentItem || (int)$shipmentItem->shipment_id !== (int)$validated['shipment_id']) {
                    return back()->withErrors(["details.{$index}.shipment_item_id" => 'Item pengiriman tidak valid untuk shipment ini.'])->withInput();
                }

                $shippedQty = (int) round((float) ($shipmentItem->quantity_shipped ?? 0), 0);
                $shippedKoli = (float) ($shipmentItem->koli_shipped ?? 0);
                $alreadyReceivedQty = (int) round((float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($validated) {
                        $q->where('shipment_id', $validated['shipment_id']);
                    })->where('shipment_item_id', $shipmentItem->id)->sum('received_quantity'), 0);
                $alreadyReceivedKoli = (float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($validated) {
                        $q->where('shipment_id', $validated['shipment_id']);
                    })->where('shipment_item_id', $shipmentItem->id)->sum('received_koli');

                $remainingQty = (int) max(0, $shippedQty - $alreadyReceivedQty);
                $remainingKoli = max(0, $shippedKoli - $alreadyReceivedKoli);

                if (($detail['received_quantity'] ?? 0) > $remainingQty) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_quantity" => "Item {$itemName}: Jumlah diterima tidak boleh melebihi sisa dikirim ({$remainingQty})."
                    ])->withInput();
                }
                if (($detail['received_koli'] ?? 0) > $remainingKoli) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_koli" => "Item {$itemName}: Koli diterima tidak boleh melebihi sisa dikirim ({$remainingKoli})."
                    ])->withInput();
                }
            }
        } else { // pengadaan
            foreach ($validated['details'] as $index => $detail) {
                $orderItemId = $detail['order_item_id'] ?? null;
                if (!$orderItemId) {
                    $orderItemId = StockInOrderItem::where('stock_in_order_id', $validated['stock_in_order_id'])
                        ->where('item_id', $detail['item_id'])
                        ->value('id');
                }
                $orderItem = $orderItemId ? StockInOrderItem::find($orderItemId) : null;
                if (!$orderItem) {
                    return back()->withErrors(["details.{$index}.item_id" => 'Item tidak valid untuk dokumen pengadaan ini.'])->withInput();
                }
                $remainingQty = (int) ($orderItem->remaining_quantity ?? 0);
                $remainingKoli = (float) ($orderItem->remaining_koli ?? 0);
                if (($detail['received_quantity'] ?? 0) > $remainingQty) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_quantity" => "Item {$itemName}: Jumlah diterima tidak boleh melebihi sisa pesanan ({$remainingQty})."
                    ])->withInput();
                }
                if (($detail['received_koli'] ?? 0) > $remainingKoli) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_koli" => "Item {$itemName}: Koli diterima tidak boleh melebihi sisa pesanan ({$remainingKoli})."
                    ])->withInput();
                }
            }
        }


        // Enforce user's warehouse if assigned
        $userWarehouseId = auth()->user()->warehouse_id ?? null;
        if ($userWarehouseId !== null) {
            $validated['warehouse_id'] = $userWarehouseId;
        }

        DB::beginTransaction();
        try {
            // Enforce user's warehouse if assigned
            $userWarehouseId = auth()->user()->warehouse_id ?? null;
            if ($userWarehouseId !== null) {
                $validated['warehouse_id'] = $userWarehouseId;
            }

            // If type pengadaan, create Shipment first and build shipment items from received quantities
            if ($validated['type'] === 'pengadaan') {
                $shipment = Shipment::create([
                    'code' => $this->generateShipmentCode(),
                    'reference_type' => Shipment::REFERENCE_TYPE_STOCK_IN_ORDER,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => (string) $validated['warehouse_id'],
                    'shipping_date' => $validated['manual_shipment']['shipping_date'],
                    'vehicle_type' => $validated['manual_shipment']['vehicle_type'],
                    'license_plate' => $validated['manual_shipment']['license_plate'],
                    'driver_name' => $validated['manual_shipment']['driver_name'] ?? null,
                    'driver_contact' => $validated['manual_shipment']['driver_contact'] ?? null,
                    'description' => $validated['manual_shipment']['description'] ?? null,
                    'shipped_by' => Auth::id(),
                ]);

                // For each detail, create a shipment_item_detail using received qty/koli
                foreach ($validated['details'] as $idx => $detail) {
                    $qty = (int) ($detail['received_quantity'] ?? 0);
                    $koli = (float) ($detail['received_koli'] ?? 0);
                    if ($qty <= 0 && $koli <= 0) {
                        continue; // skip empty lines
                    }

                    // Resolve stock_in_order_item id either from input or lookup
                    $orderItemId = $detail['order_item_id'] ?? null;
                    if (!$orderItemId) {
                        $orderItemId = StockInOrderItem::where('stock_in_order_id', $validated['stock_in_order_id'])
                            ->where('item_id', $detail['item_id'])
                            ->value('id');
                    }

                    if (!$orderItemId) {
                        throw new \RuntimeException('Tidak dapat menemukan baris item pengadaan untuk item yang dipilih.');
                    }

                    $sid = ShipmentItemDetail::create([
                        'shipment_id' => $shipment->id,
                        'item_id' => $detail['item_id'],
                        'reference_id' => $orderItemId,
                        'reference_type' => ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM,
                        'quantity_shipped' => $qty,
                        'koli_shipped' => $koli,
                        'description' => $detail['notes'] ?? null,
                    ]);

                    // Put back the generated shipment_item_id so we can create GR details consistently below
                    $validated['details'][$idx]['shipment_item_id'] = $sid->id;
                }

                // assign shipment id back to validated for GR creation
                $validated['shipment_id'] = $shipment->id;
            }

            $goodsReceipt = GoodsReceipt::create([
                'code' => $validated['code'],
                'type' => $validated['type'],
                'shipment_id' => $validated['shipment_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'receipt_date' => $validated['receipt_date'],
                'status' => GoodsReceipt::STATUS_DRAFT,
                'description' => $validated['description'] ?? null,
                'received_by' => Auth::id(),
            ]);

            foreach ($validated['details'] as $detail) {
                $receivedQty = (int) round($detail['received_quantity'] ?? 0, 0);
                $receivedKoli = round((float) ($detail['received_koli'] ?? 0), 2);

                // Skip zero-lines to avoid NULL shipment_item_id
                if ($receivedQty <= 0 && $receivedKoli <= 0) {
                    continue;
                }

                if (empty($detail['shipment_item_id'])) {
                    throw new \RuntimeException('Gagal menyimpan detail: shipment_item_id tidak tersedia untuk item yang diterima.');
                }

                $goodsReceipt->details()->create([
                    'item_id' => $detail['item_id'],
                    'shipment_item_id' => $detail['shipment_item_id'],
                    'ordered_quantity' => (int) round($detail['ordered_quantity'] ?? 0, 0),
                    'ordered_koli' => $detail['ordered_koli'] ?? 0,
                    'received_quantity' => $receivedQty,
                    'received_koli' => $receivedKoli,
                    'accepted_quantity' => (int) $receivedQty, // Auto-set
                    'accepted_koli' => $receivedKoli, // Auto-set
                    'rejected_quantity' => 0, // Auto-set
                    'rejected_koli' => 0, // Auto-set
                    'notes' => $detail['notes'] ?? null,
                ]);

                // Do not update remaining here; it will be applied when GR is completed
            }


            // Build descriptive activity similar to PengadaanController
            $itemsCount = is_array($validated['details'] ?? null) ? count($validated['details']) : 0;
            $totalQty = 0; $totalKoli = 0.0;
            foreach (($validated['details'] ?? []) as $d) {
                $totalQty += (int) round($d['received_quantity'] ?? 0, 0);
                $totalKoli += round((float) ($d['received_koli'] ?? 0), 2);
            }
            $wh = Warehouse::find($validated['warehouse_id']);
            $whName = $wh->name ?? ($validated['warehouse_id'] ?? '-');
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'Penerimaan Barang',
                'description' => 'Menambahkan Penerimaan Barang: ' . $goodsReceipt->code . ' (Tipe: ' . ($validated['type'] ?? '-') . ', Gudang: ' . $whName . ', Baris: ' . $itemsCount . ', Qty: ' . (int) $totalQty . ', Koli: ' . (float) $totalKoli . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.stok-masuk.penerimaan-barang.index')->with('success', 'Penerimaan barang berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal menyimpan penerimaan barang: ' . $e->getMessage()])->withInput();
        }
    }

    public function getReferences(Request $request)
    {
        // Deprecated in new flow; kept for backward compatibility
        return response()->json([]);
    }

    public function getReferenceDetails(Request $request)
    {
        $shipmentId = $request->input('shipment_id');
        $type = $request->input('type'); // backward compat
        $id = $request->input('id');    // backward compat
        $details = [];

        if ($shipmentId) {
            $shipment = Shipment::with('itemDetails.item')->find($shipmentId);
            if ($shipment) {
                $excludeId = $request->input('exclude_gr_id');
                $details = $shipment->itemDetails->map(function ($d) use ($shipment, $excludeId) {
                    $alreadyQty = GoodsReceiptDetail::whereHas('goodsReceipt', function($q) use ($shipment, $excludeId){
                        $q->where('shipment_id', $shipment->id);
                        if ($excludeId) { $q->where('id', '!=', $excludeId); }
                    })->where('shipment_item_id', $d->id)->sum('received_quantity');
                    $alreadyKoli = GoodsReceiptDetail::whereHas('goodsReceipt', function($q) use ($shipment, $excludeId){
                        $q->where('shipment_id', $shipment->id);
                        if ($excludeId) { $q->where('id', '!=', $excludeId); }
                    })->where('shipment_item_id', $d->id)->sum('received_koli');
                    return [
                        'shipment_item_id' => $d->id,
                        'item_id' => $d->item_id,
                        'item_label' => ($d->item->sku ?? '-') . ' - ' . ($d->item->nama_barang ?? $d->item->name ?? '-'),
                        'remaining_quantity' => (int) round(max(0, (float)$d->quantity_shipped - (float)$alreadyQty), 0),
                        'remaining_koli' => max(0, (float)$d->koli_shipped - (float)$alreadyKoli),
                        'item_koli_ratio' => $d->item->koli ?? 1,
                    ];
                });
            }
            return response()->json($details);
        }

        if (!$type || !$id) {
            return response()->json(['error' => 'Invalid request.'], 400);
        }

        if ($type === GoodsReceipt::REFERENCE_TYPE_STOCK_IN_ORDER) {
            $order = StockInOrder::with('items.item')->find($id);
            if ($order) {
                $details = $order->items->map(function ($item) {
                    return [
                        'order_item_id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_label' => $item->item->sku . ' - ' . $item->item->nama_barang,
                        'remaining_quantity' => $item->remaining_quantity,
                        'remaining_koli' => $item->remaining_koli,
                        'item_koli_ratio' => $item->item->koli ?? 1,
                    ];
                });
            }
        } elseif ($type === GoodsReceipt::REFERENCE_TYPE_TRANSFER_REQUEST) {
            // Find the latest shipment for the given Transfer Request via shipment item details
            $shipment = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                ->whereHas('itemDetails', function($q) use ($id) {
                    $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                      ->whereIn('reference_id', DB::table('transfer_request_items')->where('transfer_request_id', $id)->pluck('id'));
                })
                ->latest('shipping_date')
                ->first();

            if ($shipment) {
                // Get details from the shipment, not the original transfer request
                $shipmentDetails = ShipmentItemDetail::where('shipment_id', $shipment->id)
                    ->with('item')
                    ->get();

                $details = $shipmentDetails->map(function ($detail) {
                    // Pass the SHIPPED quantity and koli to the form
                    return [
                        'item_id' => $detail->item_id,
                        'item_label' => ($detail->item->sku ?? '-') . ' - ' . ($detail->item->nama_barang ?? $detail->item->name ?? '-'),
                        'remaining_quantity' => $detail->quantity_shipped, // This is the shipped quantity
                        'remaining_koli' => $detail->koli_shipped,       // This is the shipped koli
                        'item_koli_ratio' => $detail->item->koli ?? 1,
                    ];
                });
            }
        }

        return response()->json($details);
    }

    public function getShipmentDetails(Request $request)
    {
        $shipmentId = $request->input('shipment_id');
        if ($shipmentId) {
            $shipment = Shipment::find($shipmentId);
        } else {
            // Backward compat path (deprecated)
            $type = $request->input('type');
            $id = $request->input('id');
            if ($type && $id && $type === GoodsReceipt::REFERENCE_TYPE_TRANSFER_REQUEST) {
                $shipment = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                    ->whereHas('itemDetails', function($q) use ($id){
                        $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                          ->whereIn('reference_id', DB::table('transfer_request_items')->where('transfer_request_id', $id)->pluck('id'));
                    })
                    ->latest('shipping_date')
                    ->first();
            }
        }

        if ($shipment) {
            return response()->json([
                'code' => $shipment->code,
                'shipping_date' => $shipment->shipping_date,
                'vehicle_type' => $shipment->vehicle_type,
                'license_plate' => $shipment->license_plate,
                'driver_name' => $shipment->driver_name,
                'driver_contact' => $shipment->driver_contact,
                'description' => $shipment->description,
            ]);
        }

        return response()->json(null);
    }

    // New: list shipments for a warehouse (destination)
    public function getShipments(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        if (!$warehouseId) return response()->json([]);

        $shipments = Shipment::query()
            ->where('to_warehouse_id', $warehouseId)
            // Exclude finished shipments
            ->where(function($q){
                $q->whereNull('shipments.status')
                  ->orWhere('shipments.status', '=', 'dalam perjalanan');
            })
            ->orderByDesc('shipments.shipping_date')
            ->get(['shipments.id','shipments.code','shipments.shipping_date','shipments.reference_type'])
            ->map(function($s){
                return [
                    'id' => $s->id,
                    'label' => $s->code.' - '.($s->shipping_date ? Carbon::parse($s->shipping_date)->format('d M Y') : '-') . ' ['.$s->reference_type.']',
                ];
            });

        return response()->json($shipments);
    }

    public function getNextShipmentCode()
    {
        return response()->json(['code' => $this->generateShipmentCode()]);
    }

    // List open Stock In Orders (not completed) for a warehouse
    public function getStockInOrders(Request $request)
    {
        $warehouseId = $request->input('warehouse_id');
        $forcedWarehouseId = auth()->user()->warehouse_id ?? null;
        $wid = $forcedWarehouseId ?: $warehouseId;

        if (!$wid) return response()->json([]);

        $orders = StockInOrder::query()
            ->where('warehouse_id', $wid)
            ->whereIn('status', ['requested', 'on_shipping'])
            ->orderByDesc('date')
            ->get(['id','code','date'])
            ->map(function($o){
                return [
                    'id' => $o->id,
                    'label' => $o->code . ' - ' . ($o->date ? \Carbon\Carbon::parse($o->date)->format('d M Y') : '-')
                ];
            });

        return response()->json($orders);
    }

    public function complete(Request $request, GoodsReceipt $goodsReceipt)
    {
        if (!$this->permissionResolver->userCan('approve')) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menyetujui penerimaan barang.'], 403);
        }

        if ($goodsReceipt->status === GoodsReceipt::STATUS_COMPLETED) {
            return response()->json(['success' => false, 'message' => 'Dokumen sudah berstatus completed.'], 422);
        }

        DB::beginTransaction();
        try {
            $goodsReceipt->load('details');

            // Derive reference from shipment (header may not hold reference_id in new schema)
            $referenceType = null;
            $referenceId = null;
            $linkedShipment = null;
            if ($goodsReceipt->shipment_id) {
                $linkedShipment = Shipment::find($goodsReceipt->shipment_id);
                if ($linkedShipment) {
                    $referenceType = $linkedShipment->reference_type;
                    // Derive reference id via shipment item details without changing business logic
                    if ($referenceType === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
                        $referenceId = DB::table('shipment_item_details as sid')
                            ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
                            ->where('sid.shipment_id', $linkedShipment->id)
                            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                            ->value('tri.transfer_request_id');
                    } elseif ($referenceType === Shipment::REFERENCE_TYPE_STOCK_IN_ORDER) {
                        $referenceId = DB::table('shipment_item_details as sid')
                            ->join('stock_in_order_items as sioi', 'sioi.id', '=', 'sid.reference_id')
                            ->where('sid.shipment_id', $linkedShipment->id)
                            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM)
                            ->value('sioi.stock_in_order_id');
                    }
                }
            }

            // 1) Adjust source document for SIO, and set parent statuses
            if ($referenceType === GoodsReceipt::REFERENCE_TYPE_STOCK_IN_ORDER) {
                // Derive all Stock In Orders referenced by this shipment (support multi-SIO in one shipment)
                $sioIdsFromShipment = collect();
                if ($linkedShipment) {
                    $sioIdsFromShipment = DB::table('shipment_item_details as sid')
                        ->join('stock_in_order_items as sioi', 'sioi.id', '=', 'sid.reference_id')
                        ->where('sid.shipment_id', $linkedShipment->id)
                        ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM)
                        ->pluck('sioi.stock_in_order_id')
                        ->unique();
                }
                foreach ($goodsReceipt->details as $d) {
                    // Prefer precise mapping through shipment_item_details -> reference_id
                    $sourceItem = null;
                    if (!empty($d->shipment_item_id)) {
                        $sid = ShipmentItemDetail::find($d->shipment_item_id);
                        if ($sid && $sid->reference_type === ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM) {
                            $sourceItem = StockInOrderItem::find($sid->reference_id);
                        }
                    }
                    // Fallback by stock_in_order_id + item_id if precise link unavailable
                    if (!$sourceItem) {
                        $query = StockInOrderItem::query()->where('item_id', $d->item_id);
                        if ($sioIdsFromShipment && $sioIdsFromShipment->count()) {
                            $query->whereIn('stock_in_order_id', $sioIdsFromShipment->all());
                        } elseif ($referenceId) {
                            $query->where('stock_in_order_id', $referenceId);
                        }
                        $sourceItem = $query->first();
                    }
                    if ($sourceItem) {
                        $newRemainingQty = max(0, ($sourceItem->remaining_quantity ?? 0) - ($d->accepted_quantity ?? $d->received_quantity ?? 0));
                        $newRemainingKoli = max(0, ($sourceItem->remaining_koli ?? 0) - ($d->accepted_koli ?? $d->received_koli ?? 0));
                        $sourceItem->remaining_quantity = $newRemainingQty;
                        $sourceItem->remaining_koli = $newRemainingKoli;

                        // Update item status: completed if fully received, on_progress otherwise (if any reduction happened)
                        $isFullyCompleted = ($newRemainingQty <= 0) && ($newRemainingKoli <= 0);
                        if ($isFullyCompleted) {
                            $sourceItem->status = 'completed';
                        } else {
                            // If there is any difference from initial request quantities, mark on_progress
                            $initialQty = (float) ($sourceItem->quantity ?? 0);
                            $initialKoli = (float) ($sourceItem->koli ?? 0);
                            $partiallyReceived = ($newRemainingQty < $initialQty) || ($newRemainingKoli < $initialKoli);
                            if ($partiallyReceived) {
                                $sourceItem->status = 'on_progress';
                            }
                        }

                        $sourceItem->save();
                    }
                }

                // Update related SIO headers status (support multiple)
                $sioHeaderIds = $sioIdsFromShipment && $sioIdsFromShipment->count() ? $sioIdsFromShipment : collect([$referenceId])->filter();
                foreach ($sioHeaderIds as $sioId) {
                    $stockInOrder = StockInOrder::find($sioId);
                    if ($stockInOrder) {
                        $totalRemainingQty = (float) $stockInOrder->items()->sum('remaining_quantity');
                        $receiptDate = $goodsReceipt->receipt_date ?: now()->toDateString();
                        if (empty($stockInOrder->shipping_at)) {
                            $stockInOrder->shipping_at = $receiptDate;
                        }
                        if ($totalRemainingQty <= 0) {
                            $stockInOrder->status = 'completed';
                            if (empty($stockInOrder->completed_at)) {
                                $stockInOrder->completed_at = $receiptDate;
                            }
                        } else {
                            $stockInOrder->status = 'on_shipping';
                        }
                        $stockInOrder->save();
                    }
                }
            } elseif ($referenceType === GoodsReceipt::REFERENCE_TYPE_TRANSFER_REQUEST) {
                // Handle multiple Transfer Requests linked via shipment item details
                $trIds = DB::table('shipment_item_details as sid')
                    ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
                    ->where('sid.shipment_id', $linkedShipment ? $linkedShipment->id : 0)
                    ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                    ->pluck('tri.transfer_request_id')->unique();

                // Update received counters on related TransferRequestItems based on accepted quantities in this GR
                foreach ($goodsReceipt->details as $d) {
                    if (!empty($d->shipment_item_id)) {
                        $sid = ShipmentItemDetail::find($d->shipment_item_id);
                        if ($sid && $sid->reference_type === ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM) {
                            $tri = TransferRequestItem::find($sid->reference_id);
                            if ($tri) {
                                $accQty = (int) round($d->accepted_quantity ?? $d->received_quantity ?? 0, 0);
                                $accKoli = round((float) ($d->accepted_koli ?? $d->received_koli ?? 0), 2);
                                $tri->received_quantity = (float) ($tri->received_quantity ?? 0) + $accQty;
                                $tri->received_koli = (float) ($tri->received_koli ?? 0) + $accKoli;
                                // Clamp to requested amounts
                                if ($tri->quantity !== null && (float)$tri->received_quantity > (float)$tri->quantity) {
                                    $tri->received_quantity = $tri->quantity;
                                }
                                if ($tri->koli !== null && (float)$tri->received_koli > (float)$tri->koli) {
                                    $tri->received_koli = $tri->koli;
                                }
                                $itemComplete = (((float) ($tri->received_quantity ?? 0)) >= (float) ($tri->quantity ?? 0))
                                    || (((float) ($tri->received_koli ?? 0)) >= (float) ($tri->koli ?? 0));
                                if ($itemComplete) {
                                    $tri->status = 'completed';
                                } elseif (($accQty > 0) || ($accKoli > 0)) {
                                    $tri->status = 'on_progress';
                                }
                                $tri->save();
                            }
                        }
                    }
                }
                foreach ($trIds as $trId) {
                    $transferRequest = TransferRequest::find($trId);
                    if ($transferRequest) {
                        $items = $transferRequest->items()->get(['quantity','koli','received_quantity','received_koli']);
                        $allCompleted = $items->every(function($it){
                            $qComplete = ((float) ($it->received_quantity ?? 0)) >= (float) ($it->quantity ?? 0);
                            $kComplete = ((float) ($it->received_koli ?? 0)) >= (float) ($it->koli ?? 0);
                            return $qComplete || $kComplete;
                        });
                        $anyReceived = $items->some(function($it){
                            return ((float) ($it->received_quantity ?? 0)) > 0 || ((float) ($it->received_koli ?? 0)) > 0;
                        });
                        if ($allCompleted) {
                            $receiptDate = $goodsReceipt->receipt_date ?: now()->toDateString();
                            if (empty($transferRequest->completed_at)) {
                                $transferRequest->completed_at = $receiptDate;
                            }
                            $transferRequest->status = 'completed';
                        } else {
                            // mark on_progress if any has been received
                            if ($anyReceived) {
                                $transferRequest->status = 'on_progress';
                            }
                        }
                        $transferRequest->save();
                    }
                }
            }

            // 2) Increase inventory for destination warehouse and record stock movements
            foreach ($goodsReceipt->details as $d) {
                $qtyIn = round((float) ($d->accepted_quantity ?? $d->received_quantity ?? 0), 2);
                $koliIn = round((float) ($d->accepted_koli ?? $d->received_koli ?? 0), 2);
                if ($qtyIn == 0 && $koliIn == 0) {
                    continue;
                }

                $inventory = Inventory::firstOrCreate(
                    [
                        'warehouse_id' => $goodsReceipt->warehouse_id,
                        'item_id' => $d->item_id,
                    ],
                    [
                        'quantity' => 0,
                        'koli' => 0,
                    ]
                );

                $stockBefore = $inventory->quantity;
                $inventory->quantity = round($inventory->quantity + $qtyIn, 2);
                $inventory->koli = round($inventory->koli + $koliIn, 2);
                $inventory->save();
                $stockAfter = $inventory->quantity;

                // Reference points to Goods Receipt Detail to unify reporting
                $movementReferenceType = 'goods_receipt_details';
                $movementReferenceId = $d->id;
                StockMovement::create([
                    'item_id' => $d->item_id,
                    'warehouse_id' => $goodsReceipt->warehouse_id,
                    'date' => $goodsReceipt->receipt_date,
                    'quantity' => $qtyIn,
                    'koli' => $koliIn,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'type' => 'stock_in',
                    'description' => 'Penerimaan barang ' . $goodsReceipt->code,
                    'user_id' => Auth::id(),
                    'reference_id' => $movementReferenceId,
                    'reference_type' => $movementReferenceType,
                ]);
            }

            $goodsReceipt->status = GoodsReceipt::STATUS_COMPLETED;
            $goodsReceipt->verified_by = Auth::id();
            $goodsReceipt->completed_at = now();
            $goodsReceipt->save();

            // Update related shipment status based on total received vs shipped
            if ($goodsReceipt->shipment_id) {
                $shipment = Shipment::find($goodsReceipt->shipment_id);
                if ($shipment) {
                    $totalShippedQty = round((float) ShipmentItemDetail::where('shipment_id', $shipment->id)->sum('quantity_shipped'), 2);
                    $totalShippedKoli = round((float) ShipmentItemDetail::where('shipment_id', $shipment->id)->sum('koli_shipped'), 2);

                    $totalReceivedQty = round((float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($shipment) {
                            $q->where('shipment_id', $shipment->id);
                        })
                        ->sum('accepted_quantity'), 2);
                    $totalReceivedKoli = round((float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($shipment) {
                            $q->where('shipment_id', $shipment->id);
                        })
                        ->sum('accepted_koli'), 2);

                    $isCompleted = ($totalShippedQty <= $totalReceivedQty) && ($totalShippedKoli <= $totalReceivedKoli);
                    // Shipments enum supports: 'dalam perjalanan', 'selesai'
                    $shipment->status = $isCompleted ? 'selesai' : 'dalam perjalanan';
                    $shipment->save();

                    // If this shipment belongs to a Stock In Order, and all conditions met,
                    // set the Stock In Order status to completed.
                    if ($shipment->reference_type === Shipment::REFERENCE_TYPE_STOCK_IN_ORDER) {
                        // Handle potentially multiple Stock In Orders via details
                        $sioIds = DB::table('shipment_item_details as sid')
                            ->join('stock_in_order_items as sioi', 'sioi.id', '=', 'sid.reference_id')
                            ->where('sid.shipment_id', $shipment->id)
                            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM)
                            ->pluck('sioi.stock_in_order_id')->unique();
                        foreach ($sioIds as $stockInOrderId) {
                            $sio = StockInOrder::find($stockInOrderId);
                            if ($sio) {
                                $remaining = (float) $sio->items()->sum('remaining_quantity');
                                // Determine open shipments for this SIO by joining through details
                                $hasOpenShipments = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_STOCK_IN_ORDER)
                                    ->where(function($qq) use ($stockInOrderId){
                                        $qq->whereIn('id', DB::table('shipment_item_details as sid2')
                                            ->join('stock_in_order_items as sioi2', 'sioi2.id', '=', 'sid2.reference_id')
                                            ->where('sid2.reference_type', ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM)
                                            ->where('sioi2.stock_in_order_id', $stockInOrderId)
                                            ->pluck('sid2.shipment_id'));
                                    })
                                    ->where(function($q){
                                        $q->whereNull('status')
                                          ->orWhere('status', '!=', 'selesai');
                                    })
                                    ->exists();
                                if ($remaining <= 0 && !$hasOpenShipments) {
                                    $sio->status = 'completed';
                                    $receiptDate = $goodsReceipt->receipt_date ?: now()->toDateString();
                                    if (empty($sio->shipping_at)) {
                                        $sio->shipping_at = $receiptDate;
                                    }
                                    if (empty($sio->completed_at)) {
                                        $sio->completed_at = $receiptDate;
                                    }
                                    $sio->save();
                                }
                            }
                        }
                    }

                    // If this shipment belongs to a Transfer Request, update TRI received counters and status
                    if ($shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
                        // Update received counters for TRI based on this GR details
                        // foreach ($goodsReceipt->details as $d) {
                        //     if (!empty($d->shipment_item_id)) {
                        //         $sid = ShipmentItemDetail::find($d->shipment_item_id);
                        //         if ($sid && $sid->reference_type === ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM) {
                        //             $tri = TransferRequestItem::find($sid->reference_id);
                        //             if ($tri) {
                        //                 $accQty = (int) round($d->accepted_quantity ?? $d->received_quantity ?? 0, 0);
                        //                 $accKoli = round((float) ($d->accepted_koli ?? $d->received_koli ?? 0), 2);
                        //                 $tri->received_quantity = (float) ($tri->received_quantity ?? 0) + $accQty;
                        //                 $tri->received_koli = (float) ($tri->received_koli ?? 0) + $accKoli;
                        //                 if ($tri->quantity !== null && (float)$tri->received_quantity > (float)$tri->quantity) {
                        //                     $tri->received_quantity = $tri->quantity;
                        //                 }
                        //                 if ($tri->koli !== null && (float)$tri->received_koli > (float)$tri->koli) {
                        //                     $tri->received_koli = $tri->koli;
                        //                 }
                        //                 $itemComplete = (((float) ($tri->received_quantity ?? 0)) >= (float) ($tri->quantity ?? 0))
                        //                     || (((float) ($tri->received_koli ?? 0)) >= (float) ($tri->koli ?? 0));
                        //                 if ($itemComplete) {
                        //                     $tri->status = 'completed';
                        //                 } elseif (($accQty > 0) || ($accKoli > 0)) {
                        //                     $tri->status = 'on_progress';
                        //                 }
                        //                 $tri->save();
                        //             }
                        //         }
                        //     }
                        // }
                        // Evaluate completion per each TR linked via shipment details
                        $trIds2 = DB::table('shipment_item_details as sid')
                            ->join('transfer_request_items as tri', 'tri.id', '=', 'sid.reference_id')
                            ->where('sid.shipment_id', $shipment->id)
                            ->where('sid.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                            ->pluck('tri.transfer_request_id')->unique();
                        foreach ($trIds2 as $transferRequestId) {
                            $tr = TransferRequest::find($transferRequestId);
                            if ($tr) {
                                $items = $tr->items()->get(['quantity','koli','received_quantity','received_koli']);
                                $allCompleted = $items->every(function($it){
                                    $qComplete = ((float) ($it->received_quantity ?? 0)) >= (float) ($it->quantity ?? 0);
                                    $kComplete = ((float) ($it->received_koli ?? 0)) >= (float) ($it->koli ?? 0);
                                    return $qComplete || $kComplete;
                                });
                                $anyReceived = $items->some(function($it){
                                    return ((float) ($it->received_quantity ?? 0)) > 0 || ((float) ($it->received_koli ?? 0)) > 0;
                                });
                                // Determine open shipments referencing this TR via details
                                $hasOpenTrShipments = Shipment::where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                                    ->where(function($qq) use ($transferRequestId){
                                        $qq->whereIn('id', DB::table('shipment_item_details as sid3')
                                            ->join('transfer_request_items as tri3', 'tri3.id', '=', 'sid3.reference_id')
                                            ->where('sid3.reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                                            ->where('tri3.transfer_request_id', $transferRequestId)
                                            ->pluck('sid3.shipment_id'));
                                    })
                                    ->where(function ($q) {
                                        $q->whereNull('status')->orWhere('status', '!=', 'selesai');
                                    })
                                    ->exists();
                                if ($allCompleted && !$hasOpenTrShipments) {
                                    $tr->status = 'completed';
                                } else {
                                    if ($anyReceived) {
                                        $tr->status = 'on_progress';
                                    }
                                }
                                $tr->save();
                            }
                        }
                    }
                }
            }

            // Build descriptive approval activity
            $lines = $goodsReceipt->details()->count();
            $accQty = (int) round((float) $goodsReceipt->details()->sum('accepted_quantity'), 0);
            $accKoli = round((float) $goodsReceipt->details()->sum('accepted_koli'), 2);
            $whName = optional($goodsReceipt->warehouse)->name ?? $goodsReceipt->warehouse_id;
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'Penerimaan Barang',
                'description' => 'Menyetujui Penerimaan Barang: ' . $goodsReceipt->code . ' (Gudang: ' . $whName . ', Baris: ' . $lines . ', Qty: ' . (int) $accQty . ', Koli: ' . (float) $accKoli . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            // Send notification to origin warehouse when transfer receipt is completed
            try {
                if (isset($shipment) && $shipment && $shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
                    $tr = \App\Models\TransferRequest::find($shipment->reference_id);
                    if ($tr) {
                        $recipients = \App\Models\User::where('warehouse_id', $tr->from_warehouse_id)->get();
                        foreach ($recipients as $user) {
                            $user->notify(new TransferRequestReceived($goodsReceipt, $shipment, $tr));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore notification failures
            }

            return response()->json(['success' => true, 'message' => 'Penerimaan barang disetujui dan diselesaikan.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menyelesaikan penerimaan: ' . $e->getMessage()], 500);
        }
    }

    public function show(GoodsReceipt $goodsReceipt)
    {
        $goodsReceipt->load(['warehouse', 'shipment', 'details.item', 'receiver', 'verifier']);

        return view('admin.stok_masuk.penerimaan_barang.show', compact('goodsReceipt'));
    }

    public function bukti(GoodsReceipt $goodsReceipt)
    {
        // Only allow printing for completed documents
        if ($goodsReceipt->status !== GoodsReceipt::STATUS_COMPLETED) {
            abort(403, 'Dokumen belum berstatus completed.');
        }
        $goodsReceipt->load(['warehouse', 'shipment', 'details.item', 'receiver', 'verifier']);
        return view('admin.stok_masuk.penerimaan_barang.bukti', compact('goodsReceipt'));
    }

    public function edit(GoodsReceipt $goodsReceipt)
    {
        if (!$this->permissionResolver->userCan('edit')) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit penerimaan barang.');
        }
        $goodsReceipt->load(['details.item']);
        $warehouses = Warehouse::orderBy('name')->get();
        $employees = Employee::where('active', true)->orderBy('name')->get();
        $vehicles = Vehicle::where('active', true)->orderBy('license_plate')->get();

        return view('admin.stok_masuk.penerimaan_barang.edit', compact('goodsReceipt', 'warehouses', 'employees', 'vehicles'));
    }

    public function update(Request $request, GoodsReceipt $goodsReceipt)
    {
        if (!$this->permissionResolver->userCan('edit')) {
            abort(403, 'Anda tidak memiliki akses untuk mengupdate penerimaan barang.');
        }

        $type = $request->input('type', $goodsReceipt->type);

        // Build rules based on type
        $rules = [
            'code' => ['required', 'string', 'max:50', Rule::unique('goods_receipts')->ignore($goodsReceipt->id)],
            'type' => ['required', Rule::in(['transfer','pengadaan'])],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'receipt_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_id' => ['required', 'exists:items,id'],
            'details.*.ordered_quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.ordered_koli' => ['nullable', 'numeric', 'min:0'],
            'details.*.received_quantity' => ['required', 'integer', 'min:0'],
            'details.*.received_koli' => ['nullable', 'numeric', 'min:0'],
            'details.*.notes' => ['nullable', 'string'],
        ];
        if ($type === 'transfer') {
            $rules['shipment_id'] = ['required', 'exists:shipments,id'];
            $rules['details.*.shipment_item_id'] = ['required', 'exists:shipment_item_details,id'];
        } else { // pengadaan
            $rules['stock_in_order_id'] = ['required', 'exists:stock_in_orders,id'];
            $rules['manual_shipment.shipping_date'] = ['required','date'];
            $rules['manual_shipment.vehicle_type'] = ['required','string','max:255'];
            $rules['manual_shipment.license_plate'] = ['required','string','max:255'];
            $rules['manual_shipment.driver_name'] = ['nullable','string','max:255'];
            $rules['manual_shipment.driver_contact'] = ['nullable','string','max:255'];
            $rules['manual_shipment.description'] = ['nullable','string'];
            $rules['details.*.order_item_id'] = ['nullable','exists:stock_in_order_items,id'];
        }

        $validated = $request->validate($rules);

        // Derive working shipment id
        $shipmentId = (int) ($validated['shipment_id'] ?? $goodsReceipt->shipment_id);

        if ($type === 'transfer') {
            // Validate against shipment remaining excluding this GR
            foreach ($validated['details'] as $index => $detail) {
                $shipmentItem = ShipmentItemDetail::find($detail['shipment_item_id']);
                if (!$shipmentItem || (int)$shipmentItem->shipment_id !== (int)$validated['shipment_id']) {
                    return back()->withErrors(["details.{$index}.shipment_item_id" => 'Item pengiriman tidak valid untuk shipment ini.'])->withInput();
                }
                $shippedQty = (int) round((float) ($shipmentItem->quantity_shipped ?? 0), 0);
                $shippedKoli = (float) ($shipmentItem->koli_shipped ?? 0);
                $alreadyReceivedOthersQty = (int) round((float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($validated, $goodsReceipt) {
                        $q->where('shipment_id', $validated['shipment_id'])
                          ->where('id', '!=', $goodsReceipt->id);
                    })->where('shipment_item_id', $shipmentItem->id)->sum('received_quantity'), 0);
                $alreadyReceivedOthersKoli = (float) GoodsReceiptDetail::whereHas('goodsReceipt', function ($q) use ($validated, $goodsReceipt) {
                        $q->where('shipment_id', $validated['shipment_id'])
                          ->where('id', '!=', $goodsReceipt->id);
                    })->where('shipment_item_id', $shipmentItem->id)->sum('received_koli');
                $allowedQty = (int) max(0, $shippedQty - $alreadyReceivedOthersQty);
                $allowedKoli = max(0, $shippedKoli - $alreadyReceivedOthersKoli);
                if (($detail['received_quantity'] ?? 0) > $allowedQty) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_quantity" => "Item {$itemName}: Jumlah diterima tidak boleh melebihi sisa dikirim ({$allowedQty})."
                    ])->withInput();
                }
                if (($detail['received_koli'] ?? 0) > $allowedKoli) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors([
                        "details.{$index}.received_koli" => "Item {$itemName}: Koli diterima tidak boleh melebihi sisa dikirim ({$allowedKoli})."
                    ])->withInput();
                }
            }
        } else { // pengadaan
            // Validate against SIO remaining
            foreach ($validated['details'] as $index => $detail) {
                $orderItemId = $detail['order_item_id'] ?? StockInOrderItem::where('stock_in_order_id', $validated['stock_in_order_id'])
                    ->where('item_id', $detail['item_id'])->value('id');
                $orderItem = $orderItemId ? StockInOrderItem::find($orderItemId) : null;
                if (!$orderItem) {
                    return back()->withErrors(["details.{$index}.item_id" => 'Item tidak valid untuk dokumen pengadaan ini.'])->withInput();
                }
                $remainingQty = (int) ($orderItem->remaining_quantity ?? 0);
                $remainingKoli = (float) ($orderItem->remaining_koli ?? 0);
                if (($detail['received_quantity'] ?? 0) > $remainingQty) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors(["details.{$index}.received_quantity" => "Item {$itemName}: Jumlah diterima tidak boleh melebihi sisa pesanan ({$remainingQty})."]) ->withInput();
                }
                if (($detail['received_koli'] ?? 0) > $remainingKoli) {
                    $itemName = Item::find($detail['item_id'])->nama_barang ?? 'Item';
                    return back()->withErrors(["details.{$index}.received_koli" => "Item {$itemName}: Koli diterima tidak boleh melebihi sisa pesanan ({$remainingKoli})."]) ->withInput();
                }
            }
        }

        DB::beginTransaction();
        try {
            // Handle shipment on edit
            if ($type === 'pengadaan') {
                // Create new shipment from manual inputs FIRST
                $shipment = Shipment::create([
                    'code' => $this->generateShipmentCode(),
                    'reference_type' => Shipment::REFERENCE_TYPE_STOCK_IN_ORDER,
                    'from_warehouse_id' => null,
                    'to_warehouse_id' => (string) $validated['warehouse_id'],
                    'shipping_date' => $validated['manual_shipment']['shipping_date'],
                    'vehicle_type' => $validated['manual_shipment']['vehicle_type'],
                    'license_plate' => $validated['manual_shipment']['license_plate'],
                    'driver_name' => $validated['manual_shipment']['driver_name'] ?? null,
                    'driver_contact' => $validated['manual_shipment']['driver_contact'] ?? null,
                    'description' => $validated['manual_shipment']['description'] ?? null,
                    'shipped_by' => Auth::id(),
                ]);
                // Re-point GR to new shipment to avoid nulling shipment_id (FK non-null)
                $oldShipment = null;
                if (!empty($goodsReceipt->shipment_id)) {
                    $oldShipment = Shipment::find($goodsReceipt->shipment_id);
                }
                $goodsReceipt->shipment_id = $shipment->id;
                $goodsReceipt->save();
                // After reassignment, safely remove old SIO shipment if applicable
                if ($oldShipment && $oldShipment->reference_type === Shipment::REFERENCE_TYPE_STOCK_IN_ORDER) {
                    ShipmentItemDetail::where('shipment_id', $oldShipment->id)->delete();
                    $oldShipment->delete();
                }

                // Build shipment item details to match received items
                foreach ($validated['details'] as $idx => $detail) {
                    $orderItemId = $detail['order_item_id'] ?? StockInOrderItem::where('stock_in_order_id', $validated['stock_in_order_id'])
                        ->where('item_id', $detail['item_id'])->value('id');
                    $sid = ShipmentItemDetail::create([
                        'shipment_id' => $shipment->id,
                        'item_id' => $detail['item_id'],
                        'reference_id' => $orderItemId,
                        'reference_type' => ShipmentItemDetail::REFERENCE_TYPE_STOCK_IN_ORDER_ITEM,
                        'quantity_shipped' => (int) ($detail['received_quantity'] ?? 0),
                        'koli_shipped' => (float) ($detail['received_koli'] ?? 0),
                        'description' => $detail['notes'] ?? null,
                    ]);
                    $validated['details'][$idx]['shipment_item_id'] = $sid->id;
                }
            } else { // transfer
                // Persist selected transfer shipment FIRST
                if (isset($validated['shipment_id']) && (int)$goodsReceipt->shipment_id !== (int)$validated['shipment_id']) {
                    $goodsReceipt->shipment_id = (int)$validated['shipment_id'];
                    $goodsReceipt->save();
                }
                // If previously referenced an SIO shipment, remove it now that GR points elsewhere
                // Cleanup: if GR previously pointed to SIO shipment and now points to a transfer shipment, delete old SIO shipment
                if (!empty($previousShipmentId)) {
                    $prevShipment = Shipment::find($previousShipmentId);
                    if ($prevShipment && $prevShipment->reference_type === Shipment::REFERENCE_TYPE_STOCK_IN_ORDER && (int)$prevShipment->id !== (int)$goodsReceipt->shipment_id) {
                        ShipmentItemDetail::where('shipment_id', $prevShipment->id)->delete();
                        $prevShipment->delete();
                    }
                }
            }

            // Update GR header
            $goodsReceipt->update([
                'code' => $validated['code'],
                'type' => $type,
                'warehouse_id' => $validated['warehouse_id'],
                'receipt_date' => $validated['receipt_date'],
                'description' => $validated['description'] ?? null,
            ]);

            // Replace GR details
            $goodsReceipt->details()->delete();
            foreach ($validated['details'] as $detail) {
                $goodsReceipt->details()->create([
                    'item_id' => $detail['item_id'],
                    'shipment_item_id' => $detail['shipment_item_id'] ?? null,
                    'ordered_quantity' => (int) round($detail['ordered_quantity'] ?? 0, 0),
                    'ordered_koli' => $detail['ordered_koli'] ?? 0,
                    'received_quantity' => (int) round($detail['received_quantity'] ?? 0, 0),
                    'received_koli' => $detail['received_koli'] ?? 0,
                    'accepted_quantity' => (int) round($detail['received_quantity'] ?? 0, 0),
                    'accepted_koli' => $detail['received_koli'] ?? 0,
                    'rejected_quantity' => 0,
                    'rejected_koli' => 0,
                    'notes' => $detail['notes'] ?? null,
                ]);
            }

            // Descriptive update activity
            $itemsCount = is_array($validated['details'] ?? null) ? count($validated['details']) : 0;
            $totalQty = 0; $totalKoli = 0.0;
            foreach (($validated['details'] ?? []) as $d) {
                $totalQty += (int) round($d['received_quantity'] ?? 0, 0);
                $totalKoli += round((float) ($d['received_koli'] ?? 0), 2);
            }
            $wh = Warehouse::find($validated['warehouse_id']);
            $whName = $wh->name ?? ($validated['warehouse_id'] ?? '-');
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'Penerimaan Barang',
                'description' => 'Mengubah Penerimaan Barang: ' . $goodsReceipt->code . ' (Tipe: ' . ($type ?? '-') . ', Gudang: ' . $whName . ', Baris: ' . $itemsCount . ', Qty: ' . (int) $totalQty . ', Koli: ' . (float) $totalKoli . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.stok-masuk.penerimaan-barang.index')->with('success', 'Penerimaan barang berhasil diupdate.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['general' => 'Gagal mengupdate penerimaan barang: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy(Request $request, GoodsReceipt $goodsReceipt)
    {
        if (!$this->permissionResolver->userCan('delete')) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus penerimaan barang.');
        }

        DB::beginTransaction();
        try {
            $goodsReceipt->details()->delete();
            $goodsReceipt->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'Penerimaan Barang',
                'description' => 'Menghapus penerimaan barang: ' . $goodsReceipt->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Penerimaan barang berhasil dihapus.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal menghapus penerimaan barang.'], 500);
        }
    }
}
