@php
    // $bucket expected values: 'out', 'low', 'safe', 'over'
    $id = match($bucket) {
        'out' => 'out',
        'low' => 'low',
        'safe' => 'safe',
        'over' => 'over',
        default => 'list'
    };
@endphp

<div class="table-responsive">
    <div class="d-flex align-items-center mb-3 d-none" id="loading-{{ $id }}">
        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
        <span class="text-muted">Memuat data...</span>
    </div>
    <table class="table table-row-dashed align-middle gs-0 gy-2" id="table-{{ $id }}">
        <thead>
            <tr class="fw-bolder text-muted">
                <th class="w-125px">SKU</th>
                <th>Nama Barang</th>
                <th class="w-175px">Gudang</th>
                <th class="text-end w-100px">Qty</th>
                <th class="text-end w-100px">Koli</th>
                <th class="text-end w-150px">Min (Threshold)</th>
            </tr>
        </thead>
        <tbody id="tbody-{{ $id }}">
            <tr class="no-data"><td colspan="6" class="text-center text-muted">Tidak ada data</td></tr>
        </tbody>
    </table>
    <div class="text-muted fs-8 mt-1" id="count-{{ $id }}"></div>
    <style>
        .table-row-dashed tbody tr:not(.no-data) {
            border-bottom: 1px dashed #ebedf3;
        }
    </style>
</div>
