<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttentionSheet extends BaseSheet
{
    private const TARGET_COVER_DAYS = Analyzer::COVER_WARNING_DAYS;
    private const MAX_COVER_DAYS = Analyzer::COVER_EXCESS_DAYS;

    public function title(): string
    {
        return 'Perlu Perhatian';
    }

    protected function build(Worksheet $sheet): void
    {
        $r = $this->report;
        $this->writeTitle($sheet, 'ITEM YANG PERLU PERHATIAN', 'J');
        foreach ([6, 16, 36, 15, 13, 13, 12, 18, 16, 16] as $i => $width) {
            $sheet->getColumnDimension($this->column('A', $i))->setWidth($width);
        }

        $row = 4;

        // A. Risiko kehabisan stok: fast/medium dengan days cover < 30 hari
        $risk = $r->rows
            ->whereIn('classification', [Analyzer::FAST, Analyzer::MEDIUM])
            ->whereIn('cover_status', [Analyzer::COVER_CRITICAL, Analyzer::COVER_WARNING])
            ->sortBy('days_cover')->values();
        $row = $this->section(
            $sheet, $row,
            'A. Risiko Kehabisan Stok - Fast/Medium Moving dengan Days Cover < ' . self::TARGET_COVER_DAYS . ' hari',
            ['No', 'SKU', 'Nama Barang', 'Klasifikasi', 'Stok Saat Ini', 'Rata-rata / Hari', 'Days Cover (Hari)', 'Status', 'Kebutuhan ' . self::TARGET_COVER_DAYS . ' Hari', 'Estimasi Kekurangan'],
            $risk,
            fn ($item) => [
                $item['stock'], $item['avg_per_day'], $item['days_cover'], Analyzer::COVER_LABELS[$item['cover_status']],
                $item['avg_per_day'] * self::TARGET_COVER_DAYS,
                max(0, $item['avg_per_day'] * self::TARGET_COVER_DAYS - $item['stock']),
            ],
            ['E' => self::NUMBER, 'F' => self::NUMBER_2, 'G' => self::DAYS, 'I' => self::NUMBER_2, 'J' => self::NUMBER_2],
            'Tidak ada item Fast/Medium Moving dengan days cover di bawah ' . self::TARGET_COVER_DAYS . ' hari.',
            'Estimasi kekurangan = (rata-rata keluar per hari x ' . self::TARGET_COVER_DAYS . ' hari) - stok saat ini.'
        );

        // B. Stok berlebih: item bergerak dengan days cover > 90 hari
        $excess = $r->rows->where('cover_status', Analyzer::COVER_EXCESS)->sortByDesc('days_cover')->values();
        $row = $this->section(
            $sheet, $row,
            'B. Stok Berlebih - Days Cover > ' . self::MAX_COVER_DAYS . ' hari',
            ['No', 'SKU', 'Nama Barang', 'Klasifikasi', 'Stok Saat Ini', 'Rata-rata / Hari', 'Days Cover (Hari)', 'Stok untuk ' . self::MAX_COVER_DAYS . ' Hari', 'Kelebihan Stok', 'Frequency (Dok)'],
            $excess,
            fn ($item) => [
                $item['stock'], $item['avg_per_day'], $item['days_cover'],
                $item['avg_per_day'] * self::MAX_COVER_DAYS,
                max(0, $item['stock'] - $item['avg_per_day'] * self::MAX_COVER_DAYS),
                $item['frequency'],
            ],
            ['E' => self::NUMBER, 'F' => self::NUMBER_2, 'G' => self::DAYS, 'H' => self::NUMBER_2, 'I' => self::NUMBER_2, 'J' => '#,##0'],
            'Tidak ada item dengan stok berlebih.',
            'Kelebihan stok = stok saat ini - (rata-rata keluar per hari x ' . self::MAX_COVER_DAYS . ' hari).'
        );

        // C. Non moving: urut stok terbesar
        $nonMoving = $r->rows->where('classification', Analyzer::NON_MOVING)->sortByDesc('stock')->values();
        $this->section(
            $sheet, $row,
            'C. Non Moving - Ada Stok tanpa Pengiriman pada Periode',
            ['No', 'SKU', 'Nama Barang', 'Klasifikasi', 'Stok Saat Ini', 'Satuan', 'Terakhir Keluar', 'Hari Sejak Terakhir Keluar'],
            $nonMoving,
            fn ($item) => [
                $item['stock'], $item['uom'],
                $item['last_out_date'] ? Date::stringToExcel($item['last_out_date']) : 'Belum pernah',
                $r->daysSinceLastOut($item),
            ],
            ['E' => self::NUMBER, 'G' => self::DATE, 'H' => '#,##0'],
            'Tidak ada item Non Moving.'
        );

        $sheet->freezePane('A4');
    }

    /**
     * Tulis satu bagian tabel; kolom A-D tetap (No, SKU, Nama, Klasifikasi), sisanya dari $values.
     * Mengembalikan baris awal untuk bagian berikutnya.
     */
    private function section(
        Worksheet $sheet,
        int $row,
        string $title,
        array $headers,
        Collection $items,
        callable $values,
        array $formats,
        string $emptyMessage,
        ?string $note = null
    ): int {
        $lastColumn = $this->column('A', count($headers) - 1);
        $this->writeSectionTitle($sheet, $row, $title . '  (' . $items->count() . ' SKU)', 'J');
        $row++;
        if ($note) {
            $sheet->setCellValue("A{$row}", $note);
            $sheet->mergeCells("A{$row}:J{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('7E8299');
            $row++;
        }
        $headerRow = $row;
        $this->writeHeader($sheet, $headerRow, $headers);
        $row++;

        if ($items->isEmpty()) {
            $sheet->setCellValue("A{$row}", $emptyMessage);
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $this->borderTable($sheet, "A{$headerRow}:{$lastColumn}{$row}");
            return $row + 3;
        }

        $first = $row;
        foreach ($items as $i => $item) {
            $sheet->fromArray([$i + 1, $item['sku'], $item['nama_barang'], null, ...$values($item)], null, "A{$row}", true);
            $class = $item['classification'];
            $this->badge($sheet, "D{$row}", Analyzer::LABELS[$class], self::CLASS_COLORS[$class], self::CLASS_FILLS[$class]);
            $row++;
        }
        $last = $row - 1;

        $this->borderTable($sheet, "A{$headerRow}:{$lastColumn}{$last}");
        $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$first}:B{$last}")->getFont()->setBold(true);
        foreach ($formats as $col => $format) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")->getNumberFormat()->setFormatCode($format);
        }

        return $row + 2;
    }
}
