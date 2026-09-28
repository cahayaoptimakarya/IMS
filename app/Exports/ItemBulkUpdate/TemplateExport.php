<?php

namespace App\Exports\ItemBulkUpdate;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel update massal master item sesuai field yang dipilih.
 */
class TemplateExport implements WithMultipleSheets
{
    /**
     * @param  string[]  $fields  key FieldRegistry yang dipilih
     * @param  Collection  $rows  baris prefill (kosong = template tanpa data)
     * @param  array  $references  hasil ItemBulkUpdateService::references()
     */
    public function __construct(
        private array $fields,
        private Collection $rows,
        private array $references
    ) {
    }

    public function sheets(): array
    {
        return [
            new DataSheet($this->fields, $this->rows, $this->references),
            new GuideSheet($this->fields),
            new ReferenceSheet($this->references),
        ];
    }
}
