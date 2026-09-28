<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Exports\ItemBulkUpdate\TemplateExport;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Models\UserActivity;
use App\Support\ItemBulkUpdate\FieldRegistry;
use App\Support\ItemBulkUpdate\ItemBulkUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

/**
 * Update massal master item via Excel: pilih field -> unduh template -> upload & pratinjau -> terapkan.
 */
class ItemBulkUpdateController extends Controller
{
    public function __construct(private ItemBulkUpdateService $service)
    {
    }

    public function index()
    {
        $groups = [];
        foreach (FieldRegistry::all() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }

        return view('admin.masterdata.items.bulk-update', [
            'groups' => $groups,
            'groupLabels' => FieldRegistry::GROUPS,
            'locked' => FieldRegistry::LOCKED,
            'clearKeyword' => FieldRegistry::CLEAR_KEYWORD,
            'maxRows' => ItemBulkUpdateService::MAX_ROWS,
            'itemcategories' => ItemCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function template(Request $request)
    {
        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*' => 'string|in:' . implode(',', FieldRegistry::keys()),
            'prefill' => 'nullable|in:current,empty',
            'category_id' => 'nullable|integer|exists:item_categories,id',
            'status' => 'nullable|in:active,inactive',
        ], $this->fieldMessages());

        $fields = $this->service->sanitizeFields($validated['fields']);
        $rows = ($validated['prefill'] ?? 'current') === 'current'
            ? $this->service->templateRows($fields, $validated['category_id'] ?? null, $validated['status'] ?? null)
            : new Collection();

        $fileName = 'template-update-item_' . implode('-', array_map(fn ($f) => str_replace('_', '', $f), $fields));
        $fileName = substr($fileName, 0, 120) . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new TemplateExport($fields, $rows, $this->service->references()), $fileName);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*' => 'string|in:' . implode(',', FieldRegistry::keys()),
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ], $this->fieldMessages() + [
            'file.required' => 'Pilih file Excel yang akan diupload.',
            'file.mimes' => 'File harus berformat .xlsx atau .xls.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        try {
            $result = $this->service->preview($request->file('file'), $validated['fields'], (int) Auth::id());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true] + $result);
    }

    public function apply(Request $request)
    {
        $validated = $request->validate(['token' => 'required|uuid']);

        try {
            $result = $this->service->apply($validated['token'], (int) Auth::id());
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $count = count($result['updated']);
        if ($count > 0) {
            $labels = array_map(fn ($f) => FieldRegistry::get($f)['label'], $result['fields']);
            $skuList = implode(', ', array_slice($result['updated'], 0, 20)) . ($count > 20 ? ', ...' : '');
            UserActivity::create([
                'user_id' => Auth::id(),
                'activity' => 'updated',
                'menu' => 'items',
                'description' => "Update massal {$count} item via Excel ({$result['file_name']}). Field: " . implode(', ', $labels) . ". SKU: {$skuList}",
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
        }

        $message = "{$count} item berhasil diperbarui.";
        if ($result['conflicts']) {
            $message .= ' ' . count($result['conflicts']) . ' item dilewati karena datanya sudah berubah sejak pratinjau.';
        }
        if ($result['missing']) {
            $message .= ' ' . count($result['missing']) . ' item sudah tidak ada.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'updated' => $count,
            'conflicts' => $result['conflicts'],
            'missing' => $result['missing'],
        ]);
    }

    private function fieldMessages(): array
    {
        return [
            'fields.required' => 'Pilih minimal satu field yang akan di-update.',
            'fields.min' => 'Pilih minimal satu field yang akan di-update.',
            'fields.*.in' => 'Field yang dipilih tidak dapat di-update (SKU dan Koli dikunci).',
        ];
    }
}
