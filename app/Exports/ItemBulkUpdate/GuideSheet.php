<?php

namespace App\Exports\ItemBulkUpdate;

use App\Support\ItemBulkUpdate\FieldRegistry;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GuideSheet implements WithTitle, WithEvents
{
    public function __construct(private array $fields)
    {
    }

    public function title(): string
    {
        return 'Petunjuk';
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
        $sheet->setShowGridlines(false);
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(95);

        $sheet->setCellValue('A1', 'PETUNJUK UPDATE MASSAL MASTER ITEM');
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'Template dibuat ' . now()->format('d/m/Y H:i') . '. Isi sheet "Data", lalu upload kembali di halaman Update Massal Item.');
        $sheet->mergeCells('A2:B2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('5E6278');

        $steps = [
            'Kunci data' => 'Setiap baris dicocokkan berdasarkan SKU. SKU tidak dapat diubah; SKU yang tidak terdaftar akan dilaporkan.',
            'Sel kosong' => 'Sel yang dikosongkan berarti TIDAK DIUBAH. Hanya sel yang berisi nilai yang diproses.',
            'Mengosongkan nilai' => 'Ketik ' . FieldRegistry::CLEAR_KEYWORD . ' untuk mengosongkan field yang boleh kosong (Tempat, Deskripsi, Dimensi Koli).',
            'Tidak dapat diubah' => implode('; ', array_map(fn ($l) => $l['label'] . ' - ' . $l['reason'], FieldRegistry::LOCKED)) . '.',
            'Kolom tambahan' => 'Kolom berawalan "' . FieldRegistry::INFO_PREFIX . '" hanya informasi dan tidak diimpor. Jangan mengubah judul kolom.',
            'Pratinjau' => 'Setelah upload, sistem menampilkan pratinjau perubahan (nilai lama -> baru) dan error per baris. Data baru berubah setelah Anda menekan "Terapkan".',
        ];
        $row = 4;
        $row = $this->table($sheet, $row, 'Aturan', ['Topik', 'Keterangan'], $steps);

        $fieldRows = [];
        foreach (FieldRegistry::all() as $key => $definition) {
            if (in_array($key, $this->fields, true)) {
                $fieldRows[implode(', ', $definition['columns'])] = $definition['hint'];
            }
        }
        $this->table($sheet, $row + 1, 'Field yang di-update pada template ini', ['Kolom', 'Format Isian'], $fieldRows);
        $sheet->setSelectedCell('A1');
    }

    private function table(Worksheet $sheet, int $row, string $title, array $headers, array $rows): int
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;
        $sheet->fromArray($headers, null, "A{$row}");
        $style = $sheet->getStyle("A{$row}:B{$row}");
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E1E2D');
        $first = $row;
        foreach ($rows as $label => $text) {
            $row++;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $text);
        }
        $sheet->getStyle("A" . ($first + 1) . ":A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$first}:B{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A{$first}:B{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E4E6EF');

        return $row + 2;
    }
}
