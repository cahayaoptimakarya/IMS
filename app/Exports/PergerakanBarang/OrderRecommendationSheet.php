<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Daftar item yang perlu dipesan agar stok mencukupi days cover needed.
 */
class OrderRecommendationSheet extends BaseSheet
{
    public const TITLE = 'Rekomendasi Order';
    public const HEADER_ROW = 5;

    public function title(): string
    {
        return self::TITLE;
    }

    protected function build(Worksheet $sheet): void
    {
        $r = $this->report;
        $days = $r->daysNeededLabel();
        $this->writeTitle($sheet, 'REKOMENDASI ORDER - DAYS COVER NEEDED ' . $days . ' HARI', 'N');

        $sheet->setCellValue('A3', "Rekomendasi = (rata-rata keluar per hari x {$days} hari) - stok saat ini - qty dalam order, dibulatkan ke atas per koli.");
        $sheet->mergeCells('A3:N3');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('7E8299');

        $headers = [
            'No', 'SKU', 'Nama Barang', 'Satuan', 'Klasifikasi', 'Stok Saat Ini', 'Dalam Order', 'Rata-rata / Hari',
            'Days Cover (Hari)', "Target Stok {$days} Hari", 'Kebutuhan', 'Isi / Koli', 'Rekomendasi (Koli)', 'Rekomendasi (Qty)',
        ];
        $this->writeHeader($sheet, self::HEADER_ROW, $headers);
        foreach ([6, 16, 36, 9, 16, 14, 13, 14, 14, 16, 14, 10, 16, 16] as $i => $width) {
            $sheet->getColumnDimension($this->column('A', $i))->setWidth($width);
        }

        $first = self::HEADER_ROW + 1;
        $items = $r->orderRows();
        if ($items->isEmpty()) {
            $sheet->setCellValue("A{$first}", 'Tidak ada item yang perlu dipesan untuk days cover needed ini.');
            $sheet->mergeCells("A{$first}:N{$first}");
            $sheet->getStyle("A{$first}")->getFont()->setItalic(true);
            $this->borderTable($sheet, 'A' . self::HEADER_ROW . ":N{$first}");
            return;
        }

        $row = $first;
        foreach ($items as $i => $item) {
            $sheet->fromArray([
                $i + 1, $item['sku'], $item['nama_barang'], $item['uom'], null,
                $item['stock'], $item['on_order_qty'], $item['avg_per_day'], $item['days_cover'],
                $item['target_qty'], $item['need_qty'], $item['per_koli'], $item['order_koli'], $item['order_qty'],
            ], null, "A{$row}", true);
            $class = $item['classification'];
            $this->badge($sheet, "E{$row}", Analyzer::LABELS[$class], self::CLASS_COLORS[$class], self::CLASS_FILLS[$class]);
            $row++;
        }
        $last = $row - 1;

        // Baris total
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:L{$row}");
        $sheet->setCellValue("M{$row}", "=SUM(M{$first}:M{$last})");
        $sheet->setCellValue("N{$row}", "=SUM(N{$first}:N{$last})");
        $sheet->getStyle("A{$row}:N{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->fill($sheet, "A{$row}:N{$row}", 'F5F8FA');

        $this->borderTable($sheet, 'A' . self::HEADER_ROW . ":N{$row}");
        $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$first}:B{$last}")->getFont()->setBold(true);
        foreach (['F' => self::NUMBER, 'G' => self::NUMBER, 'H' => self::NUMBER_2, 'I' => self::DAYS, 'J' => self::NUMBER_2, 'K' => self::NUMBER_2, 'L' => '#,##0', 'M' => '#,##0', 'N' => self::NUMBER] as $col => $format) {
            $sheet->getStyle("{$col}{$first}:{$col}{$row}")->getNumberFormat()->setFormatCode($format);
        }
        $this->fill($sheet, "M{$first}:N{$last}", 'E8FFF3');
        $sheet->getStyle("M{$first}:N{$last}")->getFont()->setBold(true);

        $sheet->setAutoFilter('A' . self::HEADER_ROW . ":N{$last}");
        $sheet->freezePane('C' . $first);
    }
}
