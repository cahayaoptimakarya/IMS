<?php

namespace App\Support\ItemBulkUpdate;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\Uom;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Update massal master item dari file Excel berdasarkan SKU:
 * preview() memvalidasi & menyimpan rencana perubahan di cache, apply() menerapkannya.
 */
class ItemBulkUpdateService
{
    public const MAX_ROWS = 10000;
    private const CACHE_PREFIX = 'items-bulk-update:';
    private const CACHE_MINUTES = 30;
    private const MAX_DECIMAL = 99999999.99;
    private const MAX_INTEGER = 2147483647;

    private ?array $references = null;

    /**
     * Master data referensi: nama (dinormalisasi) -> id, dan id -> label.
     */
    public function references(): array
    {
        if ($this->references !== null) {
            return $this->references;
        }

        $build = function (Collection $rows, array $nameColumns) {
            $lookup = [];
            $labels = [];
            foreach ($rows as $row) {
                $labels[$row->id] = $row->name;
                foreach ($nameColumns as $column) {
                    $key = mb_strtolower(trim((string) $row->{$column}));
                    if ($key !== '' && !isset($lookup[$key])) {
                        $lookup[$key] = (int) $row->id;
                    }
                }
            }

            return ['lookup' => $lookup, 'labels' => $labels];
        };

        return $this->references = [
            'categories' => $build(ItemCategory::orderBy('name')->get(['id', 'name']), ['name']),
            'types' => $build(ItemType::orderBy('name')->get(['id', 'name']), ['name']),
            'uoms' => $build(Uom::orderBy('name')->get(['id', 'name', 'symbol']), ['name', 'symbol']),
        ];
    }

    /**
     * Baca & validasi file tanpa mengubah data.
     *
     * @throws RuntimeException untuk kesalahan level file (header, format, jumlah baris)
     */
    public function preview(UploadedFile $file, array $fields, int $userId): array
    {
        $fields = $this->sanitizeFields($fields);
        $columns = FieldRegistry::columnsFor($fields);

        $sheet = $this->loadSheet($file);
        $rows = $sheet->toArray(null, true, false, false);

        [$headerIndex, $columnMap, $warnings] = $this->mapHeader($rows, $columns);
        $dataRows = array_slice($rows, $headerIndex + 1, null, true);

        $nonEmpty = array_filter($dataRows, fn ($row) => $this->rowHasValue($row));
        if (count($nonEmpty) === 0) {
            throw new RuntimeException('File tidak berisi data di bawah baris header.');
        }
        if (count($nonEmpty) > self::MAX_ROWS) {
            throw new RuntimeException('Maksimal ' . number_format(self::MAX_ROWS, 0, ',', '.') . ' baris per upload. Pecah file menjadi beberapa bagian.');
        }

        $skuIndex = $columnMap['sku'];
        $items = $this->loadItems(array_map(fn ($row) => $this->cellText($row[$skuIndex] ?? null), $nonEmpty));

        $results = [];
        $plan = [];
        $seen = [];
        foreach ($nonEmpty as $index => $row) {
            $excelRow = $index + 1;
            $sku = $this->cellText($row[$skuIndex] ?? null);
            $result = ['row' => $excelRow, 'sku' => $sku, 'nama_barang' => null, 'status' => 'unchanged', 'changes' => [], 'errors' => []];

            if ($sku === '') {
                $result['status'] = 'error';
                $result['errors'][] = 'SKU kosong.';
                $results[] = $result;
                continue;
            }

            $skuKey = mb_strtoupper($sku);
            if (isset($seen[$skuKey])) {
                $result['status'] = 'error';
                $result['errors'][] = "SKU duplikat di file (sudah ada di baris {$seen[$skuKey]}). Baris ini diabaikan.";
                $results[] = $result;
                continue;
            }
            $seen[$skuKey] = $excelRow;

            $item = $items[$skuKey] ?? null;
            if (!$item) {
                $result['status'] = 'not_found';
                $result['errors'][] = 'SKU tidak ditemukan di master item.';
                $results[] = $result;
                continue;
            }

            $result['sku'] = $item->sku;
            $result['nama_barang'] = $item->nama_barang;
            $result['item_id'] = $item->id;

            $changes = [];
            foreach ($columns as $column => $meta) {
                $raw = $row[$columnMap[$column]] ?? null;
                $parsed = $this->parseCell($column, FieldRegistry::get($meta['field']), $raw);
                if ($parsed['skip']) {
                    continue;
                }
                if ($parsed['error']) {
                    $result['errors'][] = $meta['header'] . ': ' . $parsed['error'];
                    continue;
                }

                $old = $this->normalizeCurrent($column, $item->{$column});
                if ($old !== $parsed['value']) {
                    $changes[$column] = ['old' => $old, 'new' => $parsed['value']];
                }
            }

            if (in_array('dimensi_koli', $fields, true) && empty($result['errors'])) {
                $final = [];
                foreach (['panjang_koli', 'lebar_koli', 'tinggi_koli'] as $column) {
                    $final[$column] = array_key_exists($column, $changes)
                        ? $changes[$column]['new']
                        : $this->normalizeCurrent($column, $item->{$column});
                }
                $filled = count(array_filter($final, fn ($v) => $v !== null));
                if ($filled > 0 && $filled < 3) {
                    $result['errors'][] = 'Dimensi koli harus lengkap (panjang, lebar, tinggi) atau kosong semua.';
                }
            }

            if (!empty($result['errors'])) {
                $result['status'] = 'error';
            } elseif (!empty($changes)) {
                $result['status'] = 'changed';
                $plan[$item->id] = ['sku' => $item->sku, 'changes' => $changes];
            }

            $result['changes'] = $this->describeChanges($changes);
            $results[] = $result;
        }

        $summary = [
            'total' => count($results),
            'changed' => count(array_filter($results, fn ($r) => $r['status'] === 'changed')),
            'unchanged' => count(array_filter($results, fn ($r) => $r['status'] === 'unchanged')),
            'error' => count(array_filter($results, fn ($r) => $r['status'] === 'error')),
            'not_found' => count(array_filter($results, fn ($r) => $r['status'] === 'not_found')),
            'field_changes' => array_sum(array_map(fn ($p) => count($p['changes']), $plan)),
        ];

        $token = null;
        if (!empty($plan)) {
            $token = (string) Str::uuid();
            Cache::put(self::CACHE_PREFIX . $token, [
                'user_id' => $userId,
                'fields' => $fields,
                'file_name' => $file->getClientOriginalName(),
                'items' => $plan,
            ], now()->addMinutes(self::CACHE_MINUTES));
        }

        return [
            'token' => $token,
            'fields' => $fields,
            'columns' => array_map(fn ($c) => $c['header'], $columns),
            'summary' => $summary,
            'warnings' => $warnings,
            'rows' => $results,
            'expires_in_minutes' => self::CACHE_MINUTES,
        ];
    }

