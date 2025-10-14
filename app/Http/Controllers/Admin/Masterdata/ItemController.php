<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Uom;
use App\Models\ItemCategory;
use App\Models\UserActivity;
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
            'deskripsi' => 'nullable|string',
            'low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $data = $request->all();
            // Default low stock threshold to 100 if not provided
            $data['low_stock_threshold'] = $request->input('low_stock_threshold', 100);

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
            'deskripsi' => 'nullable|string',
            'low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $payload = $request->all();
            if (!$request->has('low_stock_threshold')) {
                // keep existing when field not present
                $payload['low_stock_threshold'] = $item->low_stock_threshold;
            }
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
                ]);

                $created++;
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

        // Base query with relations
        $baseQuery = Item::query()->with(['uom', 'itemCategory']);

        // Total records
        $recordsTotal = (clone $baseQuery)->count();

        // Filtering
        if ($categoryId && $categoryId !== 'semua') {
            $baseQuery->where('item_category_id', $categoryId);
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
        // 0: No (ignored), 1: Item/SKU, 2: low_stock_threshold, 3: koli, 4: uom, 5: deskripsi, 6: actions (ignored)
        $orderableColumns = [
            1 => 'nama_barang',
            2 => 'low_stock_threshold',
            3 => 'koli',
            4 => 'uom_id', // fallback; custom sort by relation name not applied here
            5 => 'deskripsi',
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
                'low_stock_threshold' => $item->low_stock_threshold,
                'koli' => $item->koli,
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
