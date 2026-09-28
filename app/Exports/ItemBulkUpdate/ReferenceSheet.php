<?php

namespace App\Exports\ItemBulkUpdate;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Daftar nilai valid untuk kolom referensi; dipakai sebagai sumber dropdown di sheet Data.
 */
class ReferenceSheet implements WithTitle, WithEvents
{
    public const TITLE = 'Referensi';

    /** kolom sheet -> [judul, key referensi] */
    public const COLUMNS = [
        'A' => ['Kategori', 'categories'],
        'B' => ['Tipe Item', 'types'],
        'C' => ['UOM', 'uoms'],
        'D' => ['Status', null],
    ];

    public function __construct(private array $references)
    {
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /** Rentang absolut untuk data validation, mis. 'Referensi'!$A$2:$A$15 */
    public static function range(string $referenceKey, array $references): ?string
    {
        foreach (self::COLUMNS as $column => [, $key]) {
            if ($key === $referenceKey) {
                $count = count($references[$key]['labels']);

                return $count > 0 ? "'" . self::TITLE . "'!\${$column}\$2:\${$column}\$" . ($count + 1) : null;
            }
        }

        return null;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (self::COLUMNS as $column => [$title, $key]) {
                    $values = $key ? array_values($this->references[$key]['labels']) : ['Aktif', 'Nonaktif'];
                    $sheet->setCellValue("{$column}1", $title);
                    foreach ($values as $i => $value) {
                        $sheet->setCellValueExplicit($column . ($i + 2), (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    $sheet->getColumnDimension($column)->setWidth(28);
                }
                $header = $sheet->getStyle('A1:D1');
                $header->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E1E2D');
                $sheet->freezePane('A2');
                $sheet->setSelectedCell('A1');
            },
        ];
    }
}
