<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet laporan yang ditulis langsung lewat PhpSpreadsheet saat AfterSheet.
 * Chart ditambahkan di build(); WithCharts hanya dipakai agar writer menyertakan chart.
 */
abstract class BaseSheet implements WithTitle, WithEvents, WithCharts
{
    public const NUMBER = '#,##0.00';
    public const NUMBER_2 = '#,##0.00';
    public const PERCENT = '0.00"%"';
    public const DAYS = '#,##0.0';
    public const DATE = 'dd mmm yyyy';

    public const CLASS_COLORS = [
        Analyzer::FAST => '50CD89',
        Analyzer::MEDIUM => '009EF7',
        Analyzer::SLOW => 'FFC700',
        Analyzer::NON_MOVING => 'F1416C',
    ];

    public const CLASS_FILLS = [
        Analyzer::FAST => 'E8FFF3',
        Analyzer::MEDIUM => 'E1F3FF',
        Analyzer::SLOW => 'FFF8DD',
        Analyzer::NON_MOVING => 'FFF5F8',
    ];

    public const COVER_COLORS = [
        Analyzer::COVER_CRITICAL => 'F1416C',
        Analyzer::COVER_WARNING => 'FFC700',
        Analyzer::COVER_SAFE => '50CD89',
        Analyzer::COVER_EXCESS => '7239EA',
        Analyzer::COVER_NONE => 'A1A5B7',
    ];

    public const COVER_FILLS = [
        Analyzer::COVER_CRITICAL => 'FFF5F8',
        Analyzer::COVER_WARNING => 'FFF8DD',
        Analyzer::COVER_SAFE => 'E8FFF3',
        Analyzer::COVER_EXCESS => 'F8F5FF',
        Analyzer::COVER_NONE => 'F5F8FA',
    ];

    private const HEADER_FILL = '1E1E2D';

    public function __construct(protected ReportData $report)
    {
    }

    abstract protected function build(Worksheet $sheet): void;

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
                $sheet->setShowGridlines(false);
                $this->build($sheet);
                // Writer tidak me-refresh chart karena pre_calculate_formulas nonaktif;
                // isi cache nilai di sini agar chart & warna per titik ikut tertulis.
                foreach ($sheet->getChartCollection() as $chart) {
                    $chart->refresh();
                }
                $sheet->setSelectedCell('A1');
            },
        ];
    }

    public function charts()
    {
        return [];
    }

    /**
     * Judul sheet + baris subjudul (gudang & periode). Mengembalikan baris berikutnya yang kosong.
     */
    protected function writeTitle(Worksheet $sheet, string $title, string $lastColumn, ?string $subtitle = null): int
    {
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB(self::HEADER_FILL);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->setCellValue('A2', $subtitle ?? $this->report->contextLine());
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('5E6278');

        return 4;
    }

    protected function writeSectionTitle(Worksheet $sheet, int $row, string $title, string $lastColumn = 'H'): void
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB(self::HEADER_FILL);
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('009EF7');
    }

    /**
     * @param  string[]  $headers
     */
    protected function writeHeader(Worksheet $sheet, int $row, array $headers, string $startColumn = 'A'): string
    {
        $sheet->fromArray($headers, null, "{$startColumn}{$row}");
        $lastColumn = $this->column($startColumn, count($headers) - 1);
        $range = "{$startColumn}{$row}:{$lastColumn}{$row}";

        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_FILL);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(30);

        return $lastColumn;
    }

    protected function borderTable(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    protected function fill(Worksheet $sheet, string $range, string $rgb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    /** Sel label berwarna (klasifikasi / status days cover). */
    protected function badge(Worksheet $sheet, string $cell, string $text, string $color, string $fillColor): void
    {
        $sheet->setCellValue($cell, $text);
        $style = $sheet->getStyle($cell);
        $style->getFont()->setBold(true)->getColor()->setRGB($color === 'FFC700' ? 'B58A00' : $color);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fillColor);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function column(string $start, int $offset): string
    {
        $index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($start) + $offset;

        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }

    /** Referensi absolut untuk data chart, mis. 'Ringkasan'!$B$5:$B$8 */
    protected function ref(string $column, int $fromRow, ?int $toRow = null, ?string $sheetTitle = null): string
    {
        $sheetTitle ??= $this->title();
        $ref = "'{$sheetTitle}'!\${$column}\${$fromRow}";

        return $toRow !== null && $toRow !== $fromRow ? "{$ref}:\${$column}\${$toRow}" : $ref;
    }

    /**
     * @param  array<int, array{label: string, values: string, color?: string|string[]}>  $series
     */
    protected function makeChart(
        string $name,
        string $title,
        string $type,
        string $categories,
        int $pointCount,
        array $series,
        string $topLeft,
        string $bottomRight,
        array $options = []
    ): Chart {
        $labels = [];
        $values = [];
        $cats = [];
        foreach ($series as $item) {
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $item['label'], null, 1);
            $value = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $item['values'], null, $pointCount);
            if (!empty($item['color'])) {
                $value->setFillColor($item['color']);
            }
            if ($type === DataSeries::TYPE_LINECHART) {
                $value->setLineWidth(28575); // 2.25pt
                $value->setPointMarker('none');
            }
            $values[] = $value;
            $cats[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $categories, null, $pointCount);
        }

        $grouping = match ($type) {
            DataSeries::TYPE_PIECHART, DataSeries::TYPE_DONUTCHART => null,
            DataSeries::TYPE_BARCHART => $options['grouping'] ?? DataSeries::GROUPING_CLUSTERED,
            default => DataSeries::GROUPING_STANDARD,
        };

        $dataSeries = new DataSeries($type, $grouping, range(0, count($values) - 1), $labels, $cats, $values);
        if ($type === DataSeries::TYPE_BARCHART) {
            $dataSeries->setPlotDirection($options['direction'] ?? DataSeries::DIRECTION_COL);
        }

        $layout = new Layout();
        if (in_array($type, [DataSeries::TYPE_PIECHART, DataSeries::TYPE_DONUTCHART], true)) {
            $layout->setShowPercent(true)->setShowVal(false);
        } elseif (!empty($options['show_values'])) {
            $layout->setShowVal(true);
        }

        $legend = ($options['legend'] ?? true)
            ? new Legend($options['legend_position'] ?? Legend::POSITION_BOTTOM, null, false)
            : null;

        $chart = new Chart(
            $name,
            new Title($title),
            $legend,
            new PlotArea($layout, [$dataSeries]),
            true,
            DataSeries::EMPTY_AS_GAP,
            isset($options['x_title']) ? new Title($options['x_title']) : null,
            isset($options['y_title']) ? new Title($options['y_title']) : null
        );
        $chart->setTopLeftPosition($topLeft);
        $chart->setBottomRightPosition($bottomRight);

        return $chart;
    }
}
