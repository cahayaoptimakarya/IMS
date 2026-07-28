<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockApiSyncRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$filters, $error] = $this->filters($request);
        if ($error) return $error;
        $serverTime = now('Asia/Jakarta');
        $query = $filters['as_of'] ? $this->historicalQuery($filters['as_of']) : StockApiSyncRecord::query();
        if (! $filters['as_of']) {
            if ($filters['updated_since']) $query->where('source_updated_at', '>=', $filters['updated_since']);
            $query->where('source_updated_at', '<=', $filters['updated_until'] ?? $serverTime);
        }
        $total = (clone $query)->count();
        $records = $filters['as_of']
            ? $query->orderBy('sr.sku')->forPage($filters['page'], $filters['per_page'])->get()
            : $query->orderBy('source_updated_at')->orderBy('sku')->forPage($filters['page'], $filters['per_page'])->get();

        return response()->json([
            'success' => true,
            'meta' => ['warehouse_code' => config('stock_api.warehouse_code'), 'server_time' => $serverTime->toIso8601String(), 'page' => $filters['page'], 'per_page' => $filters['per_page'], 'total' => $total, 'total_pages' => (int) ceil($total / $filters['per_page'])],
            'data' => $records->map(fn (object $record) => [
                'sku' => $record->sku, 'name' => $record->name, 'category' => $record->category, 'uom' => $record->uom,
                'qty' => (float) ($record->historical_qty ?? $record->qty), 'min_qty' => $record->min_qty === null ? null : (float) $record->min_qty,
                'status' => $record->status, 'updated_at' => $this->isoTimestamp($record->historical_updated_at ?? $record->source_updated_at),
            ])->values(),
        ]);
    }

    private function historicalQuery(CarbonImmutable $asOf): Builder
    {
        // `quantity` is not consistently signed in legacy adjustment rows. The
        // before/after balance is the authoritative signed movement.
        $after = DB::table('stock_movements')->select('item_id')
            ->selectRaw('SUM(COALESCE(stock_after, 0) - COALESCE(stock_before, 0)) as net_movement')
            ->selectRaw('MAX(date) as last_movement_at')
            ->where('date', '>', $asOf->toDateString())->groupBy('item_id');
        $through = DB::table('stock_movements')->select('item_id')->selectRaw('MAX(date) as last_movement_at')
            ->where('date', '<=', $asOf->toDateString())->groupBy('item_id');

        return DB::table('stock_api_sync_records as sr')
            ->leftJoinSub($after, 'after_date', 'after_date.item_id', '=', 'sr.item_id')
            ->leftJoinSub($through, 'through_date', 'through_date.item_id', '=', 'sr.item_id')
            ->select(['sr.sku', 'sr.name', 'sr.category', 'sr.uom', 'sr.min_qty', 'sr.status', 'sr.source_updated_at',
                DB::raw('CASE WHEN sr.status = \'deleted\' THEN 0 ELSE GREATEST(0, COALESCE(sr.qty, 0) - COALESCE(after_date.net_movement, 0)) END as historical_qty'),
                DB::raw('COALESCE(through_date.last_movement_at, sr.source_updated_at) as historical_updated_at'),
            ]);
    }

    private function filters(Request $request): array
    {
        $page = filter_var($request->input('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($request->input('per_page', 100), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        if ($page === false || $perPage === false) return [[], $this->error('page dan per_page harus berupa integer positif; per_page maksimum 500.')];
        $since = $this->parseIsoTimestamp($request->input('updated_since')); $until = $this->parseIsoTimestamp($request->input('updated_until'));
        if ($since === false || $until === false) return [[], $this->error('updated_since dan updated_until harus ISO-8601 dengan offset zona waktu.')];
        if ($since && $until && $since->gt($until)) return [[], $this->error('updated_since tidak boleh melebihi updated_until.')];
        $asOf = $this->parseAsOfDate($request->input('as_of'));
        if ($asOf === false) return [[], $this->error('as_of harus berupa tanggal YYYY-MM-DD yang valid (WIB).')];
        if ($asOf && ($since || $until)) return [[], $this->error('as_of tidak dapat dipakai bersama updated_since atau updated_until.')];
        return [['page' => $page, 'per_page' => $perPage, 'updated_since' => $since, 'updated_until' => $until, 'as_of' => $asOf], null];
    }

    private function parseIsoTimestamp(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') return null;
        if (! is_string($value) || ! preg_match('/T.*(?:Z|[+-]\\d{2}:\\d{2})$/', $value)) return false;
        try { return CarbonImmutable::parse($value); } catch (\Throwable) { return false; }
    }
    private function parseAsOfDate(mixed $value): CarbonImmutable|false|null
    {
        if ($value === null || $value === '') return null;
        if (! is_string($value) || ! preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) return false;
        try { $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta'); return $date->format('Y-m-d') === $value ? $date->endOfDay() : false; } catch (\Throwable) { return false; }
    }
    private function isoTimestamp(mixed $timestamp): string
    {
        return ($timestamp instanceof \DateTimeInterface ? CarbonImmutable::instance($timestamp) : CarbonImmutable::parse($timestamp))->setTimezone('Asia/Jakarta')->toIso8601String();
    }
    private function error(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'error' => ['code' => 'INVALID_PARAMETER', 'message' => $message]], 400);
    }
}
