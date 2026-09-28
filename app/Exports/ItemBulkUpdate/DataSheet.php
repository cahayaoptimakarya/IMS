<?php

namespace App\Exports\ItemBulkUpdate;

use App\Support\ItemBulkUpdate\FieldRegistry;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataSheet implements WithTitle, WithEvents
{
    public const TITLE = 'Data';

    /** Baris tambahan di bawah data yang tetap diberi dropdown/format agar nyaman diisi manual. */
    private const EXTRA_ROWS = 1000;

    public function __construct(
        private array $fields,
        private Collection $rows,
        private array $references
    ) {
    }

    public function title(): string
    {
        return self::TITLE;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build($event->sheet->getDelegate());
            },
        ];
    }

    private function build(Worksheet $sheet): void
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Susunan kolom: SKU, [Info: Nama Barang], field terpilih
        $columns = ['sku' => ['header' => FieldRegistry::SKU_HEADER, 'kind' => 'sku']];
        if (!in_array('nama_barang', $this->fields, true)) {
            $columns['nama_barang_info'] = ['header' => FieldRegistry::INFO_PREFIX . 'Nama Barang', 'kind' => 'info'];
        }
        foreach (FieldRegistry::columnsFor($this->fields) as $column => $meta) {
            $columns[$column] = ['header' => $meta['header'], 'kind' => 'field', 'definition' => FieldRegistry::get($meta['field'])];
        }

        $letters = [];
        $i = 1;
        foreach ($columns as $key => $column) {
            $letters[$key] = Coordinate::stringFromColumnIndex($i++);
        }
        $lastLetter = end($letters);
        $lastDataRow = max(2, $this->rows->count() + 1);
        $lastFormatRow = $lastDataRow + self::EXTRA_ROWS;

        // Header
        foreach ($columns as $key => $column) {
            $cell = $letters[$key] . '1';
            $sheet->setCellValue($cell, $column['header']);
            $sheet->getComment($cell)->setWidth('260px')->setHeight('90px')->getText()->createTextRun($this->headerNote($column));
        }
        $header = $sheet->getStyle("A1:{$lastLetter}1");
        $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E1E2D');
        $header->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(32);

        // Data
        $rowNumber = 2;
        foreach ($this->rows as $row) {
            foreach ($columns as $key => $column) {
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $cell = $letters[$key] . $rowNumber;
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue($cell, $value);
                } else {
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
            $rowNumber++;
        }

        // Format & bantuan per kolom
        foreach ($columns as $key => $column) {
            $letter = $letters[$key];
            $range = "{$letter}2:{$letter}{$lastFormatRow}";
            $width = 18;

            if ($column['kind'] === 'sku') {
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                $sheet->getStyle("{$letter}2:{$letter}{$lastDataRow}")->getFont()->setBold(true);
                $sheet->getStyle("{$letter}2:{$letter}{$lastDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF0F8');
                $width = 18;
            } elseif ($column['kind'] === 'info') {
                $sheet->getStyle("{$letter}2:{$letter}{$lastDataRow}")->getFont()->setItalic(true)->getColor()->setRGB('7E8299');
                $sheet->getStyle("{$letter}2:{$letter}{$lastDataRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F8FA');
                $sheet->getStyle("{$letter}1")->getFill()->getStartColor()->setRGB('5E6278');
                $width = 38;
            } else {
                $definition = $column['definition'];
                $width = match ($definition['type']) {
                    'text' => $key === 'deskripsi' ? 45 : ($key === 'nama_barang' ? 38 : 22),
                    'reference' => 24,
                    'decimal', 'integer' => 20,
                    default => 14,
                };
                $this->applyValidation($sheet, $range, $key, $definition, $column['header']);
                if (in_array($definition['type'], ['text', 'reference', 'status'], true)) {
                    $sheet->getStyle($range)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            }
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        $sheet->getStyle("A1:{$lastLetter}{$lastDataRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');
        $sheet->setAutoFilter("A1:{$lastLetter}{$lastDataRow}");
        $sheet->freezePane(isset($letters['nama_barang_info']) ? 'C2' : 'B2');
        $sheet->setSelectedCell('A2');
    }

    private function headerNote(array $column): string
    {
        return match ($column['kind']) {
            'sku' => "Kunci pencocokan item.\nJangan diubah - SKU tidak dapat di-update.",
            'info' => "Hanya informasi, tidak diimpor.",
            default => $column['definition']['hint'] . ".\nSel kosong = tidak diubah.",
        };
    }

    private function applyValidation(Worksheet $sheet, string $range, string $key, array $definition, string $header): void
    {
        $formula = match ($definition['type']) {
            'reference' => ReferenceSheet::range($definition['reference'], $this->references),
            'status' => '"Aktif,Nonaktif"',
            default => null,
        };

        if ($formula) {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setShowInputMessage(true)
                ->setErrorTitle('Nilai tidak valid')
                ->setError("Pilih {$header} dari daftar.")
                ->setPromptTitle($header)
                ->setPrompt('Pilih dari daftar. Kosongkan jika tidak diubah.')
                ->setFormula1($formula);
            $sheet->setDataValidation($range, $validation);

            return;
        }

        if ($definition['type'] === 'integer') {
            $validation = new DataValidation();
            $validation->setType(DataValidation::TYPE_WHOLE)
                ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Nilai tidak valid')
                ->setError("{$header} harus bilangan bulat >= 0.")
                ->setFormula1('0');
            $sheet->setDataValidation($range, $validation);
        }
    }
}
