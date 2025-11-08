<?php

namespace App\Http\Controllers\Admin\StokKeluar;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengeluaranBarangController extends Controller
{
    private function generateNewCode()
    {
        $prefix = 'OUT';
        $date = now()->format('Ymd');
        $latestOrder = StockOut::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();

        if ($latestOrder) {
            $sequence = (int) substr($latestOrder->code, -4) + 1;
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
            $warehouseFilter = $request->input('warehouse_id');
            $statusFilter = $request->input('status');
            $dateFilter = $request->input('date');

            $query = StockOut::with(['warehouse', 'user'])
                ->select('stock_outs.*')
                ->addSelect(DB::raw("(
                    SELECT GROUP_CONCAT(CONCAT(items.sku, ' (', FORMAT(soi.quantity,0), ')') SEPARATOR ', ')
                    FROM stock_out_items soi
                    JOIN items ON items.id = soi.item_id
                    WHERE soi.stock_out_id = stock_outs.id
                ) AS items_list"));

            $userWarehouseId = auth()->user()->warehouse_id;
            if ($userWarehouseId) {
                $query->where('warehouse_id', $userWarehouseId);
            } else {
                if ($warehouseFilter && $warehouseFilter !== 'semua') {
                    $query->where('warehouse_id', $warehouseFilter);
                }
            }

            $totalRecords = $query->count();

            if (!empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'LIKE', "%{$searchValue}%")
                      ->orWhereHas('warehouse', function ($subQuery) use ($searchValue) {
                          $subQuery->where('name', 'LIKE', "%{$searchValue}%");
                      })
                      ->orWhereHas('user', function ($subQuery) use ($searchValue) {
                          $subQuery->where('name', 'LIKE', "%{$searchValue}%");
                      });
                });
            }
            
            if ($statusFilter && $statusFilter !== 'semua') {
                $query->where('status', $statusFilter);
            }

            if ($dateFilter && $dateFilter !== 'semua') {
                $query->whereDate('date', $dateFilter);
            }

            $totalFiltered = $query->count();