    /**
     * Terapkan rencana perubahan dari preview(). Item yang berubah sejak preview dilewati (konflik).
     */
    public function apply(string $token, int $userId): array
    {
        $plan = Cache::get(self::CACHE_PREFIX . $token);
        if (!$plan || (int) $plan['user_id'] !== $userId) {
            throw new RuntimeException('Sesi pratinjau tidak ditemukan atau sudah kedaluwarsa. Silakan upload ulang file.');
        }

        $updated = [];
        $conflicts = [];
        $missing = [];

        DB::transaction(function () use ($plan, &$updated, &$conflicts, &$missing) {
            $items = Item::whereIn('id', array_keys($plan['items']))->lockForUpdate()->get()->keyBy('id');

            foreach ($plan['items'] as $itemId => $entry) {
                $item = $items->get($itemId);
                if (!$item) {
                    $missing[] = $entry['sku'];
                    continue;
                }

                $conflict = false;
                foreach ($entry['changes'] as $column => $change) {
                    if ($this->normalizeCurrent($column, $item->{$column}) !== $change['old']) {
                        $conflict = true;
                        break;
                    }
                }
                if ($conflict) {
                    $conflicts[] = $entry['sku'];
                    continue;
                }

                foreach ($entry['changes'] as $column => $change) {
                    $item->{$column} = $change['new'];
                }
                $item->save();
                $updated[] = $entry['sku'];
            }
        });

        Cache::forget(self::CACHE_PREFIX . $token);

        return [
            'updated' => $updated,
            'conflicts' => $conflicts,
            'missing' => $missing,
            'fields' => $plan['fields'],
            'file_name' => $plan['file_name'],
        ];
    }

