<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ParetoSheet extends BaseSheet
{
    private const TOP = 20;

    public function title(): string
    {
        return 'Pareto';
    }

    protected function build(Worksheet $sheet): void
    {
        $r = $this->report;
        $this->writeTitle($sheet, 'ANALISIS PARETO QTY KELUAR', 'I');
        foreach ([10, 16, 36, 15, 13, 12, 12, 11, 12] as $i => $width) {
            $sheet->getColumnDimension($this->column('A', $i))->setWidth($width);
        }

        $moving = $r->movingRows();
        if ($moving->isEmpty()) {
            $sheet->setCellValue('A4', 'Tidak ada qty keluar pada periode ini.');
            return;
        }

        // --- Top 20 ---
        $this->writeSectionTitle($sheet, 4, 'Top ' . self::TOP . ' Item Berdasarkan Qty Keluar', 'I');
        $this->writeHeader($sheet, 5, ['Peringkat', 'SKU', 'Nama Barang', 'Klasifikasi', 'Qty Keluar', 'Kontribusi (%)', 'Kumulatif (%)', 'Frequency (Dok)', 'Days Cover (Hari)']);
        $top = $moving->take(self::TOP)->values();
        $row = 6;
        foreach ($top as $item) {
            $sheet->fromArray([
                $item['rank'], $item['sku'], $item['nama_barang'], null, $item['qty_out'],
                $item['contribution'], $item['cumulative'], $item['frequency'], $item['days_cover'],
            ], null, "A{$row}", true);
            $class = $item['classification'];
            $this->badge($sheet, "D{$row}", Analyzer::LABELS[$class], self::CLASS_COLORS[$class], self::CLASS_FILLS[$class]);
            $row++;
        }
        $lastTop = $row - 1;
        $this->borderTable($sheet, "A5:I{$lastTop}");
        $sheet->getStyle("A6:A{$lastTop}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E6:E{$lastTop}")->getNumberFormat()->setFormatCode(self::NUMBER);
        $sheet->getStyle("F6:G{$lastTop}")->getNumberFormat()->setFormatCode(self::PERCENT);
        $sheet->getStyle("H6:H{$lastTop}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("I6:I{$lastTop}")->getNumberFormat()->setFormatCode(self::DAYS);

        // --- Ambang kontribusi ---
        $start = $lastTop + 2;
        $this->writeSectionTitle($sheet, $start, 'Jumlah SKU untuk Mencapai Ambang Kontribusi Qty Keluar', 'I');
        $this->writeHeader($sheet, $start + 1, ['Ambang', 'Jumlah SKU', '% dari SKU Bergerak']);
        $row = $start + 2;
        foreach ([50, 70, 80, 90, 100] as $threshold) {
            $needed = $moving->search(fn ($item) => $item['cumulative'] >= $threshold - 1e-9);
            $count = $needed === false ? $moving->count() : $needed + 1;
            $sheet->fromArray([$threshold . '%', $count, $count / $moving->count() * 100], null, "A{$row}", true);
            $row++;
        }
        $this->borderTable($sheet, 'A' . ($start + 1) . ':C' . ($row - 1));
        $sheet->getStyle('A' . ($start + 2) . ':B' . ($row - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . ($start + 2) . ':C' . ($row - 1))->getNumberFormat()->setFormatCode(self::PERCENT);

        // --- Charts ---
        $sheet->addChart($this->makeChart(
            'chart_top_out', 'Top ' . $top->count() . ' Qty Keluar', DataSeries::TYPE_BARCHART,
            $this->ref('B', 6, $lastTop), $top->count(),
            [['label' => $this->ref('E', 5), 'values' => $this->ref('E', 6, $lastTop), 'color' => '009EF7']],
            'K4', 'T28', ['legend' => false, 'direction' => DataSeries::DIRECTION_BAR]
        ));

        // Kurva Pareto seluruh item bergerak diambil dari sheet Detail (baris sudah urut peringkat)
        $detailFirst = DetailSheet::HEADER_ROW + 1;
        $detailLast = DetailSheet::HEADER_ROW + $moving->count();
        $sheet->addChart($this->makeChart(
            'chart_pareto', 'Kurva Pareto - Kontribusi Kumulatif (%) per Peringkat Item', DataSeries::TYPE_LINECHART,
            $this->ref('A', $detailFirst, $detailLast, DetailSheet::TITLE), $moving->count(),
            [['label' => $this->ref('J', DetailSheet::HEADER_ROW, null, DetailSheet::TITLE), 'values' => $this->ref('J', $detailFirst, $detailLast, DetailSheet::TITLE), 'color' => 'F1416C']],
            'K30', 'T48', ['legend' => false, 'x_title' => 'Peringkat item', 'y_title' => 'Kumulatif (%)']
        ));
    }
}
