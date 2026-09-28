<?php


namespace App\Http\Controllers\Admin\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Support\ItemMovementAnalyzer;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function laporanPergerakanBarang(Request $request)
    {
        $userWarehouseId = auth()->user()?->warehouse_id;
        $warehouses = $userWarehouseId
            ? Warehouse::where('id', $userWarehouseId)->get()
            : Warehouse::orderBy('name')->get();
        $activeWarehouse = Warehouse::find($this->resolveWarehouseId($request));

        return view('admin.laporan.pergerakan-barang', [
            'warehouses' => $warehouses,
            'activeWarehouse' => $activeWarehouse,
            'lockWarehouse' => (bool) $userWarehouseId,
            'classLabels' => ItemMovementAnalyzer::LABELS,
            'fastThreshold' => ItemMovementAnalyzer::FAST_THRESHOLD,
            'mediumThreshold' => ItemMovementAnalyzer::MEDIUM_THRESHOLD,
        ]);
    }

    public function pergerakanBarangData(Request $request, ItemMovementAnalyzer $analyzer)
    {
        $draw = (int) $request->input('draw', 0);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);

        $warehouseId = $this->resolveWarehouseId($request);
        [$startDate, $endDate] = $this->resolveDateRange($request->input('date_range'));

        if (!$warehouseId) {
            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'summary' => null,
            ]);
        }

        $result = $analyzer->analyze($warehouseId, $startDate, $endDate);
        $rows = $result['rows'];
        $recordsTotal = $rows->count();

        $classFilter = $request->input('class_filter');
        if ($classFilter && array_key_exists($classFilter, ItemMovementAnalyzer::LABELS)) {
            $rows = $rows->where('classification', $classFilter);
        }

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function ($row) use ($needle) {
                return str_contains(mb_strtolower((string) $row['sku']), $needle)
                    || str_contains(mb_strtolower((string) $row['nama_barang']), $needle);
            });
        }

        // Urutan default = peringkat kontribusi (qty keluar terbesar, non moving di akhir)
        $sortable = [
            0 => 'rank',
            1 => 'sku',
            2 => 'stock',
            3 => 'rank',
            4 => 'qty_out',
            5 => 'avg_per_day',
            6 => 'contribution',
            7 => 'frequency',
            8 => 'days_cover',
            9 => 'last_out_date',
        ];
        $orderKey = $sortable[(int) $request->input('order.0.column', 0)] ?? 'rank';
        $descending = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc';
        $rows = $rows->sort(function ($a, $b) use ($orderKey, $descending) {
            // Nilai kosong (days cover / tanggal tidak ada) selalu di akhir
            if ($a[$orderKey] === null || $b[$orderKey] === null) {
                return ($a[$orderKey] === null) <=> ($b[$orderKey] === null) ?: $a['rank'] <=> $b['rank'];
            }
            $cmp = $descending ? $b[$orderKey] <=> $a[$orderKey] : $a[$orderKey] <=> $b[$orderKey];
            return $cmp ?: $a['rank'] <=> $b['rank'];
        })->values();

        $recordsFiltered = $rows->count();
        $page = $length > 0 ? $rows->slice($start, $length)->values() : $rows;

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $page->map(function ($row) {
                $row['classification_label'] = ItemMovementAnalyzer::LABELS[$row['classification']];
                $row['last_out_date_label'] = $row['last_out_date']
                    ? Carbon::parse($row['last_out_date'])->translatedFormat('d M Y')
                    : null;
                return $row;
            }),
            'summary' => [
                'classes' => $result['summary'],
                'total_out' => $result['total_out'],
                'total_sku' => $recordsTotal,
                'period_days' => $result['period_days'],
                'period_label' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            ],
        ]);
    }

    /**
     * User yang terikat gudang selalu memakai gudangnya; selain itu filter request, default Gudang Nanggewer.
     */
    protected function resolveWarehouseId(Request $request): ?int
    {
        $userWarehouseId = auth()->user()?->warehouse_id;
        if ($userWarehouseId) {
            return (int) $userWarehouseId;
        }

        $requested = (int) $request->input('warehouse_filter');
        if ($requested && Warehouse::whereKey($requested)->exists()) {
            return $requested;
        }

        $nanggewerId = Warehouse::where('name', 'Gudang Nanggewer')->value('id');

        return $nanggewerId ? (int) $nanggewerId : Warehouse::orderBy('id')->value('id');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolveDateRange(?string $dateRange): array
    {
        if ($dateRange) {
            $dates = explode(' - ', $dateRange);
            if (count($dates) === 2) {
                try {
                    $startDate = Carbon::createFromFormat('d/m/Y', trim($dates[0]))->startOfDay();
                    $endDate = Carbon::createFromFormat('d/m/Y', trim($dates[1]))->endOfDay();
                    if ($startDate->lte($endDate)) {
                        return [$startDate, $endDate];
                    }
                } catch (\Throwable $e) {
                    // format tidak valid -> pakai default
                }
            }
        }

        return [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()];
    }
}
