<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\StockMovement;
use App\Models\Uom;
use App\Models\Warehouse;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdjustmentController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        $latestAdjustment = Adjustment::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

        if ($latestAdjustment) {
            $sequence = (int) substr($latestAdjustment->code, -4) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $searchValue = $request->input('search.value', '');
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $draw = $request->input('draw', 0);
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');
            $warehouseFilter = $request->input('warehouse_id');

            // Define columns for sorting
            $columns = [
                0 => 'adj.code',
                1 => 'adj.adjustment_date',
                2 => 'warehouse_name',
                3 => 'items_name',
                4 => 'adj.status',
                5 => 'adj.id', // Actions column, not sortable
            ];
            $orderByColumnIndex = $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = $request->input('order.0.dir', 'asc');

            $query = Adjustment::query()->from('adjustments as adj')
                ->leftJoin('warehouses as w', 'adj.warehouse_id', '=', 'w.id')
                ->leftJoin('adjustment_items as ai', 'adj.id', '=', 'ai.adjustment_id')
                ->leftJoin('items as i', 'ai.item_id', '=', 'i.id');

            $totalRecordsQuery = Adjustment::query();
            if (Auth::user()->warehouse_id) {
                $totalRecordsQuery->where('warehouse_id', Auth::user()->warehouse_id);
                $query->where('adj.warehouse_id', Auth::user()->warehouse_id);
            } else {
                if ($warehouseFilter && $warehouseFilter !== 'semua') {
                    $query->where('adj.warehouse_id', $warehouseFilter);
                }
            }
            $totalRecords = $totalRecordsQuery->count();


            // Apply filters
            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('adj.code', 'LIKE', "%{$searchValue}%")
                        ->orWhere('adj.adjustment_date', 'LIKE', "%{$searchValue}%")
                        ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                        ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                        ->orWhere('adj.status', 'LIKE', "%{$searchValue}%");
                });
            }

            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('adj.status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $query->whereBetween('adj.adjustment_date', [$startDate, $endDate]);
                } else {
                    $query->whereDate('adj.adjustment_date', $dateFilter);
                }
            }

            // Total filtered records
            $totalFiltered = $query->distinct('adj.id')->count();


            // Data query
            $data = $query->select(
                'adj.id',
                'adj.code',
                'adj.adjustment_date',
                'adj.status',
                'w.name as warehouse_name',
                DB::raw('GROUP_CONCAT(i.sku, " (Qty:", FORMAT(ai.quantity,0), ")" SEPARATOR ", ") as items_name')
            )
            ->groupBy('adj.id', 'adj.code', 'adj.adjustment_date', 'adj.status', 'w.name')
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
        return view('admin.manajemenstok.adjustment.index', compact('warehouses'));
    }

    public function import(Request $request)
    {
        $rules = [
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'import_adjustment_date' => ['required', 'date'],
            'import_notes' => ['nullable', 'string'],
        ];

        if (!auth()->user()->warehouse_id) {
            $rules['import_warehouse_id'] = ['required', 'exists:warehouses,id'];
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'import_file' => 'file CSV',
            'import_adjustment_date' => 'tanggal penyesuaian',
            'import_warehouse_id' => 'gudang',
        ]);

        if ($validator->fails()) {
            return $this->adjustmentImportResponse($request, false, 'Validasi import gagal.', [
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        $validated = $validator->validated();
        $warehouseId = auth()->user()->warehouse_id ?? $validated['import_warehouse_id'];

        try {
            $csvItems = $this->parseAdjustmentCsv($request->file('import_file')->getRealPath());
        } catch (\RuntimeException $exception) {
            return $this->adjustmentImportResponse($request, false, $exception->getMessage());
        }

        if (empty($csvItems)) {
            return $this->adjustmentImportResponse($request, false, 'File CSV tidak memiliki baris SKU yang valid.');
        }

        $items = Item::whereIn('sku', array_keys($csvItems))->get()->keyBy('sku');
        $createdSkus = [];
        $differences = [];

        foreach ($csvItems as $sku => $finalQty) {
            $item = $items->get($sku);
            if (!$item) {
                $item = $this->createPlaceholderItem($sku);
                $items->put($sku, $item);
                $createdSkus[] = $sku;
            }

            $inventory = Inventory::firstOrNew([
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
            ]);

            $currentQty = (float) ($inventory->quantity ?? 0);
            $difference = (float) $finalQty - $currentQty;

            if (abs($difference) < 0.0001) {
                continue;
            }

            $perKoli = (float) ($item->koli ?? 1);
            if ($perKoli <= 0) {
                $perKoli = 1;
            }

            $differences[] = [
                'item' => $item,
                'quantity' => $difference,
                'koli' => round($difference / $perKoli, 2),
            ];
        }

        if (empty($differences)) {
            return $this->adjustmentImportResponse($request, false, 'Tidak ada perubahan stok berdasarkan CSV.');
        }

        $adjustmentCode = null;
        $itemCount = count($differences);

        DB::beginTransaction();
        try {
            $adjustment = Adjustment::create([
                'code' => $this->generateNewCode(),
                'adjustment_date' => $validated['import_adjustment_date'],
                'warehouse_id' => $warehouseId,
                'user_id' => auth()->id(),
                'notes' => $validated['import_notes'] ?? null,
                'status' => 'pending',
            ]);

            $payload = [];
            foreach ($differences as $data) {
                $payload[] = [
                    'item_id' => $data['item']->id,
                    'quantity' => $data['quantity'],
                    'koli' => $data['koli'],
                ];
            }

            $adjustment->adjustmentItems()->createMany($payload);
            $adjustmentCode = $adjustment->code;

            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Mengimpor penyesuaian stok (pending) dengan kode ' . $adjustment->code,
                'activity' => 'imported',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->adjustmentImportResponse($request, false, 'Gagal menyimpan data: ' . $e->getMessage());
        }

        $message = "Berhasil membuat penyesuaian pending ({$itemCount} item, kode {$adjustmentCode}).";
        if (!empty($createdSkus)) {
            $message .= ' SKU baru: ' . implode(', ', $createdSkus) . '.';
        }

        return $this->adjustmentImportResponse($request, true, $message, [
            'items_count' => $itemCount,
            'code' => $adjustmentCode,
            'created_skus' => $createdSkus,
        ]);
    }

    public function getStatusCounts(Request $request)
    {
        $dateFilter = $request->input('date');
        $warehouseFilter = $request->input('warehouse_id');

        $baseQuery = Adjustment::query();

        if (Auth::user()->warehouse_id) {
            $baseQuery->where('warehouse_id', Auth::user()->warehouse_id);
        } else {
            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $baseQuery->where('warehouse_id', $warehouseFilter);
            }
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            if (strpos($dateFilter, ' to ') !== false) {
                [$startDate, $endDate] = explode(' to ', $dateFilter);
                $baseQuery->whereBetween('adjustment_date', [$startDate, $endDate]);
            } else {
                $baseQuery->whereDate('adjustment_date', $dateFilter);
            }
        }

        $statusCounts = [
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
        ];

        return response()->json($statusCounts);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        $newCode = $this->generateNewCode();
        return view('admin.manajemenstok.adjustment.create', compact('warehouses', 'items', 'newCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:adjustments,code',
            'adjustment_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.koli' => 'nullable|numeric',
        ]);

        // Custom validation: negative quantities cannot exceed available stock in selected warehouse
        $errors = [];
        $warehouseId = (int) $request->warehouse_id;
        foreach ($request->items as $idx => $itemData) {
            $qty = (float) ($itemData['quantity'] ?? 0);
            if ($qty < 0) {
                $inv = Inventory::where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemData['item_id'])
                    ->first();
                $available = (float) ($inv->quantity ?? 0);
                if (abs($qty) > $available) {
                    $item = Item::find($itemData['item_id']);
                    $name = $item->nama_barang ?? ('ID: ' . $itemData['item_id']);
                    $errors["items.$idx.quantity"] = ["Qty minus melebihi stok ($available) untuk $name."];
                }
            }
        }
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $adjustment = Adjustment::create([
                'code' => $request->code,
                'adjustment_date' => $request->adjustment_date,
                'warehouse_id' => $request->warehouse_id,
                'user_id' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            foreach ($request->items as $itemData) {
                AdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'menu' => 'Penyesuaian Stok',
                'description' => 'Membuat penyesuaian stok baru dengan kode ' . $adjustment->code,
                'activity' => 'created',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('success', 'Penyesuaian stok berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal membuat penyesuaian stok: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function show(Adjustment $adjustment)
    {
        $adjustment->load('warehouse', 'adjustmentItems.item', 'user');
        return view('admin.manajemenstok.adjustment.show', compact('adjustment'));
    }

    public function edit(Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('error', 'Hanya penyesuaian dengan status pending yang dapat diubah.');
        }

        $adjustment->load('adjustmentItems.item');
        $warehouses = Warehouse::all();
        $items = Item::with('uom')->get();
        return view('admin.manajemenstok.adjustment.edit', compact('adjustment', 'warehouses', 'items'));
    }

    public function update(Request $request, Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('error', 'Hanya penyesuaian dengan status pending yang dapat diubah.');
        }

        $request->validate([
            'code' => 'required|unique:adjustments,code,' . $adjustment->id,
            'adjustment_date' => 'required|date',
            'warehouse_id' => 'required|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|distinct|exists:items,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.koli' => 'nullable|numeric',
        ]);

        // Custom validation: negative quantities cannot exceed available stock in selected warehouse
        $errors = [];
        $warehouseId = (int) $request->warehouse_id;
        foreach ($request->items as $idx => $itemData) {
            $qty = (float) ($itemData['quantity'] ?? 0);
            if ($qty < 0) {
                $inv = Inventory::where('warehouse_id', $warehouseId)
                    ->where('item_id', $itemData['item_id'])
                    ->first();
                $available = (float) ($inv->quantity ?? 0);
                if (abs($qty) > $available) {
                    $item = Item::find($itemData['item_id']);
                    $name = $item->nama_barang ?? ('ID: ' . $itemData['item_id']);
                    $errors["items.$idx.quantity"] = ["Qty minus melebihi stok ($available) untuk $name."];
                }
            }
        }
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {
            $adjustment->update([
                'code' => $request->code,
                'adjustment_date' => $request->adjustment_date,
                'warehouse_id' => $request->warehouse_id,
                'notes' => $request->notes
            ]);

            // Delete existing items
            $adjustment->adjustmentItems()->delete();

            // Create new items
            foreach ($request->items as $itemData) {
                AdjustmentItem::create([
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'] ?? null,
                ]);
            }

            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Mengubah penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'updated',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('success', 'Penyesuaian stok berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Gagal memperbarui penyesuaian stok: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penyesuaian dengan status pending yang dapat dihapus.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Menghapus penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'deleted',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $adjustment->adjustmentItems()->delete();
            $adjustment->delete();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Penyesuaian stok berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penyesuaian stok: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, Adjustment $adjustment)
    {
        if ($adjustment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Status penyesuaian sudah diubah sebelumnya.'
            ], 422);
        }

        $request->validate([
            'status' => 'required|in:completed',
        ]);

        DB::beginTransaction();
        try {
            $adjustment->load('adjustmentItems.item');

            foreach ($adjustment->adjustmentItems as $item) {
                $inventory = Inventory::firstOrNew([
                    'warehouse_id' => $adjustment->warehouse_id,
                    'item_id' => $item->item_id
                ]);

                if (!$inventory->exists && $item->quantity < 0) {
                    throw new \Exception("Tidak dapat mengurangi stok. Stok tidak ditemukan untuk item: " . ($item->item->nama_barang ?? 'N/A'));
                }

                StockMovement::create([
                    'warehouse_id' => $adjustment->warehouse_id,
                    'item_id' => $item->item_id,
                    'date' => $adjustment->adjustment_date,
                    'quantity' => $item->quantity,
                    'koli' => $item->koli ?? 0,
                    'type' => $item->quantity >= 0 ? 'stock_in' : 'stock_out',
                    'stock_before' => $inventory->quantity ?? 0,
                    'stock_after' => ($inventory->quantity ?? 0) + $item->quantity,
                    'description' => 'Penyesuaian stok: ' . $adjustment->code,
                    'user_id' => auth()->id(),
                    'reference_id' => $item->id,
                    'reference_type' => 'adjustment_items'
                ]);

                $inventory->quantity = ($inventory->quantity ?? 0) + $item->quantity;
                $inventory->koli = ($inventory->koli ?? 0) + ($item->koli ?? 0);
                $inventory->save();
            }

            $adjustment->status = 'completed';
            $adjustment->completed_at = now();
            $adjustment->save();

            UserActivity::create([
                'user_id' => auth()->id(),
                'description' => 'Menyelesaikan penyesuaian stok dengan kode ' . $adjustment->code,
                'activity' => 'approved',
                'menu' => 'Penyesuaian Stok',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Status penyesuaian berhasil diperbarui.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseAdjustmentCsv(string $path): array
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('File CSV tidak ditemukan.');
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new \RuntimeException('File CSV tidak dapat dibuka.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new \RuntimeException('File CSV kosong.');
        }

        $firstLine = $this->stripBom($firstLine);
        $delimiter = $this->detectDelimiter($firstLine);
        $headers = array_map(fn ($value) => strtolower(trim($value)), str_getcsv($firstLine, $delimiter));

        $skuIndex = array_search('sku', $headers);
        $qtyIndex = array_search('qty', $headers);

        if ($skuIndex === false || $qtyIndex === false) {
            fclose($handle);
            throw new \RuntimeException('Header CSV harus memuat kolom "SKU" dan "qty".');
        }

        $result = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($this->isEmptyCsvRow($row)) {
                continue;
            }

            $sku = trim($row[$skuIndex] ?? '');
            $rawQty = trim($row[$qtyIndex] ?? '');

            if ($sku === '') {
                continue;
            }

            if ($rawQty === '') {
                fclose($handle);
                throw new \RuntimeException("Qty kosong pada SKU {$sku} (baris {$lineNumber}).");
            }

            $quantity = $this->normalizeImportNumber($rawQty);

            if ($quantity < 0) {
                fclose($handle);
                throw new \RuntimeException("Qty pada SKU {$sku} (baris {$lineNumber}) tidak boleh minus karena mewakili stok akhir.");
            }

            if (array_key_exists($sku, $result)) {
                fclose($handle);
                throw new \RuntimeException("SKU {$sku} muncul lebih dari sekali (baris {$lineNumber}). Gunakan satu baris per SKU.");
            }

            $result[$sku] = $quantity;
        }

        fclose($handle);

        return $result;
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
    }

    private function detectDelimiter(string $line): string
    {
        $semicolonCount = substr_count($line, ';');
        $commaCount = substr_count($line, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeImportNumber(string $value): float
    {
        $value = str_replace(["\xC2\xA0", ' ', "\t"], '', trim($value));

        if ($value === '') {
            return 0;
        }

        $commaPos = strrpos($value, ',');
        $dotPos = strrpos($value, '.');

        if ($commaPos !== false && ($dotPos === false || $commaPos > $dotPos)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function adjustmentImportResponse(Request $request, bool $success, string $message, array $payload = [])
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $payload), $success ? 200 : 422);
        }

        if ($success) {
            return redirect()
                ->route('admin.manajemenstok.adjustment.index')
                ->with('success', $message);
        }

        $errors = $payload['errors'] ?? ['import_file' => $message];

        return redirect()
            ->back()
            ->withErrors($errors)
            ->withInput()
            ->with('showAdjustmentImportModal', true);
    }

    private function createPlaceholderItem(string $sku): Item
    {
        $uomId = $this->getDefaultUomId();
        $itemTypeId = $this->getDefaultItemTypeId();

        return Item::create([
            'sku' => $sku,
            'nama_barang' => 'Belum Ada Nama Item',
            'koli' => 1,
            'uom_id' => $uomId,
            'item_type_id' => $itemTypeId,
            'item_category_id' => null,
            'low_stock_threshold' => 0,
            'lead_time_po_days' => 0,
            'lead_time_safety_days' => 0,
            'tempat' => null,
            'deskripsi' => 'Item hasil import penyesuaian stok',
        ]);
    }

    private function getDefaultUomId(): int
    {
        $uom = Uom::first();
        if (!$uom) {
            $uom = Uom::create([
                'name' => 'Unit',
                'symbol' => 'UNIT',
                'description' => 'UOM otomatis untuk import penyesuaian',
            ]);
        }

        return $uom->id;
    }

    private function getDefaultItemTypeId(): int
    {
        $itemType = ItemType::first();
        if (!$itemType) {
            $itemType = ItemType::create([
                'name' => 'Default',
                'description' => 'Item type otomatis untuk import penyesuaian',
            ]);
        }

        return $itemType->id;
    }
}

