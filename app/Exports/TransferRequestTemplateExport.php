<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransferRequestTemplateExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return [
            'sku',
            'item_id',
            'quantity',
            'koli',
            'description',
        ];
    }
}
