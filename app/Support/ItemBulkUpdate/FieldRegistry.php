<?php

namespace App\Support\ItemBulkUpdate;

/**
 * Daftar field master item yang boleh di-update massal via Excel.
 * SKU (kunci pencocokan) dan Koli sengaja tidak ada di sini sehingga tidak pernah bisa diubah.
 */
class FieldRegistry
{
    /** Isi sel dengan kata kunci ini untuk mengosongkan field yang boleh kosong. */
    public const CLEAR_KEYWORD = '#KOSONG';

    public const SKU_HEADER = 'SKU';

    /** Kolom kosong di template yang hanya informasi (tidak diimpor). */
    public const INFO_PREFIX = 'Info: ';

    public const LOCKED = [
        'sku' => ['label' => 'SKU', 'reason' => 'Kunci pencocokan data, tidak dapat diubah'],
        'koli' => ['label' => 'Koli (isi per koli)', 'reason' => 'Tidak dapat diubah melalui update massal'],
    ];

    public const GROUPS = [
        'dasar' => 'Informasi Dasar',
        'stok' => 'Stok & Pengadaan',
        'lokasi' => 'Lokasi & Keterangan',
        'dimensi' => 'Dimensi Koli (CBM)',
    ];

    /**
     * key => definisi. Satu field bisa terdiri dari beberapa kolom Excel (mis. dimensi P/L/T).
     * type: text | reference | status | integer | decimal
     */
    public static function all(): array
    {
        return [
            'nama_barang' => [
                'label' => 'Nama Barang', 'group' => 'dasar', 'icon' => 'fa-tag',
                'columns' => ['nama_barang' => 'Nama Barang'],
                'type' => 'text', 'max' => 255, 'nullable' => false,
                'hint' => 'Teks, maks. 255 karakter',
            ],
            'item_category_id' => [
                'label' => 'Kategori', 'group' => 'dasar', 'icon' => 'fa-layer-group',
                'columns' => ['item_category_id' => 'Kategori'],
                'type' => 'reference', 'reference' => 'categories', 'nullable' => false,
                'hint' => 'Nama kategori (pilih dari daftar)',
            ],
            'item_type_id' => [
                'label' => 'Tipe Item', 'group' => 'dasar', 'icon' => 'fa-shapes',
                'columns' => ['item_type_id' => 'Tipe Item'],
                'type' => 'reference', 'reference' => 'types', 'nullable' => false,
                'hint' => 'Nama tipe item (pilih dari daftar)',
            ],
            'uom_id' => [
                'label' => 'UOM', 'group' => 'dasar', 'icon' => 'fa-ruler',
                'columns' => ['uom_id' => 'UOM'],
                'type' => 'reference', 'reference' => 'uoms', 'nullable' => false,
                'hint' => 'Nama atau simbol UOM (pilih dari daftar)',
            ],
            'is_active' => [
                'label' => 'Status', 'group' => 'dasar', 'icon' => 'fa-toggle-on',
                'columns' => ['is_active' => 'Status'],
                'type' => 'status', 'nullable' => false,
                'hint' => 'Aktif / Nonaktif',
            ],
            'low_stock_threshold' => [
                'label' => 'Minimal Stok Menipis', 'group' => 'stok', 'icon' => 'fa-battery-quarter',
                'columns' => ['low_stock_threshold' => 'Minimal Stok Menipis'],
                'type' => 'integer', 'nullable' => false,
                'hint' => 'Bilangan bulat >= 0',
            ],
            'lead_time_po_days' => [
                'label' => 'Lead Time PO', 'group' => 'stok', 'icon' => 'fa-truck',
                'columns' => ['lead_time_po_days' => 'Lead Time PO (hari)'],
                'type' => 'integer', 'nullable' => false,
                'hint' => 'Hari, bilangan bulat >= 0',
            ],
            'lead_time_safety_days' => [
                'label' => 'Lead Time Safety', 'group' => 'stok', 'icon' => 'fa-shield-alt',
                'columns' => ['lead_time_safety_days' => 'Lead Time Safety (hari)'],
                'type' => 'integer', 'nullable' => false,
                'hint' => 'Hari, bilangan bulat >= 0',
            ],
            'tempat' => [
                'label' => 'Tempat', 'group' => 'lokasi', 'icon' => 'fa-map-marker-alt',
                'columns' => ['tempat' => 'Tempat'],
                'type' => 'text', 'max' => 255, 'nullable' => true,
                'hint' => 'Teks, maks. 255 karakter. ' . self::CLEAR_KEYWORD . ' untuk mengosongkan',
            ],
            'deskripsi' => [
                'label' => 'Deskripsi', 'group' => 'lokasi', 'icon' => 'fa-align-left',
                'columns' => ['deskripsi' => 'Deskripsi'],
                'type' => 'text', 'max' => 65535, 'nullable' => true,
                'hint' => 'Teks bebas. ' . self::CLEAR_KEYWORD . ' untuk mengosongkan',
            ],
            'dimensi_koli' => [
                'label' => 'Dimensi Koli (P x L x T)', 'group' => 'dimensi', 'icon' => 'fa-cube',
                'columns' => [
                    'panjang_koli' => 'Panjang Koli (cm)',
                    'lebar_koli' => 'Lebar Koli (cm)',
                    'tinggi_koli' => 'Tinggi Koli (cm)',
                ],
                'type' => 'decimal', 'nullable' => true,
                'hint' => 'cm, angka > 0. ' . self::CLEAR_KEYWORD . ' untuk mengosongkan. Hasil akhir P, L, T harus lengkap atau kosong semua',
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * Kolom Excel berurutan sesuai urutan registry untuk field yang dipilih.
     *
     * @return array<string, array{header: string, field: string}>
     */
    public static function columnsFor(array $fieldKeys): array
    {
        $columns = [];
        foreach (self::all() as $key => $definition) {
            if (!in_array($key, $fieldKeys, true)) {
                continue;
            }
            foreach ($definition['columns'] as $column => $header) {
                $columns[$column] = ['header' => $header, 'field' => $key];
            }
        }

        return $columns;
    }

    /** Label kolom per nama kolom database, untuk seluruh registry. */
    public static function columnLabels(): array
    {
        $labels = [];
        foreach (self::all() as $definition) {
            foreach ($definition['columns'] as $column => $header) {
                $labels[$column] = $header;
            }
        }

        return $labels;
    }

    /** Normalisasi header: huruf kecil tanpa spasi/simbol, mis. "Lead Time PO (hari)" -> "leadtimepohari". */
    public static function normalizeHeader(?string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $header)));
    }
}
