<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SummarySheet extends BaseSheet
{
    private const CLASS_CRITERIA = [
        Analyzer::FAST => 'Kumulatif 0 - 70% qty keluar',
        Analyzer::MEDIUM => 'Kumulatif 70 - 90% qty keluar',
        Analyzer::SLOW => 'Kumulatif > 90% qty keluar',
        Analyzer::NON_MOVING => 'Ada stok, tanpa qty keluar',
    ];

    private const COVER_ACTIONS = [
        Analyzer::COVER_CRITICAL => 'Segera replenish / buat permintaan pengadaan',
        Analyzer::COVER_WARNING => 'Jadwalkan pengadaan dalam waktu dekat',
        Analyzer::COVER_SAFE => 'Pertahankan level stok',
        Analyzer::COVER_EXCESS => 'Tahan pembelian, evaluasi level stok',
        Analyzer::COVER_NONE => 'Evaluasi: promosi, pindah gudang, atau retur',
    ];

    public function title(): string
    {
        return 'Ringkasan';
    }

    protected function build(Worksheet $sheet): void
    {
        $r = $this->report;
        $this->writeTitle($sheet, 'LAPORAN PERGERAKAN BARANG (FAST / MEDIUM / SLOW / NON MOVING)', 'H');

        foreach (['A' => 22, 'B' => 28, 'C' => 13, 'D' => 11, 'E' => 15, 'F' => 13, 'G' => 15, 'H' => 16] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // --- KPI ---
        $this->writeSectionTitle($sheet, 4, 'Indikator Utama');
        $moving = $r->movingRows();
        $kpis = [
            ['A', 'B', 'Total Qty Keluar', $r->totalOut, self::NUMBER],
            ['C', 'D', 'Rata-rata Keluar / Hari', $r->totalOut / max(1, $r->periodDays), self::NUMBER_2],
            ['E', 'F', 'SKU Bergerak', $moving->count(), '#,##0'],
            ['G', 'H', 'SKU Non Moving', $r->summary[Analyzer::NON_MOVING]['sku_count'], '#,##0'],
        ];
        foreach ($kpis as [$from, $to, $label, $value, $format]) {
            $sheet->mergeCells("{$from}5:{$to}5");
            $sheet->mergeCells("{$from}6:{$to}6");
            $sheet->setCellValue("{$from}5", $label);
            $sheet->setCellValue("{$from}6", $value);
            $sheet->getStyle("{$from}5")->getFont()->setBold(true)->getColor()->setRGB('7E8299');
            $sheet->getStyle("{$from}6")->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle("{$from}6")->getNumberFormat()->setFormatCode($format);
            $sheet->getStyle("{$from}5:{$to}6")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $this->fill($sheet, "{$from}5:{$to}6", 'F5F8FA');
        }
        $sheet->getRowDimension(6)->setRowHeight(26);

        // --- Ringkasan per klasifikasi ---
        $this->writeSectionTitle($sheet, 8, 'Ringkasan per Klasifikasi');
        $this->writeHeader($sheet, 9, ['Klasifikasi', 'Kriteria', 'Jumlah SKU', '% SKU', 'Qty Keluar', '% Qty Keluar', 'Stok Saat Ini', 'Median Days Cover (hari)']);
        $totalSku = max(1, $r->rows->count());
        $row = 10;
        foreach (Analyzer::LABELS as $class => $label) {
            $data = $r->summary[$class];
            $this->badge($sheet, "A{$row}", $label, self::CLASS_COLORS[$class], self::CLASS_FILLS[$class]);
            $sheet->fromArray([
                self::CLASS_CRITERIA[$class],
                $data['sku_count'],
                $data['sku_count'] / $totalSku * 100,
                $data['qty_out'],
                $data['qty_share'],
                $data['stock'],
                $r->medianCover($class),
            ], null, "B{$row}", true);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("C{$row}", '=SUM(C10:C13)');
        $sheet->setCellValue("D{$row}", '=SUM(D10:D13)');
        $sheet->setCellValue("E{$row}", '=SUM(E10:E13)');
        $sheet->setCellValue("F{$row}", '=SUM(F10:F13)');
        $sheet->setCellValue("G{$row}", '=SUM(G10:G13)');
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$row}:H{$row}", 'F5F8FA');
        $this->borderTable($sheet, "A9:H{$row}");
        $sheet->getStyle("C10:C{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("D10:D{$row}")->getNumberFormat()->setFormatCode(self::PERCENT);
        $sheet->getStyle("E10:E{$row}")->getNumberFormat()->setFormatCode(self::NUMBER);
        $sheet->getStyle("F10:F{$row}")->getNumberFormat()->setFormatCode(self::PERCENT);
        $sheet->getStyle("G10:G{$row}")->getNumberFormat()->setFormatCode(self::NUMBER);
        $sheet->getStyle("H10:H{$row}")->getNumberFormat()->setFormatCode(self::DAYS);

        // --- Distribusi days cover ---
        $this->writeSectionTitle($sheet, 16, 'Distribusi Days Cover (Stok Saat Ini / Rata-rata Keluar per Hari)');
        $this->writeHeader($sheet, 17, ['Status Days Cover', 'Jumlah SKU', '% SKU', 'Stok Saat Ini', 'Tindakan yang Disarankan']);
        $sheet->mergeCells('E17:H17');
        $row = 18;
        foreach (Analyzer::COVER_LABELS as $status => $label) {
            $group = $r->rows->where('cover_status', $status);
            $this->badge($sheet, "A{$row}", $label, self::COVER_COLORS[$status], self::COVER_FILLS[$status]);
            $sheet->fromArray([$group->count(), $group->count() / $totalSku * 100, (float) $group->sum('stock'), self::COVER_ACTIONS[$status]], null, "B{$row}", true);
            $sheet->mergeCells("E{$row}:H{$row}");
            $row++;
        }
        $this->borderTable($sheet, 'A17:H22');
        $sheet->getStyle('B18:B22')->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('C18:C22')->getNumberFormat()->setFormatCode(self::PERCENT);
        $sheet->getStyle('D18:D22')->getNumberFormat()->setFormatCode(self::NUMBER);

        // --- Matriks klasifikasi x days cover ---
        $this->writeSectionTitle($sheet, 24, 'Matriks Klasifikasi x Status Days Cover (Jumlah SKU)');
        $coverKeys = array_keys(Analyzer::COVER_LABELS);
        $this->writeHeader($sheet, 25, ['Klasifikasi', 'Kritis (< 7)', 'Waspada (7-30)', 'Aman (30-90)', 'Berlebih (> 90)', 'Tidak ada', 'Total']);
        $row = 26;
        foreach (Analyzer::LABELS as $class => $label) {
            $this->badge($sheet, "A{$row}", $label, self::CLASS_COLORS[$class], self::CLASS_FILLS[$class]);
            $classRows = $r->rows->where('classification', $class);
            $counts = array_map(fn ($status) => $classRows->where('cover_status', $status)->count(), $coverKeys);
            $sheet->fromArray([...$counts, "=SUM(B{$row}:F{$row})"], null, "B{$row}", true);
            $row++;
        }
        $sheet->setCellValue("A{$row}", 'Total');
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
            $sheet->setCellValue("{$col}{$row}", "=SUM({$col}26:{$col}29)");
        }
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $this->fill($sheet, "A{$row}:G{$row}", 'F5F8FA');
        $this->borderTable($sheet, "A25:G{$row}");
        $sheet->getStyle("B26:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        // Sorot sel yang butuh tindakan (sesuai Poin Penting): fast/medium kritis & medium/slow berlebih
        $this->fill($sheet, 'B26:B27', self::COVER_FILLS[Analyzer::COVER_CRITICAL]);
        $this->fill($sheet, 'E27:E28', self::COVER_FILLS[Analyzer::COVER_EXCESS]);

        // --- Poin penting ---
        $this->writeSectionTitle($sheet, 32, 'Poin Penting');
        $row = 33;
        foreach ($this->insights() as $text) {
            $sheet->setCellValue("A{$row}", '•  ' . $text);
            $sheet->mergeCells("A{$row}:H{$row}");
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
        }

        // --- Charts ---
        $classColors = array_values(self::CLASS_COLORS);
        $sheet->addChart($this->makeChart(
            'chart_qty_share', 'Kontribusi Qty Keluar per Klasifikasi', DataSeries::TYPE_DONUTCHART,
            $this->ref('A', 10, 12), 3,
            [['label' => $this->ref('E', 9), 'values' => $this->ref('E', 10, 12), 'color' => array_slice($classColors, 0, 3)]],
            'J4', 'Q20', ['legend_position' => Legend::POSITION_RIGHT]
        ));
        $sheet->addChart($this->makeChart(
            'chart_sku_class', 'Jumlah SKU per Klasifikasi', DataSeries::TYPE_BARCHART,
            $this->ref('A', 10, 13), 4,
            [['label' => $this->ref('C', 9), 'values' => $this->ref('C', 10, 13), 'color' => $classColors]],
            'J22', 'Q37', ['legend' => false, 'show_values' => true]
        ));
        $sheet->addChart($this->makeChart(
            'chart_cover', 'Jumlah SKU per Status Days Cover', DataSeries::TYPE_BARCHART,
            $this->ref('A', 18, 22), 5,
            [['label' => $this->ref('B', 17), 'values' => $this->ref('B', 18, 22), 'color' => array_values(self::COVER_COLORS)]],
            'J39', 'Q54', ['legend' => false, 'show_values' => true, 'direction' => DataSeries::DIRECTION_BAR]
        ));

        $sheet->freezePane('A4');
    }

    /** @return string[] */
    private function insights(): array
    {
        $r = $this->report;
        $fmt = fn ($n, $d = 0) => number_format((float) $n, $d, ',', '.');

        if ($r->totalOut <= 0) {
            return ['Tidak ada barang keluar dari Permintaan Barang pada periode ini. Semua item yang memiliki stok tergolong Non Moving.'];
        }

        $fast = $r->summary[Analyzer::FAST];
        $totalSku = max(1, $r->rows->count());
        $top = $r->rows->first();
        $critical = $r->rows->whereIn('classification', [Analyzer::FAST, Analyzer::MEDIUM])
            ->where('cover_status', Analyzer::COVER_CRITICAL);
        $excess = $r->rows->whereIn('classification', [Analyzer::SLOW, Analyzer::MEDIUM])
            ->where('cover_status', Analyzer::COVER_EXCESS);
        $nonMoving = $r->summary[Analyzer::NON_MOVING];

        $points = [
            sprintf(
                '%s SKU (%s%% dari SKU dianalisis) menyumbang %s%% qty keluar dan tergolong Fast Moving - prioritaskan ketersediaan & lokasi picking terdekat.',
                $fmt($fast['sku_count']), $fmt($fast['sku_count'] / $totalSku * 100, 1), $fmt($fast['qty_share'], 1)
            ),
            sprintf(
                'Item dengan qty keluar terbesar: %s - %s (%s, %s%% dari total).',
                $top['sku'], $top['nama_barang'], $fmt($top['qty_out'], 2), $fmt($top['contribution'], 1)
            ),
        ];

        $points[] = $critical->isEmpty()
            ? 'Tidak ada item Fast/Medium Moving dengan days cover kritis (< 7 hari).'
            : sprintf(
                '%s SKU Fast/Medium Moving memiliki days cover < 7 hari (%s stok habis). Lihat sheet "Perlu Perhatian" untuk estimasi kebutuhan.',
                $fmt($critical->count()), $fmt($critical->where('stock', '<=', 0)->count())
            );

        if ($excess->isNotEmpty()) {
            $points[] = sprintf(
                '%s SKU Medium/Slow Moving memiliki stok untuk > 90 hari - pertimbangkan menahan pengadaan.',
                $fmt($excess->count())
            );
        }

        if ($nonMoving['sku_count'] > 0) {
            $points[] = sprintf(
                '%s SKU Non Moving menahan total stok %s tanpa ada pengiriman pada periode ini.',
                $fmt($nonMoving['sku_count']), $fmt($nonMoving['stock'], 2)
            );
        }

        return $points;
    }
}
