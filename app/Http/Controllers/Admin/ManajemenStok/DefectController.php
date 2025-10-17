<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Defect;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DefectController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            if (is_array($searchValue)) {
                $searchValue = $searchValue['value'] ?? '';
            }
            $manualSearch = $request->input('search');
            if (is_string($manualSearch) && $manualSearch !== '') {
                $searchValue = $manualSearch;
            }
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            // Define columns for sorting (mirror client columns)
            if (is_null(auth()->user()->warehouse_id)) {
                // code, date, warehouse.name, item.nama_barang, quantity, koli, status, action, action_status, user_name
                $columns = [
                    0 => 'd.code',
                    1 => 'd.date',
                    2 => 'w.name',
                    3 => 'i.nama_barang',
                    4 => 'd.quantity',
                    5 => 'd.koli',
                    6 => 'd.status',
                    7 => 'd.action',
                    8 => 'd.action_status',
                    9 => 'u.name',
                ];
            } else {
                // code, date, item.nama_barang, quantity, koli, status, action, action_status, user_name
                $columns = [
                    0 => 'd.code',
                    1 => 'd.date',
                    2 => 'i.nama_barang',
                    3 => 'd.quantity',
                    4 => 'd.koli',
                    5 => 'd.status',
                    6 => 'd.action',
                    7 => 'd.action_status',
                    8 => 'u.name',
                ];
            }

            $orderByColumnIndex = (int) $request->input('order.0.column', 1);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? 'd.date';
            $orderDirection = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

            $query = Defect::query()
                ->from('defects as d')
                ->leftJoin('warehouses as w', 'd.warehouse_id', '=', 'w.id')
                ->leftJoin('items as i', 'd.item_id', '=', 'i.id')
                ->leftJoin('users as u', 'd.created_by', '=', 'u.id');

            // Scope to user's warehouse if set
            if (!is_null(auth()->user()->warehouse_id)) {
                $query->where('d.warehouse_id', auth()->user()->warehouse_id);
            }

            $totalRecords = (clone $query)->count('d.id');

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('d.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('d.date', 'LIKE', "%{$searchValue}%")
                        ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%")
                        ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                        ->orWhere('d.notes', 'LIKE', "%{$searchValue}%")
                        ->orWhere('u.name', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('d.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('d.date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('d.date', $dateFilter);
                }
            }

            $totalFiltered = (clone $query)->count('d.id');

            $rows = $query->select(
                'd.id',
                'd.code',
                'd.date',
                'd.quantity',
                'd.koli',
                'd.notes',
                'd.status',
                'd.action',
                'd.action_status',
                'w.name as warehouse_name',
                'i.nama_barang as item_name',
                'i.sku as item_sku',
                'u.name as user_name'
            )
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            // Shape data to support dot-notation columns in view (warehouse.name, item.nama_barang)
            $data = $rows->map(function ($r) {
                return [
                    'id' => $r->id,
                    'code' => $r->code,
                    'date' => $r->date,
                    'warehouse' => ['name' => $r->warehouse_name],
                    'item' => ['nama_barang' => $r->item_name, 'sku' => $r->item_sku],
                    'quantity' => (float) $r->quantity,
                    'koli' => $r->koli !== null ? (float) $r->koli : null,
                    'notes' => $r->notes,
                    'status' => $r->status,
                    'action' => $r->action,
                    'action_status' => $r->action_status,
                    'user_name' => $r->user_name ?? 'N/A',
                ];
            });

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => (int) $totalRecords,
                'recordsFiltered' => (int) $totalFiltered,
                'data' => $data,
            ]);
        }

        return view('admin.manajemenstok.defects.index');
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::select('id', 'nama_barang', 'sku', 'koli')->get();
        $newCode = $this->generateUniqueCode();
        return view('admin.manajemenstok.defects.create', compact('warehouses', 'items', 'newCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0.01',
            'koli' => 'nullable|numeric|min:0',
            'notes' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $koliVal = $request->koli ?? 0;

            $defect = Defect::create([
                'code' => $this->generateUniqueCode(),
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'item_id' => $request->item_id,
                'quantity' => $request->quantity,
                'koli' => $koliVal,
                'notes' => $request->notes,
                'created_by' => Auth::id(),
                'status' => 'pending',
            ]);

            // Log user activity (non-blocking if fails)
            try {
                $item = Item::select('sku','nama_barang')->find($defect->item_id);
                $itemText = $item ? (($item->sku ? ($item->sku.' - ') : '') . ($item->nama_barang ?? '')) : ('Item ID: '.$defect->item_id);
                $desc = sprintf(
                    'Membuat draft barang rusak: %s | Item: %s | Qty: %s | Koli: %s',
                    $defect->code,
                    $itemText,
                    rtrim(rtrim(number_format((float)$defect->quantity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float)($defect->koli ?? 0), 2, '.', ''), '0'), '.')
                );
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'created',
                    'menu' => 'Barang Rusak',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { /* ignore activity errors */ }

            DB::commit();
            return redirect()->route('admin.manajemenstok.defects.index')->with('success', 'Barang rusak berhasil dibuat (draft).');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function edit(Defect $defect)
    {
        if (($defect->status ?? 'pending') !== 'pending') {
            return redirect()->route('admin.manajemenstok.defects.index')->with('error', 'Hanya data dengan status pending yang dapat diubah.');
        }
        $warehouses = Warehouse::all();
        $items = Item::select('id', 'nama_barang', 'sku', 'koli')->get();
        return view('admin.manajemenstok.defects.edit', compact('defect', 'warehouses', 'items'));
    }

    public function update(Request $request, Defect $defect)
    {
        if (($defect->status ?? 'pending') !== 'pending') {
            return redirect()->route('admin.manajemenstok.defects.index')->with('error', 'Hanya data dengan status pending yang dapat diubah.');
        }

        $request->validate([
            'date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
            'quantity' => 'required|numeric|min:0.01',
            'koli' => 'nullable|numeric|min:0',
            'notes' => 'required|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $defect->update([
                'date' => $request->date,
                'warehouse_id' => $request->warehouse_id,
                'item_id' => $request->item_id,
                'quantity' => $request->quantity,
                'koli' => $request->koli ?? 0,
                'notes' => $request->notes,
            ]);

            try {
                $item = Item::select('sku','nama_barang')->find($defect->item_id);
                $itemText = $item ? (($item->sku ? ($item->sku.' - ') : '') . ($item->nama_barang ?? '')) : ('Item ID: '.$defect->item_id);
                $desc = sprintf(
                    'Mengubah draft barang rusak: %s | Item: %s | Qty: %s | Koli: %s',
                    $defect->code,
                    $itemText,
                    rtrim(rtrim(number_format((float)$defect->quantity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float)($defect->koli ?? 0), 2, '.', ''), '0'), '.')
                );
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'updated',
                    'menu' => 'Barang Rusak',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { }

            DB::commit();
            return redirect()->route('admin.manajemenstok.defects.index')->with('success', 'Draft barang rusak berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui draft: ' . $e->getMessage())->withInput();
        }
    }

    public function approve(Request $request, Defect $defect)
    {
        if (($defect->status ?? 'pending') !== 'pending') {
            return redirect()->back()->with('error', 'Hanya data pending yang dapat di-approve.');
        }

        DB::beginTransaction();
        try {
            $inventory = Inventory::where('warehouse_id', $defect->warehouse_id)
                ->where('item_id', $defect->item_id)
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->quantity < $defect->quantity) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Stok tidak mencukupi untuk approve.');
            }

            $stock_before = (float) $inventory->quantity;
            $inventory->decrement('quantity', $defect->quantity);
            if (($defect->koli ?? 0) > 0) {
                $inventory->decrement('koli', $defect->koli ?? 0);
            }

            StockMovement::create([
                'item_id' => $defect->item_id,
                'warehouse_id' => $defect->warehouse_id,
                'date' => $defect->date,
                'quantity' => -$defect->quantity,
                'koli' => -($defect->koli ?? 0),
                'stock_before' => $stock_before,
                'stock_after' => $inventory->quantity,
                'type' => 'stock_out',
                'description' => 'Barang Rusak/Defect',
                'user_id' => Auth::id(),
                'reference_id' => $defect->id,
                'reference_type' => 'defects',
            ]);

            $defect->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            try {
                $defect->loadMissing('item');
                $item = $defect->item;
                $itemText = $item ? (($item->sku ? ($item->sku.' - ') : '') . ($item->nama_barang ?? '')) : ('Item ID: '.$defect->item_id);
                $desc = sprintf(
                    'Menyetujui barang rusak: %s | Item: %s | Qty: %s | Koli: %s',
                    $defect->code,
                    $itemText,
                    rtrim(rtrim(number_format((float)$defect->quantity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float)($defect->koli ?? 0), 2, '.', ''), '0'), '.')
                );
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'approved',
                    'menu' => 'Barang Rusak',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { }

            DB::commit();
            return redirect()->route('admin.manajemenstok.defects.index')->with('success', 'Barang rusak berhasil di-approve.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal approve: ' . $e->getMessage());
        }
    }

    public function updateAction(Request $request, Defect $defect)
    {
        if (($defect->status ?? 'pending') !== 'approved') {
            return redirect()->back()->with('error', 'Tindakan hanya dapat diproses jika status sudah approved.');
        }

        $data = $request->validate([
            'action' => 'required|in:disposal,return_to_vendor,rework,return_to_stock,other',
            'action_notes' => 'nullable|string|max:2000',
        ]);

        try {
            $defect->update([
                'action' => $data['action'],
                'action_notes' => $data['action_notes'] ?? null,
                'action_status' => 'in_progress',
                'handled_by' => Auth::id(),
            ]);

            try {
                $defect->loadMissing('item');
                $item = $defect->item;
                $itemText = $item ? (($item->sku ? ($item->sku.' - ') : '') . ($item->nama_barang ?? '')) : ('Item ID: '.$defect->item_id);
                $desc = sprintf(
                    'Memulai penanganan defect: %s (%s) | Item: %s | Qty: %s | Koli: %s',
                    $defect->code,
                    $defect->action,
                    $itemText,
                    rtrim(rtrim(number_format((float)$defect->quantity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float)($defect->koli ?? 0), 2, '.', ''), '0'), '.')
                );
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'updated',
                    'menu' => 'Barang Rusak',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { }

            return redirect()->back()->with('success', 'Penanganan dimulai.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal memulai penanganan: ' . $e->getMessage());
        }
    }

    public function completeAction(Request $request, Defect $defect)
    {
        if (($defect->status ?? 'pending') !== 'approved') {
            return redirect()->back()->with('error', 'Hanya data approved yang dapat diselesaikan tindakannya.');
        }
        if (!$defect->action) {
            return redirect()->back()->with('error', 'Pilih tindakan terlebih dahulu.');
        }
        if (in_array($defect->action_status, ['completed', 'cancelled'])) {
            return redirect()->back()->with('error', 'Tindakan sudah diselesaikan atau dibatalkan.');
        }

        $validated = $request->validate([
            'action_notes' => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            // If return to stock, add back inventory and movement
            if ($defect->action === 'return_to_stock') {
                $inventory = Inventory::where('warehouse_id', $defect->warehouse_id)
                    ->where('item_id', $defect->item_id)
                    ->lockForUpdate()
                    ->first();

                if (!$inventory) {
                    // create inventory record if not exists
                    $inventory = Inventory::create([
                        'warehouse_id' => $defect->warehouse_id,
                        'item_id' => $defect->item_id,
                        'quantity' => 0,
                        'koli' => 0,
                    ]);
                }

                $stock_before = (float) $inventory->quantity;
                $inventory->increment('quantity', $defect->quantity);
                if (($defect->koli ?? 0) > 0) {
                    $inventory->increment('koli', $defect->koli ?? 0);
                }

                StockMovement::create([
                    'item_id' => $defect->item_id,
                    'warehouse_id' => $defect->warehouse_id,
                    'date' => now()->toDateString(),
                    'quantity' => $defect->quantity,
                    'koli' => ($defect->koli ?? 0),
                    'stock_before' => $stock_before,
                    'stock_after' => $inventory->quantity,
                    'type' => 'stock_in',
                    'description' => 'Return to stock dari defect: ' . $defect->code,
                    'user_id' => Auth::id(),
                    'reference_id' => $defect->id,
                    'reference_type' => 'defects',
                ]);
            }

            $defect->update([
                'action_status' => 'completed',
                'action_notes' => $validated['action_notes'] ?? $defect->action_notes,
                'handled_by' => Auth::id(),
                'handled_at' => now(),
            ]);

            try {
                $defect->loadMissing('item');
                $item = $defect->item;
                $itemText = $item ? (($item->sku ? ($item->sku.' - ') : '') . ($item->nama_barang ?? '')) : ('Item ID: '.$defect->item_id);
                $desc = sprintf(
                    'Menyelesaikan penanganan defect: %s (%s) | Item: %s | Qty: %s | Koli: %s',
                    $defect->code,
                    $defect->action,
                    $itemText,
                    rtrim(rtrim(number_format((float)$defect->quantity, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float)($defect->koli ?? 0), 2, '.', ''), '0'), '.')
                );
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'completed',
                    'menu' => 'Barang Rusak',
                    'description' => $desc,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            } catch (\Throwable $e) { }

            DB::commit();
            return redirect()->back()->with('success', 'Tindakan penanganan diselesaikan.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menyelesaikan tindakan: ' . $e->getMessage());
        }
    }

    public function getStatusCounts(Request $request)
    {
        $query = Defect::query();
        if (!is_null(auth()->user()->warehouse_id)) {
            $query->where('warehouse_id', auth()->user()->warehouse_id);
        }

        $data = [
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'total' => Defect::count(),
        ];

        return response()->json($data);
    }

    private function generateUniqueCode()
    {
        $prefix = 'DFCT-' . date('Ymd');
        $lastRecord = Defect::where('code', 'like', $prefix . '%')->orderBy('code', 'desc')->first();
        $number = 1;
        if ($lastRecord) {
            $number = ((int)substr($lastRecord->code, -4)) + 1;
        }
        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function stockInfo(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'item_id' => 'required|exists:items,id',
        ]);

        $inventory = Inventory::where('warehouse_id', $request->warehouse_id)
            ->where('item_id', $request->item_id)
            ->first();

        $item = Item::select('id','koli')->find($request->item_id);
        $perKoli = max(1, (int)($item->koli ?? 1));

        $qty = (float)($inventory->quantity ?? 0);
        $koli = $perKoli > 0 ? round($qty / $perKoli, 2) : 0;

        return response()->json([
            'quantity' => $qty,
            'koli' => (float)($inventory->koli ?? 0),
            'per_koli' => $perKoli,
            'computed_koli_from_qty' => $koli,
        ]);
    }
}
