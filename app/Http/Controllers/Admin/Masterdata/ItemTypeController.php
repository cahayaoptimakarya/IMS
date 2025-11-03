<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\ItemType;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemTypeController extends Controller
{
    public function index()
    {
        $itemtypes = ItemType::all();
        return view('admin.masterdata.itemtypes.index', compact('itemtypes'));
    }

    public function create()
    {
        return view('admin.masterdata.itemtypes.create');
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:item_types,name',
                'description' => 'nullable|string',
            ]);

            $itemtype = ItemType::create($request->only(['name','description']));

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'created',
                'menu' => 'itemtypes',
                'description' => 'Menambahkan tipe item baru: ' . $itemtype->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemtypes.index')->with('success', 'Tipe item berhasil ditambahkan.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal menambahkan tipe item: ' . $e->getMessage());
        }
    }

    public function edit(ItemType $itemtype)
    {
        return view('admin.masterdata.itemtypes.edit', compact('itemtype'));
    }

    public function update(Request $request, ItemType $itemtype)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:item_types,name,' . $itemtype->id,
                'description' => 'nullable|string',
            ]);

            $itemtype->update($request->only(['name','description']));

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'itemtypes',
                'description' => 'Memperbarui tipe item: ' . $itemtype->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemtypes.index')->with('success', 'Tipe item berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error' , 'Gagal memperbarui tipe item: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, ItemType $itemtype)
    {
        try {
            $name = $itemtype->name;
            $itemtype->delete();

            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'deleted',
                'menu' => 'itemtypes',
                'description' => 'Menghapus tipe item: ' . $name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return redirect()->route('admin.masterdata.itemtypes.index')->with('success', 'Tipe item berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal menghapus tipe item: ' . $e->getMessage());
        }
    }
}

