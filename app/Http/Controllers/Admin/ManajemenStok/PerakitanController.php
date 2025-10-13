<?php

namespace App\Http\Controllers\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\Assembly;
use App\Models\AssemblyComponent;
use App\Models\AssemblyRecipe;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\UserActivity;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PerakitanController extends Controller
{
    private function generateCode(): string
    {
        $prefix = 'ASM';
        $date = now()->format('Ymd');
        $latest = Assembly::where('code', 'LIKE', "$prefix-$date-%")->latest('id')->first();
        $seq = 1;
        if ($latest) {
            $seq = ((int) substr($latest->code, -4)) + 1;
        }
        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }

    public function index(Request $request)
    {
        // DataTables server-side
        if ($request->ajax()) {
            $searchValue = (string) $request->input('search.value', '');
            $start = (int) $request->input('start', 0);
            $length = (int) $request->input('length', 10);
            $draw = (int) $request->input('draw', 0);
            $statusFilter = (string) $request->input('status');
            $dateFilter = (string) $request->input('date');

            $columns = [
                0 => 'a.code',
                1 => 'a.date',
                2 => 'w.name',
                3 => 'i.sku',
                4 => 'a.status',
                5 => 'a.id', // actions
            ];
            $orderByColumnIndex = (int) $request->input('order.0.column', 0);
            $orderByColumnName = $columns[$orderByColumnIndex] ?? $columns[0];
            $orderDirection = (string) $request->input('order.0.dir', 'desc');

            $base = Assembly::query()
                ->from('assemblies as a')
                ->leftJoin('warehouses as w', 'a.warehouse_id', '=', 'w.id')
                ->leftJoin('items as i', 'a.finished_item_id', '=', 'i.id');

            $totalRecords = (clone $base)->count('a.id');

            // Apply filters
            if ($searchValue !== '') {
                $base->where(function ($q) use ($searchValue) {
                    $q->where('a.code', 'LIKE', "%{$searchValue}%")
                      ->orWhere('a.notes', 'LIKE', "%{$searchValue}%")
                      ->orWhere('w.name', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.sku', 'LIKE', "%{$searchValue}%")
                      ->orWhere('i.nama_barang', 'LIKE', "%{$searchValue}%");
                });
            }
            if ($statusFilter && $statusFilter !== 'semua') {
                $mapped = $statusFilter === 'in_progress' ? 'dalam proses' : $statusFilter;
                $base->where('a.status', $mapped);
            }
            if ($dateFilter && $dateFilter !== 'semua') {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$startDate, $endDate] = explode(' to ', $dateFilter);
                    $base->whereBetween('a.date', [$startDate, $endDate]);
                } else {
                    $base->whereDate('a.date', $dateFilter);
                }
            }

            $totalFiltered = (clone $base)->count('a.id');

            $data = $base->select(
                    'a.id',
                    'a.code',
                    'a.date',
                    'a.status',
                    'a.output_quantity_plan',
                    'a.output_quantity_real',
                    'a.output_koli_plan',
                    'a.output_koli_real',
                    'a.notes',
                    'w.name as warehouse_name',
                    'i.sku as finished_sku',
                    'i.nama_barang as finished_name'
                )
                ->orderBy($orderByColumnName, $orderDirection)
                ->offset($start)
                ->limit($length)
                ->get();

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $totalFiltered,
                'data' => $data,
            ]);
        }

        $search = trim((string) $request->input('search'));
        $statusFilter = (string) $request->input('status', 'semua');
        $dateFilter = (string) $request->input('date', 'semua');

        $assemblies = Assembly::query()
            ->with(['warehouse', 'finishedItem'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('finishedItem', function ($qi) use ($search) {
                      $qi->where('sku', 'LIKE', "%{$search}%")
                         ->orWhere('nama_barang', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('warehouse', function ($qw) use ($search) {
                      $qw->where('name', 'LIKE', "%{$search}%");
                  });
            })
            ->when($statusFilter !== '' && $statusFilter !== 'semua', function ($q) use ($statusFilter) {
                $status = $statusFilter === 'in_progress' ? 'dalam proses' : $statusFilter;
                $q->where('status', $status);
            })
            ->when($dateFilter !== '' && $dateFilter !== 'semua', function ($q) use ($dateFilter) {
                if (strpos($dateFilter, ' to ') !== false) {
                    [$start, $end] = explode(' to ', $dateFilter);
                    $q->whereBetween('date', [$start, $end]);
                } else {
                    $q->whereDate('date', $dateFilter);
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10)
            ->appends([
                'search' => $search,
                'status' => $statusFilter,
                'date' => $dateFilter,
            ]);

        return view('admin.manajemenstok.perakitan.index', compact('assemblies', 'search', 'statusFilter', 'dateFilter'));
    }

    public function create()
    {
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        $recipes = AssemblyRecipe::with('finishedItem', 'items.item')->where('is_active', true)->orderBy('code')->get();
        $nextCode = $this->generateCode();
        return view('admin.manajemenstok.perakitan.create', compact('warehouses', 'items', 'recipes', 'nextCode'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:assemblies,code',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'date' => 'required|date',
            'assembly_recipe_id' => 'nullable|integer|exists:assembly_recipes,id',
            'finished_item_id' => 'required|integer|exists:items,id',
            'output_quantity_plan' => 'required|numeric|min:0.01',
            'output_koli_plan' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'components.item_id' => 'required|array|min:1',
            'components.item_id.*' => 'required|integer|exists:items,id',
            'components.quantity_recipes' => 'required|array|min:1',
            'components.quantity_recipes.*' => 'required|numeric|min:0.00',
            'components.quantity_plan' => 'required|array|min:1',
            'components.quantity_plan.*' => 'required|numeric|min:0.01',
            'components.koli_plan' => 'nullable|array',
            'components.koli_plan.*' => 'nullable|numeric|min:0',
        ]);

        $finished = (int) $request->input('finished_item_id');
        $componentIds = $request->input('components.item_id', []);
        // Ensure no duplicate component items
        $seen = [];
        foreach ($componentIds as $idx => $compId) {
            if (!$compId) continue;
            if (isset($seen[$compId])) {
                return back()->withInput()->withErrors(['components.item_id.' . $idx => 'Item komponen tidak boleh duplikat.']);
            }
            $seen[$compId] = true;
        }
        foreach ($componentIds as $compId) {
            if ($compId && (int) $compId === $finished) {
                return back()->withInput()->withErrors(['finished_item_id' => 'Produk jadi tidak boleh sama dengan salah satu komponen.']);
            }
        }

        $warehouseId = (int) $request->input('warehouse_id');
        $itemIds = $request->input('components.item_id', []);
        $quantitiesRecipes = $request->input('components.quantity_recipes', []);
        $quantitiesPlan = $request->input('components.quantity_plan', []);
        $kolisPlan = $request->input('components.koli_plan', []);

        // Validate stock sufficiency (aggregate and return SweetAlert-friendly payload)
        $shortages = [];
        $uniqItemIds = collect($itemIds)->filter()->unique()->values()->all();
        if (!empty($uniqItemIds)) {
            $inventories = Inventory::where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $uniqItemIds)
                ->get()
                ->keyBy('item_id');
            $itemsMeta = Item::whereIn('id', $uniqItemIds)->get()->keyBy('id');
            foreach ($itemIds as $idx => $itemId) {
                $qty = (float) ($quantitiesPlan[$idx] ?? 0);
                if (!$itemId || $qty <= 0) continue;
                $available = isset($inventories[$itemId]) ? (float) $inventories[$itemId]->quantity : 0.0;
                if ($available < $qty) {
                    $meta = $itemsMeta[$itemId] ?? null;
                    $shortages[] = [
                        'item_id' => (int) $itemId,
                        'sku' => $meta->sku ?? null,
                        'name' => $meta->nama_barang ?? 'Item #' . $itemId,
                        'required' => $qty,
                        'available' => $available,
                        'row_index' => $idx,
                    ];
                }
            }
        }
        if (!empty($shortages)) {
            return back()
                ->withInput()
                ->with('stock_errors', $shortages)
                ->with('stock_error_title', 'Stok komponen tidak mencukupi');
        }

        DB::beginTransaction();
        try {
            $recipeId = $request->input('assembly_recipe_id');
            $recipe = $recipeId ? AssemblyRecipe::find($recipeId) : null;
            // If recipe selected, finished item must follow recipe
            if ($recipe && (int) $request->input('finished_item_id') !== (int) $recipe->finished_item_id) {
                return back()->withInput()->withErrors(['finished_item_id' => 'Produk jadi harus mengikuti produk pada resep yang dipilih.']);
            }
            $assembly = Assembly::create([
                'code' => $request->input('code'),
                'warehouse_id' => $warehouseId,
                'date' => $request->input('date'),
                'assembly_recipe_id' => $recipeId,
                'finished_item_id' => $finished,
                'output_quantity_plan' => (float) $request->input('output_quantity_plan'),
                'output_quantity_real' => 0,
                'output_koli_plan' => (float) $request->input('output_koli_plan', 0),
                'output_koli_real' => 0,
                'notes' => $request->input('notes'),
                'created_by' => Auth::id(),
                'status' => 'draft',
            ]);

            // Save components (plan)
            foreach ($itemIds as $idx => $itemId) {
                $qtyRecipes = (float) ($quantitiesRecipes[$idx] ?? 0);
                $qtyPlan = (float) ($quantitiesPlan[$idx] ?? 0);
                $koliPlan = (float) ($kolisPlan[$idx] ?? 0);
                if (!$itemId || $qtyPlan <= 0) continue;

                AssemblyComponent::create([
                    'assembly_id' => $assembly->id,
                    'item_id' => $itemId,
                    'quantity_recipes' => $qtyRecipes,
                    'quantity_plan' => $qtyPlan,
                    'quantity_real' => 0,
                    'quantity_rejected' => 0,
                    'koli_plan' => $koliPlan,
                    'koli_real' => 0,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'perakitan',
                'description' => 'Membuat perakitan (draft): ' . $assembly->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('success', 'Perakitan berhasil disimpan (draft).');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan perakitan: ' . $e->getMessage());
        }
    }

    public function edit(Assembly $assembly)
    {
        if ($assembly->status === 'completed' || $assembly->status === 'dalam proses') {
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('error', 'Dokumen sudah '. $assembly->status .', tidak dapat diedit.');
        }
        $assembly->load('components', 'finishedItem', 'warehouse', 'recipe');
        $warehouses = Warehouse::orderBy('name')->get();
        $items = Item::orderBy('nama_barang')->get();
        $recipes = AssemblyRecipe::with('finishedItem', 'items.item')->where('is_active', true)->orderBy('code')->get();
        return view('admin.manajemenstok.perakitan.edit', compact('assembly', 'warehouses', 'items', 'recipes'));
    }

    public function update(Request $request, Assembly $assembly)
    {
        if ($assembly->status === 'completed') {
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('error', 'Dokumen sudah completed, tidak dapat diubah.');
        }

        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'date' => 'required|date',
            'assembly_recipe_id' => 'nullable|integer|exists:assembly_recipes,id',
            'finished_item_id' => 'required|integer|exists:items,id',
            'output_quantity_plan' => 'required|numeric|min:0.01',
            'output_koli_plan' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'components.item_id' => 'required|array|min:1',
            'components.item_id.*' => 'required|integer|exists:items,id',
            'components.quantity_recipes' => 'required|array|min:1',
            'components.quantity_recipes.*' => 'required|numeric|min:0.00',
            'components.quantity_plan' => 'required|array|min:1',
            'components.quantity_plan.*' => 'required|numeric|min:0.01',
            'components.koli_plan' => 'nullable|array',
            'components.koli_plan.*' => 'nullable|numeric|min:0',
        ]);

        $finished = (int) $request->input('finished_item_id');
        foreach ($request->input('components.item_id', []) as $compId) {
            if ($compId && (int) $compId === $finished) {
                return back()->withInput()->withErrors(['finished_item_id' => 'Produk jadi tidak boleh sama dengan salah satu komponen.']);
            }
        }

        // Ensure no duplicate component items
        $componentIds = $request->input('components.item_id', []);
        $seen = [];
        foreach ($componentIds as $idx => $compId) {
            if (!$compId) continue;
            if (isset($seen[$compId])) {
                return back()->withInput()->withErrors(['components.item_id.' . $idx => 'Item komponen tidak boleh duplikat.']);
            }
            $seen[$compId] = true;
        }

        // Validate stock sufficiency prior to persisting changes
        $warehouseId = (int) $request->input('warehouse_id');
        $itemIds = $request->input('components.item_id', []);
        $quantitiesPlan = $request->input('components.quantity_plan', []);
        $shortages = [];
        $uniqItemIds = collect($itemIds)->filter()->unique()->values()->all();
        if (!empty($uniqItemIds)) {
            $inventories = Inventory::where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $uniqItemIds)
                ->get()
                ->keyBy('item_id');
            $itemsMeta = Item::whereIn('id', $uniqItemIds)->get()->keyBy('id');
            foreach ($itemIds as $idx => $itemId) {
                $qty = (float) ($quantitiesPlan[$idx] ?? 0);
                if (!$itemId || $qty <= 0) continue;
                $available = isset($inventories[$itemId]) ? (float) $inventories[$itemId]->quantity : 0.0;
                if ($available < $qty) {
                    $meta = $itemsMeta[$itemId] ?? null;
                    $shortages[] = [
                        'item_id' => (int) $itemId,
                        'sku' => $meta->sku ?? null,
                        'name' => $meta->nama_barang ?? 'Item #' . $itemId,
                        'required' => $qty,
                        'available' => $available,
                        'row_index' => $idx,
                    ];
                }
            }
        }
        if (!empty($shortages)) {
            return back()
                ->withInput()
                ->with('stock_errors', $shortages)
                ->with('stock_error_title', 'Stok komponen tidak mencukupi');
        }

        DB::beginTransaction();
        try {
            $recipeId = $request->input('assembly_recipe_id');
            $recipe = $recipeId ? AssemblyRecipe::find($recipeId) : null;
            if ($recipe && (int) $request->input('finished_item_id') !== (int) $recipe->finished_item_id) {
                return back()->withInput()->withErrors(['finished_item_id' => 'Produk jadi harus mengikuti produk pada resep yang dipilih.']);
            }
            $assembly->update([
                'warehouse_id' => (int) $request->input('warehouse_id'),
                'date' => $request->input('date'),
                'assembly_recipe_id' => $recipeId,
                'finished_item_id' => $finished,
                'output_quantity_plan' => (float) $request->input('output_quantity_plan'),
                'output_koli_plan' => (float) $request->input('output_koli_plan', $assembly->output_koli_plan ?? 0),
                'notes' => $request->input('notes'),
            ]);

            // Sync components: simple approach -> delete & recreate
            $assembly->components()->delete();
            $itemIds = $request->input('components.item_id', []);
            $quantitiesRecipes = $request->input('components.quantity_recipes', []);
            $quantitiesPlan = $request->input('components.quantity_plan', []);
            $kolisPlan = $request->input('components.koli_plan', []);
            foreach ($itemIds as $idx => $itemId) {
                $qtyRecipes = (float) ($quantitiesRecipes[$idx] ?? 0);
                $qtyPlan = (float) ($quantitiesPlan[$idx] ?? 0);
                $koliPlan = (float) ($kolisPlan[$idx] ?? 0);
                if (!$itemId || $qtyPlan <= 0) continue;
                AssemblyComponent::create([
                    'assembly_id' => $assembly->id,
                    'item_id' => $itemId,
                    'quantity_recipes' => $qtyRecipes,
                    'quantity_plan' => $qtyPlan,
                    'quantity_real' => 0,
                    'quantity_rejected' => 0,
                    'koli_plan' => $koliPlan,
                    'koli_real' => 0,
                ]);
            }

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'perakitan',
                'description' => 'Mengubah perakitan (draft): ' . $assembly->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('success', 'Perakitan berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memperbarui perakitan: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, Assembly $assembly)
    {
        if ($assembly->status === 'completed') {
            return back()->with('error', 'Dokumen sudah completed.');
        }

        $request->validate([
            'output_quantity_real' => 'required|numeric|min:0.01',
            'output_koli_real' => 'nullable|numeric|min:0',
            'components' => 'sometimes|array',
            'components.*.quantity_real' => 'nullable|numeric|min:0',
            'components.*.koli_real' => 'nullable|numeric|min:0',
            'completed_date' => 'nullable|date',
        ]);

        // Pre-check stock sufficiency based on intended real consumption
        $componentsInput = (array) $request->input('components', []);
        $components = $assembly->components()->with('item')->get();
        $warehouseId = (int) $assembly->warehouse_id;

        // Aggregate required quantities per item
        $requiredPerItem = [];
        foreach ($components as $component) {
            $cid = (string) $component->id;
            $need = isset($componentsInput[$cid]['quantity_real'])
                ? (float) $componentsInput[$cid]['quantity_real']
                : (float) $component->quantity_plan;
            if ($need <= 0) continue;
            $itemId = (int) $component->item_id;
            if (!isset($requiredPerItem[$itemId])) $requiredPerItem[$itemId] = 0.0;
            $requiredPerItem[$itemId] += $need;
        }

        if (!empty($requiredPerItem)) {
            $itemIds = array_keys($requiredPerItem);
            $inventories = Inventory::where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id');
            $itemsMeta = Item::whereIn('id', $itemIds)->get()->keyBy('id');

            $shortMsgs = [];
            foreach ($requiredPerItem as $itemId => $need) {
                $available = isset($inventories[$itemId]) ? (float) $inventories[$itemId]->quantity : 0.0;
                if ($available + 1e-9 < $need) { // tolerate tiny floating error
                    $meta = $itemsMeta[$itemId] ?? null;
                    $label = ($meta->sku ?? 'SKU?') . ' - ' . ($meta->nama_barang ?? ('Item #' . $itemId));
                    $shortMsgs[] = $label . ' (butuh ' . number_format($need,2) . ', stok ' . number_format($available,2) . ')';
                }
            }
            if (!empty($shortMsgs)) {
                $message = 'Stok komponen tidak mencukupi: ' . implode('; ', $shortMsgs);
                if ($request->ajax()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return back()->with('error', $message);
            }
        }

        DB::beginTransaction();
        try {
            $assembly->output_quantity_real = $request->input('output_quantity_real');
            $assembly->output_koli_real =  $request->input('output_koli_real', 0);
            $completedDate = $request->input('completed_date');
            if ($completedDate) {
                $assembly->completed_at = $completedDate;
            } else {
                $assembly->completed_at = now()->toDateString();
            }
            $assembly->status = 'completed';
            $assembly->save();

            // Determine real component usage: use request values if provided; otherwise fallback to plan
            $components = $assembly->components()->get();
            foreach ($components as $component) {
                $cid = (string) $component->id;
                $realQty = isset($componentsInput[$cid]['quantity_real']) ? (float) $componentsInput[$cid]['quantity_real'] : null;
                $realKoli = isset($componentsInput[$cid]['koli_real']) ? (float) $componentsInput[$cid]['koli_real'] : null;

                if ($realQty === null || $realQty <= 0) {
                    $realQty = (float) $component->quantity_plan;
                }
                if ($realKoli === null || $realKoli < 0) {
                    $realKoli = (float) $component->koli_plan;
                }
                $component->quantity_real = $realQty;
                $component->koli_real = $realKoli;
                $component->save();
            }

            // Inventory movements on completion
            $warehouseId = (int) $assembly->warehouse_id;
            foreach ($components as $component) {
                $itemId = (int) $component->item_id;
                $qty = (float) $component->quantity_real;
                if ($qty <= 0) continue;

                $inventory = Inventory::firstOrCreate([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                ], [
                    'quantity' => 0,
                    'koli' => 0,
                ]);
                $before = (float) $inventory->quantity;
                $inventory->decrement('quantity', $qty);
                $after = (float) $inventory->fresh()->quantity;

                StockMovement::create([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'date' => $assembly->date,
                    'quantity' => -$qty,
                    'koli' => (float) $component->koli_real,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'type' => 'stock_out',
                    'description' => 'Konsumsi komponen perakitan ' . $assembly->code,
                    'user_id' => Auth::id(),
                    'reference_id' => $component->id,
                    'reference_type' => 'assembly_components',
                ]);
            }

            // Produce finished item with real output
            $prodInventory = Inventory::firstOrCreate([
                'warehouse_id' => $warehouseId,
                'item_id' => (int) $assembly->finished_item_id,
            ], [
                'quantity' => 0,
                'koli' => 0,
            ]);
            $beforeProd = (float) $prodInventory->quantity;
            $prodInventory->increment('quantity', (float) $assembly->output_quantity_real);
            $afterProd = (float) $prodInventory->fresh()->quantity;

            StockMovement::create([
                'item_id' => (int) $assembly->finished_item_id,
                'warehouse_id' => $warehouseId,
                'date' => $assembly->date,
                'quantity' => (float) $assembly->output_quantity_real,
                'koli' => (float) $assembly->output_koli_real,
                'stock_before' => $beforeProd,
                'stock_after' => $afterProd,
                'type' => 'stock_in',
                'description' => 'Hasil perakitan ' . $assembly->code,
                'user_id' => Auth::id(),
                'reference_id' => $assembly->id,
                'reference_type' => 'assemblies',
            ]);

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'approved',
                'menu' => 'perakitan',
                'description' => 'Menyetujui perakitan: ' . $assembly->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('success', 'Perakitan disetujui dan diselesaikan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal menyetujui perakitan: ' . $e->getMessage());
        }
    }

    public function recipeDetail(AssemblyRecipe $recipe)
    {
        $recipe->load('finishedItem', 'items.item');
        return response()->json([
            'id' => $recipe->id,
            'finished_item_id' => $recipe->finished_item_id,
            'items' => $recipe->items->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'sku' => $row->item->sku ?? null,
                    'name' => $row->item->nama_barang ?? null,
                    'quantity' => (float) $row->quantity,
                ];
            })->values(),
        ]);
    }

    public function stock(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'item_id' => 'required|integer|exists:items,id',
        ]);
        $warehouseId = (int) $request->input('warehouse_id');
        $itemId = (int) $request->input('item_id');
        $inv = Inventory::where('warehouse_id', $warehouseId)->where('item_id', $itemId)->first();
        return response()->json([
            'success' => true,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'quantity' => $inv ? (float) $inv->quantity : 0.0,
            'koli' => $inv ? (float) $inv->koli : 0.0,
        ]);
    }

    public function getStatusCounts(Request $request)
    {
        $dateFilter = (string) $request->input('date', 'semua');
        $search = trim((string) $request->input('search', ''));

        $base = Assembly::query();
        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }
        if ($dateFilter !== '' && $dateFilter !== 'semua') {
            if (strpos($dateFilter, ' to ') !== false) {
                [$start, $end] = explode(' to ', $dateFilter);
                $base->whereBetween('date', [$start, $end]);
            } else {
                $base->whereDate('date', $dateFilter);
            }
        }

        $counts = [
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'in_progress' => (clone $base)->where('status', 'dalam proses')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];
        return response()->json($counts);
    }

    public function detail(Assembly $assembly)
    {
        $assembly->load(['components.item', 'finishedItem']);
        return response()->json([
            'id' => $assembly->id,
            'code' => $assembly->code,
            'status' => $assembly->status,
            'output_quantity_plan' => (float) $assembly->output_quantity_plan,
            'output_koli_plan' => (float) $assembly->output_koli_plan,
            'finished_sku' => $assembly->finishedItem->sku ?? null,
            'finished_name' => $assembly->finishedItem->nama_barang ?? null,
            'finished_koli_ratio' => isset($assembly->finishedItem) ? (float) ($assembly->finishedItem->koli ?? 1) : 1,
            'components' => $assembly->components->map(function ($c) {
                return [
                    'id' => $c->id,
                    'item_id' => $c->item_id,
                    'sku' => $c->item->sku ?? null,
                    'name' => $c->item->nama_barang ?? null,
                    'quantity_recipes' => (float) $c->quantity_recipes,
                    'quantity_plan' => (float) $c->quantity_plan,
                    'koli_plan' => (float) $c->koli_plan,
                    'koli_ratio' => isset($c->item) ? (float) ($c->item->koli ?? 1) : 1,
                ];
            })->values(),
        ]);
    }

    public function destroy(Request $request, Assembly $assembly)
    {
        if ($assembly->status === 'completed') {
            $message = 'Dokumen completed tidak dapat dihapus.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        DB::beginTransaction();
        try {
            $code = $assembly->code;
            // Remove components first
            $assembly->components()->delete();
            $assembly->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'perakitan',
                'description' => 'Menghapus perakitan: ' . $code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Dokumen ' . $code . ' berhasil dihapus.']);
            }
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('success', 'Dokumen ' . $code . ' berhasil dihapus.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Gagal menghapus dokumen: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Gagal menghapus dokumen: ' . $e->getMessage());
        }
    }

    public function start(Request $request, Assembly $assembly)
    {
        if ($assembly->status !== 'draft') {
            $message = 'Hanya dokumen berstatus draft yang dapat diproses.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return back()->with('error', $message);
        }

        // Validate stock sufficiency based on planned component quantities
        $components = $assembly->components()->with('item')->get();
        $warehouseId = (int) $assembly->warehouse_id;
        $requiredPerItem = [];
        foreach ($components as $component) {
            $need = (float) $component->quantity_plan;
            if ($need <= 0) continue;
            $itemId = (int) $component->item_id;
            if (!isset($requiredPerItem[$itemId])) $requiredPerItem[$itemId] = 0.0;
            $requiredPerItem[$itemId] += $need;
        }
        if (!empty($requiredPerItem)) {
            $itemIds = array_keys($requiredPerItem);
            $inventories = Inventory::where('warehouse_id', $warehouseId)
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id');
            $itemsMeta = Item::whereIn('id', $itemIds)->get()->keyBy('id');

            $shortMsgs = [];
            foreach ($requiredPerItem as $itemId => $need) {
                $available = isset($inventories[$itemId]) ? (float) $inventories[$itemId]->quantity : 0.0;
                if ($available + 1e-9 < $need) {
                    $meta = $itemsMeta[$itemId] ?? null;
                    $label = ($meta->sku ?? 'SKU?') . ' - ' . ($meta->nama_barang ?? ('Item #' . $itemId));
                    $shortMsgs[] = $label . ' (butuh ' . number_format($need,2) . ', stok ' . number_format($available,2) . ')';
                }
            }
            if (!empty($shortMsgs)) {
                $message = 'Stok komponen tidak mencukupi untuk memulai proses: ' . implode('; ', $shortMsgs);
                if ($request->ajax()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return back()->with('error', $message);
            }
        }

        DB::beginTransaction();
        try {
            $assembly->status = 'dalam proses';
            $assembly->save();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'started',
                'menu' => 'perakitan',
                'description' => 'Memproses perakitan (dalam proses): ' . $assembly->code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            DB::commit();
            $message = 'Perakitan telah diproses (status: dalam proses).';
            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => $message]);
            }
            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            $message = 'Gagal memproses perakitan: ' . $e->getMessage();
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }
            return back()->with('error', $message);
        }
    }
}
