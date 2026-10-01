<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DetailSheet extends BaseSheet
{
    public const TITLE = 'Detail Item';
    public const HEADER_ROW = 4;

    public function title(): string
    {
        return self::TITLE;
    }

    protected function build(Worksheet $sheet): void
    {
        $r = $this->report;
        $this->writeTitle($sheet, 'DETAIL KLASIFIKASI PERGERAKAN BARANG', 'R');

        $headers = [
            'No', 'SKU', 'Nama Barang', 'Satuan', 'Stok Saat Ini', 'Klasifikasi', 'Qty Keluar', 'Rata-rata / Hari',
            'Kontribusi (%)', 'Kumulatif (%)', 'Frequency (Dok)', 'Days Cover (Hari)', 'Status Days Cover',
            'Tanggal Terakhir Keluar', 'Hari Sejak Terakhir Keluar',
            'Dalam Order', 'Rekomendasi Order (Koli)', 'Rekomendasi Order (Qty)',
        ];
        $this->writeHeader($sheet, self::HEADER_ROW, $headers);
        // Lebar menyisakan ruang untuk tombol autofilter di header
        $widths = [7, 16, 38, 10, 15, 17, 14, 15, 15, 15, 14, 14, 22, 16, 17, 14, 16, 16];
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimension($this->column('A', $i))->setWidth($width);
        }

        $first = self::HEADER_ROW + 1;
        $data = [];
        foreach ($r->rows as $i => $row) {
            $data[] = [
                $i + 1,
                $row['sku'],
                $row['nama_barang'],
                $row['uom'],
                $row['stock'],
                Analyzer::LABELS[$row['classification']],
                $row['qty_out'],
                $row['avg_per_day'],
                $row['contribution'],
                $row['cumulative'],
                $row['frequency'],
                $row['days_cover'],
                Analyzer::COVER_LABELS[$row['cover_status']],
                $row['last_out_date'] ? Date::stringToExcel($row['last_out_date']) : 'Belum pernah',
                $r->daysSinceLastOut($row),
                $row['on_order_qty'] ?? 0,
                $row['order_koli'] ?? 0,
                $row['order_qty'] ?? 0,
            ];
        }

        if (empty($data)) {
            $sheet->setCellValue("A{$first}", 'Tidak ada data untuk gudang dan periode ini.');
            $sheet->mergeCells("A{$first}:R{$first}");
            return;
        }

        $sheet->fromArray($data, null, "A{$first}", true);
        $last = $first + count($data) - 1;

        $this->borderTable($sheet, 'A' . self::HEADER_ROW . ":R{$last}");
        $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$first}:B{$last}")->getFont()->setBold(true);
        foreach (['E' => self::NUMBER, 'G' => self::NUMBER, 'H' => self::NUMBER_2, 'I' => self::PERCENT, 'J' => self::PERCENT, 'K' => '#,##0', 'L' => self::DAYS, 'N' => self::DATE, 'O' => '#,##0', 'P' => self::NUMBER, 'Q' => '#,##0', 'R' => self::NUMBER] as $col => $format) {
            $sheet->getStyle("{$col}{$first}:{$col}{$last}")->getNumberFormat()->setFormatCode($format);
        }
        $sheet->getStyle("F{$first}:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("M{$first}:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Baris urut per peringkat sehingga tiap klasifikasi berurutan -> warnai per blok
        $cursor = $first;
        foreach (array_keys(Analyzer::LABELS) as $class) {
            $count = $r->rows->where('classification', $class)->count();
            if ($count === 0) {
                continue;
            }
            $range = "F{$cursor}:F" . ($cursor + $count - 1);
            $this->fill($sheet, $range, self::CLASS_FILLS[$class]);
            $color = self::CLASS_COLORS[$class];
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB($color === 'FFC700' ? 'B58A00' : $color);
            $cursor += $count;
        }

        // Status days cover tidak berurutan -> conditional formatting
        $conditions = [];
        foreach (Analyzer::COVER_LABELS as $status => $label) {
            $conditional = new Conditional();
            $conditional->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_EQUAL)
                ->addCondition('"' . $label . '"');
            $conditional->getStyle()->getFont()->setBold(true)->getColor()->setRGB(
                self::COVER_COLORS[$status] === 'FFC700' ? 'B58A00' : self::COVER_COLORS[$status]
            );
            $conditional->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB(self::COVER_FILLS[$status]);
            $conditions[] = $conditional;
        }
        $sheet->getStyle("M{$first}:M{$last}")->setConditionalStyles($conditions);

        $sheet->getStyle("G{$first}:G{$last}")->getFont()->setBold(true);
        $sheet->getStyle("Q{$first}:R{$last}")->getFont()->setBold(true)->getColor()->setRGB('0BB783');

        $sheet->setAutoFilter('A' . self::HEADER_ROW . ":R{$last}");
        $sheet->freezePane('C' . $first);
    }
}
