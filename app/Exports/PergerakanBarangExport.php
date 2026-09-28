<?php

namespace App\Exports;

use App\Exports\PergerakanBarang\AttentionSheet;
use App\Exports\PergerakanBarang\DetailSheet;
use App\Exports\PergerakanBarang\NotesSheet;
use App\Exports\PergerakanBarang\ParetoSheet;
use App\Exports\PergerakanBarang\ReportData;
use App\Exports\PergerakanBarang\SummarySheet;
use App\Exports\PergerakanBarang\TrendSheet;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PergerakanBarangExport implements WithMultipleSheets
{
    private ReportData $report;

    public function __construct(
        array $result,
        array $trend,
        string $warehouseName,
        Carbon $startDate,
        Carbon $endDate,
        ?string $printedBy = null
    ) {
        $this->report = new ReportData($result, $trend, $warehouseName, $startDate, $endDate, $printedBy);
    }

    public function sheets(): array
    {
        return [
            new SummarySheet($this->report),
            new DetailSheet($this->report),
            new ParetoSheet($this->report),
            new TrendSheet($this->report),
            new AttentionSheet($this->report),
            new NotesSheet($this->report),
        ];
    }
}
