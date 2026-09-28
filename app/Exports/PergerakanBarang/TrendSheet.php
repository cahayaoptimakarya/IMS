<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrendSheet extends BaseSheet
{
    public function title(): string
    {
        return 'Tren Keluar';
    }

    protected function build(Worksheet $sheet): void
    {
        $weekly = ($this->report->trend['granularity'] ?? 'daily') === 'weekly';
        $buckets = $this->report->trend['buckets'] ?? [];

        $this->writeTitle($sheet, 'TREN QTY KELUAR ' . ($weekly ? 'MINGGUAN' : 'HARIAN') . ' PER KLASIFIKASI', 'E');
        foreach (['A' => 16, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 14] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $classes = [Analyzer::FAST, Analyzer::MEDIUM, Analyzer::SLOW];
        $this->writeHeader($sheet, 4, [
            $weekly ? 'Minggu (mulai)' : 'Tanggal',
            ...array_map(fn ($c) => Analyzer::LABELS[$c], $classes),
            'Total',
        ]);

        $row = 5;
        foreach ($buckets as $date => $values) {
            $label = Carbon::parse($date)->format('d/m/Y');
            $perClass = array_map(fn ($c) => $values[$c], $classes);
            $sheet->fromArray([$label, ...$perClass, array_sum($perClass)], null, "A{$row}", true);
            $row++;
        }
        $last = $row - 1;
        if ($last < 5) {
            return;
        }

        $sheet->setCellValue("A{$row}", 'Total');
        foreach (['B', 'C', 'D', 'E'] as $col) {
            $sheet->setCellValue("{$col}{$row}", "=SUM({$col}5:{$col}{$last})");
        }
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$row}:E{$row}", 'F5F8FA');
        $this->borderTable($sheet, "A4:E{$row}");
        $sheet->getStyle("A5:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B5:E{$row}")->getNumberFormat()->setFormatCode(self::NUMBER);
        $sheet->freezePane('B5');

        $points = $last - 4;
        $series = [];
        foreach ($classes as $i => $class) {
            $col = $this->column('B', $i);
            $series[] = ['label' => $this->ref($col, 4), 'values' => $this->ref($col, 5, $last), 'color' => self::CLASS_COLORS[$class]];
        }
        $sheet->addChart($this->makeChart(
            'chart_trend_class', 'Qty Keluar ' . ($weekly ? 'per Minggu' : 'per Hari') . ' per Klasifikasi', DataSeries::TYPE_BARCHART,
            $this->ref('A', 5, $last), $points, $series,
            'G4', 'U24', ['grouping' => DataSeries::GROUPING_STACKED]
        ));
        $sheet->addChart($this->makeChart(
            'chart_trend_total', 'Total Qty Keluar ' . ($weekly ? 'per Minggu' : 'per Hari'), DataSeries::TYPE_LINECHART,
            $this->ref('A', 5, $last), $points,
            [['label' => $this->ref('E', 4), 'values' => $this->ref('E', 5, $last), 'color' => '1E1E2D']],
            'G26', 'U42', ['legend' => false]
        ));
    }
}
