<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ItemCategoryImportController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.item_category_import.index');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file CSV.');
        }

        $createdCategories = 0;
        $updatedItems = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNumber++;

                // Skip empty lines
                if (count($row) === 0 || (count($row) === 1 && trim((string)($row[0] ?? '')) === '')) {
                    continue;
                }

                $sku = isset($row[0]) ? trim((string)$row[0]) : '';
                $categoryName = isset($row[1]) ? trim((string)$row[1]) : '';

                if ($sku === '') {
                    $errors[] = "Baris {$rowNumber}: SKU wajib.";
                    $skipped++;
                    continue;
                }

                // Tentukan kategori: jika kolom B kosong, pakai default id=1
                if ($categoryName === '') {
                    $categoryId = 1;
                } else {
                    // Ensure category exists (create if missing)
                    $existingCategory = ItemCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])
                        ->first();
                    if (!$existingCategory) {
                        $existingCategory = ItemCategory::create([
                            'name' => $categoryName,
                            'description' => null,
                        ]);
                        $createdCategories++;
                    }
                    $categoryId = $existingCategory->id;
                }

                // Update item category by SKU
                $affected = Item::where('sku', $sku)->update([
                    'item_category_id' => $categoryId,
                ]);

                if ($affected > 0) {
                    $updatedItems += $affected;
                } else {
                    $errors[] = "Baris {$rowNumber}: SKU '{$sku}' tidak ditemukan; dilewati.";
                    $skipped++;
                }
            }

            fclose($handle);

            if ($updatedItems > 0 || $createdCategories > 0) {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'imported',
                    'menu' => 'itemcategories-import',
                    'description' => "Import kategori dari CSV: {$createdCategories} kategori baru, {$updatedItems} item terbarui.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            }

            DB::commit();

            $message = "Import selesai. {$createdCategories} kategori dibuat, {$updatedItems} item diperbarui, {$skipped} baris dilewati.";
            if (count($errors) > 0) {
                $message .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 5));
            }

            // Flash structured summary for UI rendering
            session()->flash('import_summary', [
                'created_categories' => $createdCategories,
                'updated_items' => $updatedItems,
                'skipped' => $skipped,
                'notes' => array_slice($errors, 0, 50),
            ]);

            return redirect()->route('admin.masterdata.item-categories-import.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            return redirect()->back()->with('error', 'Gagal mengimpor CSV: ' . $e->getMessage());
        }
    }

    public function importTempat(Request $request)
    {
        $request->validate([
            'csv_file_tempat' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file_tempat');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file CSV.');
        }

        $updated = 0; $skipped = 0; $errors = [];

        DB::beginTransaction();
        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNumber++;
                if (count($row) === 0 || (count($row) === 1 && trim((string)($row[0] ?? '')) === '')) {
                    continue;
                }
                $sku = isset($row[0]) ? trim((string)$row[0]) : '';
                $tempat = isset($row[1]) ? trim((string)$row[1]) : '';

                if ($sku === '' || $tempat === '') {
                    $errors[] = "Baris {$rowNumber}: SKU dan Tempat wajib.";
                    $skipped++;
                    continue;
                }

                $affected = Item::where('sku', $sku)->update(['tempat' => $tempat]);
                if ($affected > 0) { $updated += $affected; }
                else { $errors[] = "Baris {$rowNumber}: SKU '{$sku}' tidak ditemukan; dilewati."; $skipped++; }
            }

            fclose($handle);

            if ($updated > 0) {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'imported',
                    'menu' => 'items-tempat-import',
                    'description' => "Import tempat dari CSV: {$updated} item diperbarui.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            }

            DB::commit();

            session()->flash('import_tempat_summary', [
                'updated' => $updated,
                'skipped' => $skipped,
                'notes' => array_slice($errors, 0, 50),
            ]);

            $msg = "Import tempat selesai. {$updated} item diperbarui, {$skipped} baris dilewati.";
            if (count($errors) > 0) { $msg .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 5)); }
            return redirect()->route('admin.masterdata.item-categories-import.index')->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) { fclose($handle); }
            return redirect()->back()->with('error', 'Gagal mengimpor CSV tempat: ' . $e->getMessage());
        }
    }

    public function importItemType(Request $request)
    {
        $request->validate([
            'csv_file_tipe' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file_tipe');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file CSV.');
        }

        $createdTypes = 0; $updatedItems = 0; $skipped = 0; $errors = [];

        DB::beginTransaction();
        try {
            $rowNumber = 0;
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $rowNumber++;
                if (count($row) === 0 || (count($row) === 1 && trim((string)($row[0] ?? '')) === '')) {
                    continue;
                }
                $sku = isset($row[0]) ? trim((string)$row[0]) : '';
                $typeName = isset($row[1]) ? trim((string)$row[1]) : '';

                if ($sku === '' || $typeName === '') {
                    $errors[] = "Baris {$rowNumber}: SKU dan Nama Tipe wajib.";
                    $skipped++;
                    continue;
                }

                $existingType = \App\Models\ItemType::whereRaw('LOWER(name) = ?', [mb_strtolower($typeName)])->first();
                if (!$existingType) {
                    $existingType = \App\Models\ItemType::create(['name' => $typeName, 'description' => null]);
                    $createdTypes++;
                }

                $affected = Item::where('sku', $sku)->update(['item_type_id' => $existingType->id]);
                if ($affected > 0) { $updatedItems += $affected; }
                else { $errors[] = "Baris {$rowNumber}: SKU '{$sku}' tidak ditemukan; dilewati."; $skipped++; }
            }

            fclose($handle);

            if ($updatedItems > 0 || $createdTypes > 0) {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'activity' => 'imported',
                    'menu' => 'itemtypes-import',
                    'description' => "Import tipe dari CSV: {$createdTypes} tipe baru, {$updatedItems} item terbarui.",
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                ]);
            }

            DB::commit();

            session()->flash('import_tipe_summary', [
                'created_types' => $createdTypes,
                'updated_items' => $updatedItems,
                'skipped' => $skipped,
                'notes' => array_slice($errors, 0, 50),
            ]);

            $msg = "Import tipe selesai. {$createdTypes} tipe dibuat, {$updatedItems} item diperbarui, {$skipped} baris dilewati.";
            if (count($errors) > 0) { $msg .= ' Catatan: ' . implode(' | ', array_slice($errors, 0, 5)); }
            return redirect()->route('admin.masterdata.item-categories-import.index')->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            if (is_resource($handle)) { fclose($handle); }
            return redirect()->back()->with('error', 'Gagal mengimpor CSV tipe: ' . $e->getMessage());
        }
    }
}
