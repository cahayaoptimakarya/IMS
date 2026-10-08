<?php

namespace App\Support;

use App\Models\Item;

/**
 * Perhitungan CBM (m³) baris dokumen (request, pengadaan, pengiriman) dari dimensi koli item.
 * Dihitung saat ditampilkan, tidak disimpan: data lama otomatis ikut jika dimensi item diisi.
 */
final class Cbm
{
    /**
     * CBM satu baris. Koli dipakai sebagai acuan; jika koli kosong, dihitung dari qty / isi per koli.
     * Null jika dimensi koli item belum lengkap.
     */
    public static function forLine(?Item $item, $koli, $quantity = null): ?float
    {
        $perKoli = $item?->cbm_per_koli;
        if ($perKoli === null) {
            return null;
        }

        $koli = (float) ($koli ?? 0);
        if ($koli > 0) {
            return $koli * $perKoli;
        }

        $quantity = (float) ($quantity ?? 0);
        $perUnit = $item->cbm_per_unit;

        return ($quantity > 0 && $perUnit !== null) ? $quantity * $perUnit : 0.0;
    }

    /**
     * Ringkasan CBM untuk banyak baris. Item tanpa dimensi koli tidak ikut dihitung (kontribusi 0).
     *
     * @param  iterable  $rows  Koleksi baris dokumen
     * @param  callable  $map   fn ($row) => [?Item $item, $koli, $quantity]
     * @return array{total: float, missing: int}
     */
    public static function summarize(iterable $rows, callable $map): array
    {
        $total = 0.0;
        $missing = 0;

        foreach ($rows as $row) {
            [$item, $koli, $quantity] = $map($row);
            $cbm = self::forLine($item, $koli, $quantity);
            if ($cbm === null) {
                $missing++;
                continue;
            }
            $total += $cbm;
        }

        return ['total' => $total, 'missing' => $missing];
    }

    /**
     * Format angka CBM gaya Indonesia (3 desimal; nilai sangat kecil sampai 6 desimal). '-' jika null.
     */
    public static function format(?float $value, int $decimals = 3): string
    {
        if ($value === null) {
            return '-';
        }

        if ($value > 0 && round($value, $decimals) == 0) {
            return rtrim(rtrim(number_format($value, 6, ',', '.'), '0'), ',');
        }

        return number_format($value, $decimals, ',', '.');
    }
}