            $data = $query->latest()->offset($start)->limit($length)->get();

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => intval($totalRecords),
                'recordsFiltered' => intval($totalFiltered),
                'data' => $data,
            ]);
        }

        $userWarehouseId = auth()->user()->warehouse_id;

        $warehouses = $userWarehouseId ? collect() : Warehouse::all();
        return view('admin.stok_keluar.index', compact('warehouses'));
    }

    public function getStatusCounts(Request $request)
    {
        $warehouseFilter = $request->input('warehouse_id');
        $dateFilter = $request->input('date');

        $pendingCountQuery = StockOut::where('status', 'pending');
        $completedCountQuery = StockOut::where('status', 'completed');

        $userWarehouseId = auth()->user()->warehouse_id;
        if ($userWarehouseId) {
            $pendingCountQuery->where('warehouse_id', $userWarehouseId);
            $completedCountQuery->where('warehouse_id', $userWarehouseId);
        } else {
            if ($warehouseFilter && $warehouseFilter !== 'semua') {
                $pendingCountQuery->where('warehouse_id', $warehouseFilter);
                $completedCountQuery->where('warehouse_id', $warehouseFilter);
            }
        }

        if ($dateFilter && $dateFilter !== 'semua') {
            $pendingCountQuery->whereDate('date', $dateFilter);
            $completedCountQuery->whereDate('date', $dateFilter);
        }

        $pendingCount = $pendingCountQuery->count();
        $completedCount = $completedCountQuery->count();

        return response()->json([
            'pending' => $pendingCount,
            'completed' => $completedCount,
        ]);
    }

    public function create()
    {
        $warehouses = Warehouse::all();
        $inventory = Inventory::with(['item.uom', 'item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')
                                   ->from('stock_out_items');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');
        $newCode = $this->generateNewCode();
        return view('admin.stok_keluar.create', compact('warehouses', 'inventory', 'newCode'));
    }

    public function store(Request $request)
    {
        // dd($request->all());
                $request->validate([
            'code' => 'required|string|unique:stock_outs,code',
            'warehouse_id' => 'required|exists:warehouses,id',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.koli' => 'required|min:0',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:1',
                function ($attribute, $value, $fail) use ($request) {
                    $index = explode('.', $attribute)[1];
                    $itemId = $request->input("items.$index.item_id");
                    $warehouseId = $request->input('warehouse_id');

                    $inventory = Inventory::where('warehouse_id', $warehouseId)
                                        ->where('item_id', $itemId)
                                        ->first();

                    if (!$inventory || $inventory->quantity < $value) {
                        $fail('tidak ada item digudang');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($request) {
            $stockOut = StockOut::create([
                'code' => $request->code,
                'warehouse_id' => $request->warehouse_id,
                'user_id' => auth()->id(),
                'date' => $request->date,
                'created_by' => auth()->id(),
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            $itemsPayload = collect($request->items)->map(function ($itemData) {
                return [
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'],
                ];
            })->toArray();

            $stockOut->items()->createMany($itemsPayload);

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'created',
                'menu' => 'Stok Keluar',
                'description' => 'Membuat draft pengeluaran barang dengan kode ' . $stockOut->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Data pengeluaran barang berhasil disimpan.');
    }

    public function import(Request $request)
    {
        $rules = [
            'import_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'import_date' => ['required', 'date'],
            'import_notes' => ['nullable', 'string'],
        ];

        if (!auth()->user()->warehouse_id) {
            $rules['import_warehouse_id'] = ['required', 'exists:warehouses,id'];
        }

        $validator = Validator::make($request->all(), $rules, [], [
            'import_file' => 'file CSV',
            'import_date' => 'tanggal pengeluaran',
            'import_warehouse_id' => 'gudang',
        ]);

        if ($validator->fails()) {
            return $this->importResponse($request, false, 'Validasi import gagal.', [
                'errors' => $validator->errors()->toArray(),
            ]);
        }

        $validated = $validator->validated();
        $warehouseId = auth()->user()->warehouse_id ?? $validated['import_warehouse_id'];

        try {
            $csvItems = $this->parsePendingStockOutCsv($request->file('import_file')->getRealPath());
        } catch (\RuntimeException $exception) {
            return $this->importResponse($request, false, $exception->getMessage());
        }

        if (empty($csvItems)) {
            return $this->importResponse($request, false, 'File CSV tidak memiliki baris SKU yang valid.');
        }

        $items = Item::whereIn('sku', array_keys($csvItems))->get()->keyBy('sku');
        $missingSkus = array_values(array_diff(array_keys($csvItems), $items->keys()->all()));

        if ($items->isEmpty()) {
            return $this->importResponse(
                $request,
                false,
                'Tidak ada SKU valid yang ditemukan pada sistem.'
            );
        }

        $stockOutCode = null;
        $itemCount = 0;
        $skippedSkus = $missingSkus;

        DB::transaction(function () use ($csvItems, $items, $warehouseId, $validated, &$stockOutCode, &$itemCount, $request) {
            $stockOut = StockOut::create([
                'code' => $this->generateNewCode(),
                'warehouse_id' => $warehouseId,
                'date' => $validated['import_date'],
                'created_by' => auth()->id(),
                'user_id' => auth()->id(),
                'notes' => $validated['import_notes'] ?? null,
                'status' => 'pending',
            ]);

            $grouped = [];
            foreach ($csvItems as $sku => $quantity) {
                $item = $items->get($sku);
                if (!$item) {
                    continue;
                }
                if (!isset($grouped[$item->id])) {
                    $grouped[$item->id] = [
                        'item' => $item,
                        'quantity' => 0,
                    ];
                }
                $grouped[$item->id]['quantity'] += $quantity;
            }

            $payload = [];
            foreach ($grouped as $data) {
                $item = $data['item'];
                $qty = $data['quantity'];
                $perKoli = max(1, (float) ($item->koli ?? 1));
                $payload[] = [
                    'item_id' => $item->id,
                    'quantity' => $qty,
                    'koli' => round($qty / $perKoli, 2),
                ];
            }


            $stockOut->items()->createMany($payload);

            $itemCount = count($payload);
            $stockOutCode = $stockOut->code;

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'imported',
                'menu' => 'Stok Keluar',
                'description' => 'Mengimpor pengeluaran barang pending dengan kode ' . $stockOut->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
            ]);
        });

        $message = "Berhasil membuat {$itemCount} item pengeluaran pending melalui import (kode {$stockOutCode}).";
        if (!empty($skippedSkus)) {
            $message .= ' SKU dilewati: ' . implode(', ', $skippedSkus);
        }

        return $this->importResponse($request, true, $message, [
            'stock_out_code' => $stockOutCode,
            'items_count' => $itemCount,
            'skipped_skus' => $skippedSkus,
        ]);
    }

    public function show(StockOut $pengeluaranBarang)
    {
        $pengeluaranBarang->load(['warehouse', 'user', 'items.item']);
        return view('admin.stok_keluar.show', compact('pengeluaranBarang'));
    }

    public function edit(StockOut $pengeluaranBarang)
    {
        $warehouses = Warehouse::all();
        $inventory = Inventory::with(['item.uom', 'item'])
            ->where('quantity', '>', 0)
            ->orWhere(function ($query) {
                $query->where('quantity', 0)
                      ->whereIn('item_id', function ($subQuery) {
                          $subQuery->select('item_id')
                                   ->from('stock_out_items');
                      });
            })
            ->get()
            ->groupBy('warehouse_id');

        $pengeluaranBarang->load('items.item');

        $warehouseInventory = $inventory->get($pengeluaranBarang->warehouse_id, collect());
        $needsUpdate = false;

        foreach ($pengeluaranBarang->items as $stockOutItem) {
            $existing = $warehouseInventory->firstWhere('item_id', $stockOutItem->item_id);
            if (!$existing) {
                $mockInventory = new Inventory([
                    'warehouse_id' => $pengeluaranBarang->warehouse_id,
                    'item_id' => $stockOutItem->item_id,
                    'quantity' => 0,
                    'koli' => 0,
                ]);
                $mockInventory->setRelation('item', $stockOutItem->item);
                $warehouseInventory->push($mockInventory);
                $needsUpdate = true;
            }
        }

        if ($needsUpdate || !$inventory->has($pengeluaranBarang->warehouse_id)) {
            $inventory->put($pengeluaranBarang->warehouse_id, $warehouseInventory);
        }

        return view('admin.stok_keluar.edit', compact('pengeluaranBarang', 'warehouses', 'inventory'));
    }

    public function update(Request $request, StockOut $pengeluaranBarang)
    {
        if ($pengeluaranBarang->status !== 'pending') {
            return redirect()->back()->with('error', 'Pengeluaran barang yang sudah dikonfirmasi tidak dapat diedit.');
        }

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.koli' => 'required|numeric|min:0',
            'items.*.quantity' => [
                'required',
                'numeric',
                'min:1',
            ],
        ]);

        DB::transaction(function () use ($request, $pengeluaranBarang) {
            $pengeluaranBarang->items()->delete();

            $pengeluaranBarang->update([
                'warehouse_id' => $request->warehouse_id,
                'date' => $request->date,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            $itemsPayload = collect($request->items)->map(function ($itemData) {
                return [
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'koli' => $itemData['koli'],
                ];
            })->toArray();

            $pengeluaranBarang->items()->createMany($itemsPayload);

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'updated',
                'menu' => 'Stok Keluar',
                'description' => 'Memperbarui draft pengeluaran barang dengan kode ' . $pengeluaranBarang->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Draft pengeluaran barang berhasil diperbarui.');
    }

    public function confirm(Request $request, StockOut $pengeluaranBarang)
    {
        if ($pengeluaranBarang->status !== 'pending') {
            return redirect()->back()->with('error', 'Dokumen ini sudah diproses.');
        }

        $pengeluaranBarang->loadMissing('items.item');

        try {
            DB::transaction(function () use ($pengeluaranBarang, $request) {
                foreach ($pengeluaranBarang->items as $item) {
                    $inventory = Inventory::where('warehouse_id', $pengeluaranBarang->warehouse_id)
                        ->where('item_id', $item->item_id)
                        ->lockForUpdate()
                        ->first();

                    $availableQty = $inventory ? $inventory->quantity : 0;
                    if (!$inventory || $availableQty < $item->quantity) {
                        $sku = $item->item->sku ?? '-';
                        throw ValidationException::withMessages([
                            'inventory' => "Stok untuk SKU {$sku} tidak mencukupi (tersedia {$availableQty}).",
                        ]);
                    }

                    $stockBefore = $inventory->quantity;
                    $inventory->decrement('quantity', $item->quantity);
                    $inventory->decrement('koli', $item->koli);
                    $stockAfter = $inventory->quantity;

                    StockMovement::create([
                        'item_id' => $item->item_id,
                        'warehouse_id' => $pengeluaranBarang->warehouse_id,
                        'quantity' => -$item->quantity,
                        'koli' => -$item->koli,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'type' => 'stock_out',
                        'description' => 'Konfirmasi Pengeluaran Barang',
                        'reference_id' => $item->id,
                        'reference_type' => 'stock_out_items',
                        'user_id' => auth()->id(),
                        'date' => $pengeluaranBarang->date,
                    ]);
                }

                $pengeluaranBarang->update(['status' => 'completed']);

                UserActivity::create([
                    'user_id' => auth()->id(),
                    'activity' => 'confirmed',
                    'menu' => 'Stok Keluar',
                    'description' => 'Mengonfirmasi pengeluaran barang dengan kode ' . $pengeluaranBarang->code,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('user-agent'),
                ]);
            });
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message ?? 'Stok tidak mencukupi untuk dikonfirmasi.',
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->with('error', $message ?? 'Stok tidak mencukupi untuk dikonfirmasi.');
        }

        $successMessage = 'Pengeluaran barang berhasil dikonfirmasi.';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', $successMessage);
    }

    public function destroy(StockOut $pengeluaranBarang)
    {
        DB::transaction(function () use ($pengeluaranBarang) {
            if ($pengeluaranBarang->status === 'completed') {
                foreach ($pengeluaranBarang->items as $item) {
                    $inventory = Inventory::where('warehouse_id', $pengeluaranBarang->warehouse_id)
                             ->where('item_id', $item->item_id)->first();
                    
                    $stockBefore = $inventory->quantity;
                    $inventory->increment('quantity', $item->quantity);
                    // Return koli back to inventory when deleting stock out
                    $inventory->increment('koli', $item->koli);
                    $stockAfter = $inventory->quantity;

                    StockMovement::create([
                        'item_id' => $item->item_id,
                        'warehouse_id' => $pengeluaranBarang->warehouse_id,
                        'quantity' => $item->quantity,
                        'koli' => $item->koli,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'type' => 'stock_out',
                        'description' => 'Hapus Pengeluaran Barang',
                        'reference_id' => $item->id,
                        'reference_type' => 'stock_out_items',
                        'user_id' => auth()->id(),
                        'date' => $pengeluaranBarang->date,
                    ]);
                }
            }

            $pengeluaranBarang->items()->delete();
            $pengeluaranBarang->delete();

            UserActivity::create([
                'user_id' => auth()->id(),
                'activity' => 'deleted',
                'menu' => 'Stok Keluar',
                'description' => 'Menghapus data pengeluaran barang dengan kode ' . $pengeluaranBarang->code,
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('user-agent'),
            ]);
        });

        return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')->with('success', 'Data pengeluaran barang berhasil dihapus.');
    }

    private function parsePendingStockOutCsv(string $path): array
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
        $delimiter = ';';

        $headers = array_map(fn ($value) => strtolower(trim($value)), str_getcsv($firstLine, $delimiter));
        $skuIndex = array_search('sku', $headers);
        $qtyIndex = array_search('jumlah', $headers);

        if ($skuIndex === false || $qtyIndex === false) {
            fclose($handle);
            throw new \RuntimeException('Header CSV harus memuat kolom "SKU" dan "Jumlah".');
        }

        $lineNumber = 1;
        $result = [];

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
                throw new \RuntimeException("Jumlah kosong untuk SKU {$sku} (baris {$lineNumber}).");
            }

            $quantity = $this->normalizeNumericValue($rawQty);
            if ($quantity <= 0) {
                fclose($handle);
                throw new \RuntimeException("Jumlah untuk SKU {$sku} pada baris {$lineNumber} harus lebih besar dari 0.");
            }

            $result[$sku] = ($result[$sku] ?? 0) + $quantity;
        }

        fclose($handle);

        if (empty($result)) {
            throw new \RuntimeException('Tidak ada baris item yang dapat diproses dari file CSV.');
        }

        return $result;
    }

    private function stripBom(string $line): string
    {
        if (str_starts_with($line, "\xEF\xBB\xBF")) {
            return substr($line, 3);
        }

        return $line;
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

    private function normalizeNumericValue(string $value): float
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

    private function importResponse(Request $request, bool $success, string $message, array $payload = [])
    {
        if ($request->ajax() || $request->wantsJson()) {
            $status = $success ? 200 : 422;
            return response()->json(array_merge([
                'success' => $success,
                'message' => $message,
            ], $payload), $status);
        }

        if ($success) {
            return redirect()->route('admin.stok-keluar.pengeluaran-barang.index')
                ->with('success', $message);
        }

        $errors = [];
        if (!empty($payload['errors']) && is_array($payload['errors'])) {
            $errors = $payload['errors'];
        } else {
            $errors = ['import_file' => $message];
        }

        return redirect()->back()
            ->withErrors($errors)
            ->withInput()
            ->with('showImportModal', true);
    }
}
