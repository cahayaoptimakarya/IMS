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
        $search = trim((string) $request->input('search'));
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
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(10)
            ->appends(['search' => $search]);

        return view('admin.manajemenstok.perakitan.index', compact('assemblies', 'search'));
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
                'status' => 'dalam proses',
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
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('success', 'Perakitan berhasil disimpan (dalam proses).');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Gagal menyimpan perakitan: ' . $e->getMessage());
        }
    }

    public function edit(Assembly $assembly)
    {
        if ($assembly->status === 'completed') {
            return redirect()->route('admin.manajemenstok.perakitan.index')->with('error', 'Dokumen sudah completed, tidak dapat diedit.');
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
                'description' => 'Mengubah perakitan (dalam proses): ' . $assembly->code,
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
        ]);

        DB::beginTransaction();
        try {
            $assembly->output_quantity_real = $request->input('output_quantity_real');
            $assembly->output_koli_real =  $request->input('output_koli_real', 0);
            $assembly->status = 'completed';
            $assembly->save();

            // Determine real component usage: fallback to plan if none provided
            $components = $assembly->components()->get();
            foreach ($components as $component) {
                if (!$component->quantity_real || $component->quantity_real <= 0) {
                    $component->quantity_real = $component->quantity_plan;
                }
                if (!$component->koli_real || $component->koli_real <= 0) {
                    $component->koli_real = $component->koli_plan;
                }
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
                    'type' => 'assembly_consumption',
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
                'type' => 'assembly_production',
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
}
