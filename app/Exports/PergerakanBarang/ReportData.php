<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hasil ItemMovementAnalyzer + konteks cetak yang dipakai bersama oleh semua sheet.
 */
class ReportData
{
    public Collection $rows;
    public array $summary;
    public int $periodDays;
    public float $totalOut;
    public Carbon $printedAt;

    public function __construct(
        array $result,
        public array $trend,
        public string $warehouseName,
        public Carbon $startDate,
        public Carbon $endDate,
        public ?string $printedBy = null
    ) {
        // Urut sesuai peringkat kontribusi (moving dulu, non moving di akhir)
        $this->rows = $result['rows']->sortBy('rank')->values();
        $this->summary = $result['summary'];
        $this->periodDays = $result['period_days'];
        $this->totalOut = $result['total_out'];
        $this->printedAt = Carbon::now();
    }

    public function contextLine(): string
    {
        return sprintf(
            '%s  |  Periode %s - %s (%d hari)  |  Dicetak %s%s',
            $this->warehouseName,
            $this->startDate->format('d/m/Y'),
            $this->endDate->format('d/m/Y'),
            $this->periodDays,
            $this->printedAt->format('d/m/Y H:i'),
            $this->printedBy ? ' oleh ' . $this->printedBy : ''
        );
    }

    public function movingRows(): Collection
    {
        return $this->rows->where('classification', '!=', Analyzer::NON_MOVING)->values();
    }

    public function daysSinceLastOut(array $row): ?int
    {
        if (!$row['last_out_date']) {
            return null;
        }

        return (int) Carbon::parse($row['last_out_date'])->diffInDays($this->endDate->copy()->startOfDay());
    }

    public function medianCover(string $class): ?float
    {
        $values = $this->rows->where('classification', $class)
            ->pluck('days_cover')->filter(fn ($v) => $v !== null)->sort()->values();

        return $values->isEmpty() ? null : (float) $values->median();
    }
}
