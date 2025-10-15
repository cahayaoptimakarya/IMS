<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use App\Models\TransferRequest;
use App\Models\TransferRequestItem;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\Vehicle;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class RiwayatPengirimanController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);

            $statusFilter = $request->input('status');
            $refTypeFilter = $request->input('reference_type');
            $dateFilter = $request->input('date');

            $columns = [
                0 => 's.code',
                1 => 's.shipping_date',
                2 => 'reference_code',
                3 => 'from_warehouse_name',
                4 => 'to_warehouse_name',
                5 => 's.status',
                6 => 's.driver_name',
                7 => 's.id',
            ];

            $base = Shipment::query()->from('shipments as s')
                ->leftJoin('transfer_requests as tr', function ($j) {
                    $j->on('s.reference_id', '=', 'tr.id')
                        ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST);
                })
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->leftJoin('stock_in_orders as sio', function ($j) {
                    $j->on('s.reference_id', '=', 'sio.id')
                        ->where('s.reference_type', Shipment::REFERENCE_TYPE_STOCK_IN_ORDER);
                })
                ->leftJoin('warehouses as wio', 'sio.warehouse_id', '=', 'wio.id');

            if (auth()->user() && auth()->user()->warehouse_id) {
                $wid = (int) auth()->user()->warehouse_id;
                // Only show shipments originating from the user's warehouse (transfer requests only)
                $base->where(function ($q) use ($wid) {
                    $q->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                      ->where('tr.from_warehouse_id', $wid);
                });
            }

            $totalRecords = (clone $base)->count();

            if ($statusFilter && $statusFilter !== 'semua') {
                $base->where('s.status', $statusFilter);
            }
            if ($refTypeFilter && $refTypeFilter !== 'semua') {
                $base->where('s.reference_type', $refTypeFilter);
            }
            if ($dateFilter && $dateFilter !== 'semua') {
                if (str_contains($dateFilter, ' to ')) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $base->whereBetween('s.shipping_date', [$startDate, $endDate]);
                } else {
                    $base->whereDate('s.shipping_date', $dateFilter);
                }
            }

            if (!empty($searchValue)) {
                $base->where(function ($q) use ($searchValue) {
                    $q->where('s.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tr.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('sio.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('fw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('tw.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('wio.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('s.driver_name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('s.license_plate', 'LIKE', "%{$searchValue}%");
                });
            }

            $totalFiltered = (clone $base)->count();

            $orderColumnIndex = (int) $request->input('order.0.column', 0);
            $orderColumnName = $columns[$orderColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            $rows = $base->select([
                    's.id', 's.code', 's.shipping_date', 's.status', 's.reference_type', 's.reference_id',
                    DB::raw('COALESCE(tr.code, sio.code) as reference_code'),
                    DB::raw('fw.name as from_warehouse_name'),
                    DB::raw('COALESCE(tw.name, wio.name) as to_warehouse_name'),
                    's.vehicle_type', 's.license_plate', 's.driver_name'
                ])
                ->orderBy($orderColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'code' => $r->code,
                        'shipping_date' => $r->shipping_date ? Carbon::parse($r->shipping_date)->format('Y-m-d') : null,
                        'status' => $r->status,
                        'reference_type' => $r->reference_type,
                        'reference_code' => $r->reference_code,
                        'reference_id' => $r->reference_id,
                        'from_warehouse_name' => $r->from_warehouse_name,
                        'to_warehouse_name' => $r->to_warehouse_name,
                        'vehicle' => trim(($r->vehicle_type ? $r->vehicle_type.' ' : '') . ($r->license_plate ?? '')),
                        'driver' => $r->driver_name,
                    ];
                });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $rows,
            ]);
        }

        $warehouses = Warehouse::orderBy('name')->get();
        $statusOptions = ['dalam perjalanan', 'selesai'];
        return view('admin.riwayat_pengiriman.index', compact('warehouses', 'statusOptions'));
    }

    public function getStatusCounts(Request $request)
    {
        $refTypeFilter = $request->input('reference_type');
        $dateFilter = $request->input('date');

        $base = Shipment::query()->from('shipments as s')
            ->leftJoin('transfer_requests as tr', function ($j) {
                $j->on('s.reference_id', '=', 'tr.id')
                    ->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST);
            });

        if (auth()->user() && auth()->user()->warehouse_id) {
            $wid = (int) auth()->user()->warehouse_id;
            $base->where(function ($q) use ($wid) {
                $q->where('s.reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
                  ->where('tr.from_warehouse_id', $wid);
            });
        }

        if ($refTypeFilter && $refTypeFilter !== 'semua') {
            $base->where('s.reference_type', $refTypeFilter);
        }
        if ($dateFilter && $dateFilter !== 'semua') {
            if (str_contains($dateFilter, ' to ')) {
                [$startDate, $endDate] = explode(' to ', $dateFilter);
                $base->whereBetween('s.shipping_date', [$startDate, $endDate]);
            } else {
                $base->whereDate('s.shipping_date', $dateFilter);
            }
        }

        return response()->json([
            'in_transit' => (clone $base)->where('s.status', 'dalam perjalanan')->count(),
            'completed' => (clone $base)->where('s.status', 'selesai')->count(),
        ]);
    }

    public function show(Shipment $shipment)
    {
        $shipment->load(['itemDetails.item']);

        // Determine reference code and warehouses
        $referenceCode = null;
        $fromWarehouse = null;
        $toWarehouse = null;

        if ($shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            $tr = DB::table('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->where('tr.id', $shipment->reference_id)
                ->select('tr.code as reference_code', 'fw.name as from_name', 'tw.name as to_name')
                ->first();
            $referenceCode = $tr->reference_code ?? null;
            $fromWarehouse = $tr->from_name ?? null;
            $toWarehouse = $tr->to_name ?? null;
        } else {
            $sio = DB::table('stock_in_orders as sio')
                ->leftJoin('warehouses as w', 'sio.warehouse_id', '=', 'w.id')
                ->where('sio.id', $shipment->reference_id)
                ->select('sio.code as reference_code', 'w.name as to_name')
                ->first();
            $referenceCode = $sio->reference_code ?? null;
            $toWarehouse = $sio->to_name ?? null;
        }

        // Load receipts for this shipment (if any)
        $receipts = \App\Models\GoodsReceipt::where('shipment_id', $shipment->id)
            ->with(['details.item', 'warehouse'])
            ->orderBy('receipt_date')
            ->get();

        return view('admin.riwayat_pengiriman.show', compact('shipment', 'referenceCode', 'fromWarehouse', 'toWarehouse', 'receipts'));
    }

    public function edit(Shipment $shipment)
    {
        if (strtolower((string) $shipment->status) === 'selesai' || strtolower((string) $shipment->status) === 'completed') {
            return redirect()->route('admin.riwayat-pengiriman.index')->with('error', 'Pengiriman yang sudah selesai tidak dapat diedit.');
        }

        $shipment->load(['itemDetails.item']);

        $referenceCode = null;
        $fromWarehouse = null;
        $toWarehouse = null;
        $fromWarehouseId = null;

        if ($shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            $tr = DB::table('transfer_requests as tr')
                ->leftJoin('warehouses as fw', 'tr.from_warehouse_id', '=', 'fw.id')
                ->leftJoin('warehouses as tw', 'tr.to_warehouse_id', '=', 'tw.id')
                ->where('tr.id', $shipment->reference_id)
                ->select('tr.id','tr.code as reference_code', 'fw.name as from_name', 'tw.name as to_name', 'tr.from_warehouse_id')
                ->first();
            $referenceCode = $tr->reference_code ?? null;
            $fromWarehouse = $tr->from_name ?? null;
            $toWarehouse = $tr->to_name ?? null;
            $fromWarehouseId = $tr->from_warehouse_id ?? null;
        } else {
            $sio = DB::table('stock_in_orders as sio')
                ->leftJoin('warehouses as w', 'sio.warehouse_id', '=', 'w.id')
                ->where('sio.id', $shipment->reference_id)
                ->select('sio.code as reference_code', 'w.name as to_name')
                ->first();
            $referenceCode = $sio->reference_code ?? null;
            $toWarehouse = $sio->to_name ?? null;
        }

        // Compute per-line max (remaining + current) for transfer requests
        $lineMax = [];
        if ($shipment->reference_type === Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            foreach ($shipment->itemDetails as $detail) {
                $tri = TransferRequestItem::find($detail->reference_id);
                if ($tri) {
                    $lineMax[$detail->id] = [
                        'max_qty' => (float) ($tri->remaining_quantity ?? 0) + (float) ($detail->quantity_shipped ?? 0),
                        'max_koli' => (float) ($tri->remaining_koli ?? 0) + (float) ($detail->koli_shipped ?? 0),
                    ];
                } else {
                    $lineMax[$detail->id] = [ 'max_qty' => (float) ($detail->quantity_shipped ?? 0), 'max_koli' => (float) ($detail->koli_shipped ?? 0) ];
                }
            }
        }

        $vehicles = Vehicle::all();
        $employees = Employee::all();
        return view('admin.riwayat_pengiriman.edit', compact('shipment', 'referenceCode', 'fromWarehouse', 'toWarehouse', 'fromWarehouseId', 'lineMax', 'vehicles', 'employees'));
    }

    public function update(Request $request, Shipment $shipment)
    {
        if (strtolower((string) $shipment->status) === 'selesai' || strtolower((string) $shipment->status) === 'completed') {
            return back()->with('error', 'Pengiriman yang sudah selesai tidak dapat diedit.');
        }

        $validated = $request->validate([
            'shipping_date' => 'required|date',
            'vehicle_type' => 'required|string|max:255',
            'license_plate' => 'required|string|max:255',
            'driver_name' => 'required|string|max:255',
            'driver_contact' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.koli' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string|max:255',
        ]);

        // Only full edit with item quantities is supported for Transfer Request shipments
        if ($shipment->reference_type !== Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            // For other types, update header only
            $shipment->update([
                'shipping_date' => $validated['shipping_date'],
                'vehicle_type' => $validated['vehicle_type'],
                'license_plate' => $validated['license_plate'],
                'driver_name' => $validated['driver_name'],
                'driver_contact' => $validated['driver_contact'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);
            return redirect()->route('admin.riwayat-pengiriman.show', $shipment->id)->with('success', 'Pengiriman diperbarui.');
        }

        try {
            DB::transaction(function () use ($validated, $shipment, $request) {
                // reload details
                $shipment->load('itemDetails');

                // Determine from warehouse for inventory check
                $transferRequest = TransferRequest::find($shipment->reference_id);
                if (!$transferRequest) {
                    throw ValidationException::withMessages(['shipment' => 'Referensi permintaan transfer tidak ditemukan.']);
                }

                // 1) Validate each line against remaining + current, and inventory for positive delta
                foreach ($validated['items'] as $sid => $itemData) {
                    $detail = ShipmentItemDetail::where('shipment_id', $shipment->id)->findOrFail($sid);
                    $newQty = (float) ($itemData['quantity'] ?? 0);
                    $newKoli = (float) ($itemData['koli'] ?? 0);
                    $oldQty = (float) ($detail->quantity_shipped ?? 0);
                    $oldKoli = (float) ($detail->koli_shipped ?? 0);

                    $tri = TransferRequestItem::find($detail->reference_id);
                    if ($tri) {
                        $maxQty = (float) ($tri->remaining_quantity ?? 0) + $oldQty;
                        $maxKoli = (float) ($tri->remaining_koli ?? 0) + $oldKoli;
                        if ($newQty > $maxQty || $newKoli > $maxKoli) {
                            throw ValidationException::withMessages(['items' => 'Kuantitas kirim melebihi sisa yang diminta untuk item ' . ($detail->item->nama_barang ?? $detail->item->name ?? 'ID '.$detail->item_id) . '.']);
                        }
                    }

                    $deltaQty = $newQty - $oldQty;
                    $deltaKoli = $newKoli - $oldKoli;

                    if ($deltaQty > 0 || $deltaKoli > 0) {
                        $inventory = Inventory::where('warehouse_id', $transferRequest->from_warehouse_id)
                            ->where('item_id', $detail->item_id)
                            ->first();
                        if (!$inventory || (float) $inventory->quantity < (float) $deltaQty) {
                            throw ValidationException::withMessages(['items' => 'Stok tidak mencukupi di gudang asal untuk item ' . ($detail->item->nama_barang ?? $detail->item->name ?? 'ID '.$detail->item_id) . '.']);
                        }
                    }
                }

                // 2) Update header
                $oldShippingDate = $shipment->shipping_date;
                $shipment->update([
                    'shipping_date' => $validated['shipping_date'],
                    'vehicle_type' => $validated['vehicle_type'],
                    'license_plate' => $validated['license_plate'],
                    'driver_name' => $validated['driver_name'],
                    'driver_contact' => $validated['driver_contact'] ?? null,
                    'description' => $validated['description'] ?? null,
                ]);

                // 3) Apply inventory movements by editing original stock movements
                //    and update details + TRI remaining
                foreach ($validated['items'] as $sid => $itemData) {
                    $detail = ShipmentItemDetail::where('shipment_id', $shipment->id)->findOrFail($sid);
                    $newQty = (float) ($itemData['quantity'] ?? 0);
                    $newKoli = (float) ($itemData['koli'] ?? 0);
                    $oldQty = (float) ($detail->quantity_shipped ?? 0);
                    $oldKoli = (float) ($detail->koli_shipped ?? 0);
                    $deltaQty = $newQty - $oldQty;
                    $deltaKoli = $newKoli - $oldKoli;

                    $tri = TransferRequestItem::find($detail->reference_id);

                    if ($deltaQty !== 0 || $deltaKoli !== 0) {
                        // Adjust inventory first (based on delta)
                        $inventory = Inventory::firstOrNew([
                            'warehouse_id' => $transferRequest->from_warehouse_id,
                            'item_id' => $detail->item_id,
                        ]);
                        $before = (float) ($inventory->quantity ?? 0);
                        if ($deltaQty > 0 || $deltaKoli > 0) {
                            // ship more => reduce inventory
                            $inventory->quantity = $before - $deltaQty;
                            $inventory->koli = (float) ($inventory->koli ?? 0) - $deltaKoli;
                        } else {
                            // ship less => return inventory
                            $inventory->quantity = $before + abs($deltaQty);
                            $inventory->koli = (float) ($inventory->koli ?? 0) + abs($deltaKoli);
                        }
                        $inventory->save();

                        // Edit the original stock movement for this shipment line
                        $movement = StockMovement::where('type', 'stock_out')
                            ->where('item_id', $detail->item_id)
                            ->where('warehouse_id', $transferRequest->from_warehouse_id)
                            ->where('reference_type', 'transfer_request_items')
                            ->where('reference_id', $detail->reference_id)
                            ->where('date', $oldShippingDate)
                            ->where('quantity', -$oldQty)
                            ->where('koli', -$oldKoli)
                            ->orderByDesc('id')
                            ->first();

                        if ($movement) {
                            // Keep stock_before; recalc stock_after using new quantity
                            $stockBefore = (float) ($movement->stock_before ?? 0);
                            $movement->date = $validated['shipping_date'];
                            $movement->quantity = -$newQty;
                            $movement->koli = -$newKoli;
                            $movement->stock_after = $stockBefore + (-$newQty);
                            $movement->description = 'Pengiriman permintaan transfer ' . ($transferRequest->code ?? $movement->description);
                            $movement->user_id = Auth::id();
                            $movement->save();
                        } else {
                            // Fallback: create one if not found
                            StockMovement::create([
                                'item_id' => $detail->item_id,
                                'warehouse_id' => $transferRequest->from_warehouse_id,
                                'date' => $validated['shipping_date'],
                                'quantity' => -$newQty,
                                'koli' => -$newKoli,
                                'stock_before' => $before,
                                'stock_after' => $inventory->quantity,
                                'type' => 'stock_out',
                                'description' => 'Pengiriman permintaan transfer ' . ($transferRequest->code ?? $shipment->code),
                                'user_id' => Auth::id(),
                                'reference_id' => $detail->reference_id,
                                'reference_type' => 'transfer_request_items',
                            ]);
                        }

                        // Update TRI remaining accordingly
                        if ($tri) {
                            $tri->remaining_quantity = (float) ($tri->remaining_quantity ?? 0) - $deltaQty; // subtract positive delta, add if negative
                            $tri->remaining_koli = (float) ($tri->remaining_koli ?? 0) - $deltaKoli;
                            $tri->save();
                        }
                    }

                    // Update shipment detail values
                    $detail->quantity_shipped = $newQty;
                    $detail->koli_shipped = $newKoli;
                    $detail->description = $itemData['description'] ?? $detail->description;
                    $detail->save();
                }
            });

            return redirect()->route('admin.riwayat-pengiriman.show', $shipment->id)->with('success', 'Pengiriman dan pergerakan stok berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memperbarui pengiriman: ' . $e->getMessage())->withInput();
        }
    }
}
