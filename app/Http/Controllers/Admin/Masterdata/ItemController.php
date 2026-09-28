<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Uom;
use App\Models\ItemCategory;
use App\Models\UserActivity;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $itemcategories = ItemCategory::all();
        if($request->get('category_id') !== 'semua') {
            $selected_category_id = $request->get('category_id');
        } else {
            $selected_category_id = null;
        }

        // Data tabel akan dimuat via DataTables server-side (AJAX)
        return view('admin.masterdata.items.index', compact('itemcategories', 'selected_category_id'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $uoms = Uom::all();
        $itemcategories = ItemCategory::all();
        return view('admin.masterdata.items.create', compact('uoms', 'itemcategories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'koli' => 'required|integer',
            'sku' => 'required|string|unique:items,sku',
            'uom_id' => 'required|exists:uoms,id',
            'item_category_id' => 'required|exists:item_categories,id|not_in:null',
            'nama_barang' => 'required|string',
            'tempat' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'lead_time_po_days' => 'nullable|integer|min:0',
            'lead_time_safety_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ] + $this->dimensionRules(), $this->dimensionMessages());

        DB::beginTransaction();
        try {
            $data = $request->all();
            // Default low stock threshold to 100 if not provided
            $data['low_stock_threshold'] = $request->input('low_stock_threshold', 100);
            // Default lead times if not provided
            $data['lead_time_po_days'] = $request->input('lead_time_po_days', 7);
            $data['lead_time_safety_days'] = $request->input('lead_time_safety_days', 1);
            $data['is_active'] = $request->boolean('is_active', true);

            $item = Item::create($data);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'items',
                'description' => 'Menambahkan item baru: ' . $item->nama_barang . ' (SKU: ' . $item->sku . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.items.index')
                ->with('success', 'Item created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal membuat item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $item = Item::with('uom')->findOrFail($id);
        return view('admin.masterdata.items.show', compact('item'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $item = Item::findOrFail($id);
        $uoms = Uom::all();
        $itemcategories = ItemCategory::all();
        return view('admin.masterdata.items.edit', compact('item', 'uoms', 'itemcategories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $item = Item::findOrFail($id);

        $request->validate([
            'koli' => 'required|integer',
            'sku' => 'required|string|unique:items,sku,' . $item->id,
            'uom_id' => 'required|exists:uoms,id',
            'item_category_id' => 'required|exists:item_categories,id|not_in:null',
            'nama_barang' => 'required|string',
            'tempat' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'lead_time_po_days' => 'nullable|integer|min:0',
            'lead_time_safety_days' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ] + $this->dimensionRules(), $this->dimensionMessages());

        DB::beginTransaction();
        try {
            $payload = $request->all();
            if (!$request->has('low_stock_threshold')) {
                // keep existing when field not present
                $payload['low_stock_threshold'] = $item->low_stock_threshold;
            }
            if (!$request->has('lead_time_po_days')) {
                $payload['lead_time_po_days'] = $item->lead_time_po_days;
            }
            if (!$request->has('lead_time_safety_days')) {
                $payload['lead_time_safety_days'] = $item->lead_time_safety_days;
            }
            $payload['is_active'] = $request->boolean('is_active', $item->is_active);
            $item->update($payload);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'items',
                'description' => 'Memperbarui item: ' . $item->nama_barang . ' (SKU: ' . $item->sku . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.items.index')
                ->with('success', 'Item updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal memperbarui item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $item = Item::findOrFail($id);
            $itemName = $item->nama_barang;
            $itemSku = $item->sku;
            $item->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'items',
                'description' => 'Menghapus item: ' . $itemName . ' (SKU: ' . $itemSku . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();

            return redirect()->route('admin.masterdata.items.index')
                ->with('success', 'Item deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus item: ' . $e->getMessage());
        }
    }

    /**
     * Import items from uploaded CSV file.
     * Expected columns:
     * A: SKU
     * B: Nama Barang
     * C: Koli (default 10)
     * D: Low Stock Threshold (default 100)
     * E: Item Category ID (default 1)
     * F: UOM ID (default 1)
     * G: Initial Quantity (optional) -> create/update Inventory + StockMovement
     * H: Warehouse ID for initial qty (optional)
     * I: Tempat (optional)
     * J, K, L: Panjang, Lebar, Tinggi per koli dalam cm (optional, untuk CBM)
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        $file = $request->file('csv_file');

        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file CSV.');
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNumber++;
                // skip empty rows
                if (count($row) === 1 && trim($row[0]) === '') {
                    continue;
                }

                // Map columns
                $sku = isset($row[0]) ? trim($row[0]) : null;
                $nama_barang = isset($row[1]) ? trim($row[1]) : null;
                $koli = isset($row[2]) && $row[2] !== '' ? (int)$row[2] : 10;
                $low_stock = isset($row[3]) && $row[3] !== '' ? (int)$row[3] : 100;
                $item_category_id = isset($row[4]) && $row[4] !== '' ? (int)$row[4] : 1;
                $uom_id = isset($row[5]) && $row[5] !== '' ? (int)$row[5] : 1;
                $init_qty = isset($row[6]) && $row[6] !== '' ? (float)$row[6] : null; // G
                $init_warehouse_id = isset($row[7]) && $row[7] !== '' ? (int)$row[7] : null; // H
                $tempat = isset($row[8]) && $row[8] !== '' ? trim((string)$row[8]) : null; // I (optional)
                if ($tempat !== null && $tempat !== '') {
                    $tempat = mb_substr($tempat, 0, 255);
                } else {
                    $tempat = null;
                }
                // J, K, L (opsional): dimensi per koli dalam cm, disimpan hanya jika ketiganya valid
                $dimensions = [
                    'panjang_koli' => $this->parseCsvDimension($row[9] ?? ''),
                    'lebar_koli' => $this->parseCsvDimension($row[10] ?? ''),
                    'tinggi_koli' => $this->parseCsvDimension($row[11] ?? ''),
                ];
                $filledDimensions = count(array_filter($dimensions, fn ($v) => $v !== null));
                if ($filledDimensions > 0 && $filledDimensions < 3) {
                    $errors[] = "Baris {$rowNumber}: dimensi koli (J/K/L) tidak lengkap atau tidak valid; dimensi diabaikan.";
                }
                if ($filledDimensions < 3) {
                    $dimensions = array_fill_keys(array_keys($dimensions), null);
                }

                // Basic validation
                if (!$sku || !$nama_barang) {
                    $errors[] = "Baris {$rowNumber}: SKU dan Nama Barang wajib.";
                    $skipped++;
                    continue;
                }

                // Check SKU uniqueness
                if (Item::where('sku', $sku)->exists()) {
                    $errors[] = "Baris {$rowNumber}: SKU '{$sku}' sudah ada, dilewati.";
                    $skipped++;
                    continue;
                }

                // Validate foreign keys existence; fallback to defaults if not found
                if (!\App\Models\Uom::where('id', $uom_id)->exists()) {
                    $uom_id = 1;
                }
                if (!\App\Models\ItemCategory::where('id', $item_category_id)->exists()) {
                    $item_category_id = 1;
                }

                // Create item
                $item = Item::create([
                    'sku' => $sku,
                    'nama_barang' => $nama_barang,
                    'koli' => $koli,
                    'low_stock_threshold' => $low_stock,
                    'item_category_id' => $item_category_id,
                    'uom_id' => $uom_id,
                    'tempat' => $tempat,
                    'is_active' => true,
                ] + $dimensions);

                $created++;

                // Optional: seed initial inventory and stock movement if G and H provided
                if (!is_null($init_qty) && !is_null($init_warehouse_id)) {
                    if (Warehouse::where('id', $init_warehouse_id)->exists()) {
                        $inv = Inventory::firstOrCreate(
                            ['warehouse_id' => $init_warehouse_id, 'item_id' => $item->id],
                            ['quantity' => 0, 'koli' => 0]
                        );

                        $beforeQty = (float) ($inv->quantity ?? 0);
                        $beforeKoli = (float) ($inv->koli ?? 0);
                        $perKoli = (float) ($item->koli ?: 1);
                        if ($perKoli <= 0) { $perKoli = 1; }
                        $targetQty = (float) $init_qty;
                        $targetKoli = (float) ($targetQty / $perKoli);
                        $deltaQty = $targetQty - $beforeQty;
                        $deltaKoli = $targetKoli - $beforeKoli;

                        if ($deltaQty != 0 || $deltaKoli != 0) {
                            StockMovement::create([
                                'warehouse_id' => $init_warehouse_id,
                                'item_id' => $item->id,
                                'date' => now()->toDateString(),
                                'quantity' => $deltaQty,
                                'koli' => $deltaKoli,
                                'stock_before' => $beforeQty,
                                'stock_after' => $targetQty,
                                'type' => $deltaQty >= 0 ? 'stock_in' : 'stock_out',
                                'description' => 'Saldo awal ' . now()->toDateString(),
                                'user_id' => Auth::id(),
                                'reference_id' => $item->id,
                                'reference_type' => 'items_import',
                            ]);

                            $inv->quantity = $targetQty;
                            $inv->koli = $targetKoli;
                            $inv->save();
                        }
                    } else {
                        $errors[] = "Baris {$rowNumber}: Warehouse ID '{$init_warehouse_id}' tidak ditemukan; stok awal diabaikan.";
                    }
                }
            }

            fclose($handle);

            if ($created > 0) {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'imported',
                    'menu' => 'items',
                    'description' => "Mengimpor {$created} item dari CSV",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            }

            DB::commit();

            $message = "Import selesai. {$created} dibuat, {$skipped} dilewati.";
            if (count($errors) > 0) {
                $message .= ' Beberapa catatan: ' . implode(' | ', array_slice($errors, 0, 5));
            }

            return redirect()->route('admin.masterdata.items.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return redirect()->back()->with('error', 'Gagal mengimpor CSV: ' . $e->getMessage());
        }
    }

    

    /**
     * Dimensi per koli (cm) untuk CBM: opsional, tetapi P, L, T harus diisi bersamaan.
     */
    protected function dimensionRules(): array
    {
        return [
            'panjang_koli' => 'nullable|numeric|gt:0|max:99999999.99|required_with:lebar_koli,tinggi_koli',
            'lebar_koli' => 'nullable|numeric|gt:0|max:99999999.99|required_with:panjang_koli,tinggi_koli',
            'tinggi_koli' => 'nullable|numeric|gt:0|max:99999999.99|required_with:panjang_koli,lebar_koli',
        ];
    }

    protected function dimensionMessages(): array
    {
        return [
            'panjang_koli.required_with' => 'Panjang koli wajib diisi jika lebar/tinggi diisi.',
            'lebar_koli.required_with' => 'Lebar koli wajib diisi jika panjang/tinggi diisi.',
            'tinggi_koli.required_with' => 'Tinggi koli wajib diisi jika panjang/lebar diisi.',
            'panjang_koli.gt' => 'Panjang koli harus lebih dari 0.',
            'lebar_koli.gt' => 'Lebar koli harus lebih dari 0.',
            'tinggi_koli.gt' => 'Tinggi koli harus lebih dari 0.',
        ];
    }

    /**
     * Angka dimensi dari CSV; menerima desimal koma ("12,5") maupun titik. Nilai <= 0 / tidak valid -> null.
     */
    protected function parseCsvDimension($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    public function checkSkuUniqueness(Request $request)
    {
        $sku = $request->input('sku');
        $itemId = $request->input('item_id');

        $query = Item::where('sku', $sku);

        if ($itemId) {
            $query->where('id', '!=', $itemId);
        }

        $isUnique = !$query->exists();

        return response()->json(['isUnique' => $isUnique]);
    }

    public function data(Request $request)
    {
        $draw = (int) $request->input('draw');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $searchValue = $request->input('search.value');
        $categoryId = $request->input('category_id');
        $status = $request->input('status');

        // Base query with relations
        $baseQuery = Item::query()->with(['uom', 'itemCategory']);

        // Total records
        $recordsTotal = (clone $baseQuery)->count();

        // Filtering
        if ($categoryId && $categoryId !== 'semua') {
            $baseQuery->where('item_category_id', $categoryId);
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $baseQuery->where('is_active', $status === 'active');
        }
        if ($searchValue) {
            $baseQuery->where(function ($q) use ($searchValue) {
                $q->where('sku', 'like', "%{$searchValue}%")
                    ->orWhere('nama_barang', 'like', "%{$searchValue}%")
                    ->orWhere('deskripsi', 'like', "%{$searchValue}%");
            });
        }

        // recordsFiltered after applying filters
        $recordsFiltered = (clone $baseQuery)->count();

        // Ordering
        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');

        // Map DataTables column index to database columns
        // 0: No (ignored), 1: Item/SKU, 2: status, 3: low_stock_threshold, 4: koli, 5: dimensi/CBM (ignored), 6: uom, 7: deskripsi, 8: actions (ignored)
        $orderableColumns = [
            1 => 'nama_barang',
            2 => 'is_active',
            3 => 'low_stock_threshold',
            4 => 'koli',
            6 => 'uom_id', // fallback; custom sort by relation name not applied here
            7 => 'deskripsi',
        ];
        if (isset($orderableColumns[$orderColumnIndex])) {
            $baseQuery->orderBy($orderableColumns[$orderColumnIndex], $orderDir === 'desc' ? 'desc' : 'asc');
        } else {
            $baseQuery->latest('id');
        }

        // Pagination (limit/offset)
        if ($length > 0) {
            $baseQuery->skip($start)->take($length);
        }

        $items = $baseQuery->get();

        // Transform rows for DataTables
        $data = [];
        $rowNumber = $start + 1;

        foreach ($items as $item) {
            $data[] = [
                'no' => $rowNumber++,
                'item_sku' => [
                    'sku' => $item->sku,
                    'nama_barang' => $item->nama_barang,
                ],
                'is_active' => (bool) $item->is_active,
                'low_stock_threshold' => $item->low_stock_threshold,
                'koli' => $item->koli,
                'panjang_koli' => $item->panjang_koli,
                'lebar_koli' => $item->lebar_koli,
                'tinggi_koli' => $item->tinggi_koli,
                'cbm_per_koli' => $item->cbm_per_koli,
                'uom' => optional($item->uom)->name,
                'deskripsi' => $item->deskripsi,
                'id' => $item->id,
            ];
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