    /**
     * Data item untuk template yang sudah terisi nilai saat ini.
     */
    public function templateRows(array $fields, ?int $categoryId = null, ?string $status = null): Collection
    {
        $columns = FieldRegistry::columnsFor($this->sanitizeFields($fields));
        $refs = $this->references();

        return Item::query()
            ->when($categoryId, fn ($q) => $q->where('item_category_id', $categoryId))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('sku')
            ->get()
            ->map(function (Item $item) use ($columns, $refs) {
                $values = ['sku' => $item->sku, 'nama_barang_info' => $item->nama_barang];
                foreach ($columns as $column => $meta) {
                    $values[$column] = $this->displayValue($column, $this->normalizeCurrent($column, $item->{$column}), $refs, true);
                }

                return $values;
            });
    }

    /** @return string[] */
    public function sanitizeFields(array $fields): array
    {
        $fields = array_values(array_intersect(FieldRegistry::keys(), array_map('strval', $fields)));
        if (empty($fields)) {
            throw new RuntimeException('Pilih minimal satu field yang akan di-update.');
        }

        return $fields;
    }

    private function loadSheet(UploadedFile $file): Worksheet
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $book = $reader->load($file->getRealPath());
        } catch (\Throwable $e) {
            throw new RuntimeException('File tidak dapat dibaca. Pastikan formatnya .xlsx atau .xls dan tidak rusak.');
        }

        return $book->getSheetByName('Data') ?? $book->getSheet(0);
    }

    /**
     * Cari baris header (berisi "SKU") di 10 baris pertama lalu petakan kolom yang dipilih.
     *
     * @return array{0: int, 1: array<string, int>, 2: string[]}
     */
    private function mapHeader(array $rows, array $columns): array
    {
        $headerIndex = null;
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            foreach ($row as $cell) {
                if (FieldRegistry::normalizeHeader((string) $cell) === 'sku') {
                    $headerIndex = $index;
                    break 2;
                }
            }
        }
        if ($headerIndex === null) {
            throw new RuntimeException('Kolom "SKU" tidak ditemukan. Gunakan template yang diunduh dari halaman ini.');
        }

        $known = [];
        foreach (FieldRegistry::columnLabels() as $column => $header) {
            $known[FieldRegistry::normalizeHeader($header)] = $column;
            $known[FieldRegistry::normalizeHeader($column)] = $column;
        }
        $lockedHeaders = ['koli', 'isiperkoli', 'koliisiperkoli'];

        $map = [];
        $warnings = [];
        $ignored = [];
        foreach ($rows[$headerIndex] as $cellIndex => $cell) {
            $text = trim((string) $cell);
            $normalized = FieldRegistry::normalizeHeader($text);
            if ($normalized === '' || str_starts_with(mb_strtolower($text), mb_strtolower(FieldRegistry::INFO_PREFIX))) {
                continue;
            }
            if ($normalized === 'sku') {
                $map['sku'] ??= $cellIndex;
                continue;
            }
            if (in_array($normalized, $lockedHeaders, true)) {
                $warnings[] = "Kolom \"{$text}\" diabaikan karena Koli tidak dapat diubah.";
                continue;
            }
            $column = $known[$normalized] ?? null;
            if ($column && isset($columns[$column])) {
                if (isset($map[$column])) {
                    $warnings[] = "Kolom \"{$text}\" muncul lebih dari sekali; hanya kolom pertama yang dipakai.";
                    continue;
                }
                $map[$column] = $cellIndex;
            } else {
                $ignored[] = $text;
            }
        }

        if ($ignored) {
            $warnings[] = 'Kolom berikut tidak termasuk field yang dipilih sehingga diabaikan: ' . implode(', ', array_map(fn ($h) => "\"{$h}\"", $ignored)) . '.';
        }

        $missing = array_diff_key($columns, $map);
        if ($missing) {
            throw new RuntimeException('Kolom untuk field yang dipilih tidak ditemukan di file: '
                . implode(', ', array_map(fn ($c) => '"' . $c['header'] . '"', $missing))
                . '. Unduh ulang template sesuai field yang dipilih.');
        }

        return [$headerIndex, $map, $warnings];
    }

    /** @return array<string, Item> keyed by uppercase SKU */
    private function loadItems(array $skus): array
    {
        $skus = array_values(array_unique(array_filter($skus, fn ($s) => $s !== '')));
        $items = [];
        foreach (array_chunk($skus, 1000) as $chunk) {
            foreach (Item::whereIn('sku', $chunk)->get() as $item) {
                $items[mb_strtoupper($item->sku)] = $item;
            }
        }

        return $items;
    }

    /**
     * @return array{skip: bool, error: ?string, value: mixed}
     */
    private function parseCell(string $column, array $definition, $raw): array
    {
        $text = $this->cellText($raw);
        if ($text === '') {
            return ['skip' => true, 'error' => null, 'value' => null];
        }

        if (mb_strtoupper($text) === FieldRegistry::CLEAR_KEYWORD) {
            return $definition['nullable']
                ? ['skip' => false, 'error' => null, 'value' => null]
                : ['skip' => false, 'error' => 'field ini wajib diisi, tidak boleh dikosongkan.', 'value' => null];
        }

        $fail = fn (string $message) => ['skip' => false, 'error' => $message, 'value' => null];
        $ok = fn ($value) => ['skip' => false, 'error' => null, 'value' => $value];

        switch ($definition['type']) {
            case 'text':
                return mb_strlen($text) > $definition['max']
                    ? $fail('maksimal ' . number_format($definition['max'], 0, ',', '.') . ' karakter.')
                    : $ok($text);

            case 'reference':
                $refs = $this->references()[$definition['reference']];
                $id = $refs['lookup'][mb_strtolower($text)] ?? null;

                return $id ? $ok($id) : $fail("\"{$text}\" tidak ditemukan di master data.");

            case 'status':
                $value = mb_strtolower(preg_replace('/\s+/', '', $text));
                if (in_array($value, ['aktif', 'active', '1', 'ya', 'yes', 'true'], true)) {
                    return $ok(true);
                }
                if (in_array($value, ['nonaktif', 'tidakaktif', 'inactive', '0', 'tidak', 'no', 'false'], true)) {
                    return $ok(false);
                }

                return $fail('isi dengan "Aktif" atau "Nonaktif".');

            case 'integer':
                $number = $this->toNumber($raw);
                if ($number === null || floor($number) != $number || $number < 0 || $number > self::MAX_INTEGER) {
                    return $fail('harus bilangan bulat >= 0.');
                }

                return $ok((int) $number);

            case 'decimal':
                $number = $this->toNumber($raw);
                if ($number === null || $number <= 0 || $number > self::MAX_DECIMAL) {
                    return $fail('harus angka lebih dari 0.');
                }

                return $ok(round($number, 2));
        }

        return $fail('tipe field tidak dikenal.');
    }

    /** Nilai database dalam bentuk yang bisa dibandingkan dengan hasil parseCell(). */
    private function normalizeCurrent(string $column, $value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($column) {
            'is_active' => (bool) $value,
            'item_category_id', 'item_type_id', 'uom_id', 'low_stock_threshold', 'lead_time_po_days', 'lead_time_safety_days' => (int) $value,
            'panjang_koli', 'lebar_koli', 'tinggi_koli' => round((float) $value, 2),
            default => (string) $value,
        };
    }

    private function describeChanges(array $changes): array
    {
        $refs = $this->references();
        $labels = FieldRegistry::columnLabels();
        $described = [];
        foreach ($changes as $column => $change) {
            $described[] = [
                'column' => $column,
                'label' => $labels[$column] ?? $column,
                'old' => $this->displayValue($column, $change['old'], $refs),
                'new' => $this->displayValue($column, $change['new'], $refs),
            ];
        }

        return $described;
    }

    /** Nilai yang dibaca manusia (nama referensi, Aktif/Nonaktif). $forExcel mempertahankan angka. */
    private function displayValue(string $column, $value, array $refs, bool $forExcel = false)
    {
        if ($value === null) {
            return $forExcel ? null : '(kosong)';
        }

        return match ($column) {
            'item_category_id' => $refs['categories']['labels'][$value] ?? "#{$value}",
            'item_type_id' => $refs['types']['labels'][$value] ?? "#{$value}",
            'uom_id' => $refs['uoms']['labels'][$value] ?? "#{$value}",
            'is_active' => $value ? 'Aktif' : 'Nonaktif',
            'panjang_koli', 'lebar_koli', 'tinggi_koli' => $forExcel ? $value : rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ','),
            'low_stock_threshold', 'lead_time_po_days', 'lead_time_safety_days' => $forExcel ? $value : number_format($value, 0, ',', '.'),
            default => $value,
        };
    }

    private function cellText($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /** Angka dari sel; string boleh memakai koma desimal ("12,5"). */
    private function toNumber($value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $text = str_replace([' ', ','], ['', '.'], trim((string) $value));

        return is_numeric($text) ? (float) $text : null;
    }

    private function rowHasValue(array $row): bool
    {
        foreach ($row as $cell) {
            if ($this->cellText($cell) !== '') {
                return true;
            }
        }

        return false;
    }
}
