@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Permintaan Barang - Belum Dikirim',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Permintaan Barang', 'Belum Dikirim'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap mb-4">
                    <form id="filter_form" method="GET" class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center mb-2">
                            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                <span class="svg-icon svg-icon-2"><i class="fas fa-filter"></i></span>
                                Filter
                            </button>
                            <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt-toolbar-filter">
                                <div class="px-7 py-5"><div class="fs-4 text-dark fw-bolder">Filter Options</div></div>
                                <div class="separator border-gray-200"></div>
                                <div class="px-7 py-5">
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Asal:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="from_warehouse_id" id="from_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter" @if($userWarehouseId) disabled @endif>
                                            <option value="">Semua</option>
                                            @foreach(($warehouses ?? []) as $w)
                                                <option value="{{ $w->id }}" @if(isset($selectedFrom) && (string)$selectedFrom===(string)$w->id) selected @endif>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Tujuan:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="to_warehouse_id" id="to_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="">Semua</option>
                                            @foreach(($warehouses ?? []) as $w)
                                                <option value="{{ $w->id }}" @if((isset($selectedTo) && (string)$selectedTo===(string)$w->id) || (isset($userWarehouseId) && $userWarehouseId==$w->id)) selected @endif>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="reset" class="btn btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">Batal</button>
                                        <button type="submit" class="btn btn-primary" id="apply_filter">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <button type="button" id="print_btn" class="btn btn-success" title="Cetak">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Title (screen) -->
                <div class="text-center mb-4 screen-header">
                    <div class="fw-bolder" style="font-size:1.1rem;">Daftar barang belum dikirim</div>
                    <div class="text-muted" id="filters_summary" style="font-size:.9rem;">
                        Gudang Asal: <span id="sum_from">Semua</span> | Gudang Tujuan: <span id="sum_to">Semua</span>
                    </div>
                </div>

                <div class="text-center mb-4 print-header" style="display:none;">
                    <div class="fw-bolder doc-title-mini" style="font-size:1.2rem;">Daftar Barang Belum Dikirim</div>
                    <div class="text-muted" id="filters_summary" style="font-size:.9rem;">
                        Gudang Asal: <span id="sum_from">Semua</span> | Gudang Tujuan: <span id="sum_to">Semua</span>
                    </div>
                </div>

                <div class="mb-4" style="max-width:320px;">
                    <input type="text" id="search_input" class="form-control form-control-solid" placeholder="Cari Barang Belum Dikirim" />
                </div>
                <div class="table-responsive">
                    <table id="unshipped_table" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>Kode TR</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Sisa Qty Kirim</th>
                                <th class="text-end">Sisa Koli Kirim</th>
                                <th class="no-print-col">Dari</th>
                                <th class="no-print-col">Ke</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                            @forelse(($pendingItems ?? []) as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('Y-m-d') }}</td>
                                    <td><a href="{{ route('admin.stok-keluar.permintaan-barang.show', $row->tr_id) }}" class="text-primary fw-bold">{{ $row->code }}</a></td>
                                    <td>{{ $row->sku }}</td>
                                    <td>{{ $row->nama_barang }}</td>
                                    <td class="text-end">{{ number_format((int)($row->ship_remaining_quantity ?? 0), 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format((float)($row->ship_remaining_koli ?? 0), 2, ',', '.') }}</td>
                                    <td class="no-print-col">{{ $row->from_warehouse_name }}</td>
                                    <td class="no-print-col">{{ $row->to_warehouse_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function(){
        if ($.fn.select2) {
            $('#from_warehouse_filter, #to_warehouse_filter').select2();
        }
        // Update summary on change
        function updateSummary(){
            var fromText = $('#from_warehouse_filter option:selected').text() || 'Semua';
            var toText = $('#to_warehouse_filter option:selected').text() || 'Semua';
            if(!$('#from_warehouse_filter').val()){ fromText = 'Semua'; }
            if(!$('#to_warehouse_filter').val()){ toText = 'Semua'; }
            $('#sum_from').text(fromText);
            $('#sum_to').text(toText);
        }
        updateSummary();
        $('#from_warehouse_filter, #to_warehouse_filter').on('change', updateSummary);

        // Submit filter form
        $('#apply_filter').on('click', function(){ $('#filter_form').submit(); });

        // Client-side search (filter rows)
        $('#search_input').on('input', function(){
            var q = ($(this).val() || '').toString().toLowerCase().trim();
            var $rows = $('#unshipped_table tbody tr');
            if (!q) { $rows.show(); return; }
            $rows.each(function(){
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(q) !== -1);
            });
        });

        // Simple print
        $('#print_btn').on('click', function(){ window.print(); });
    });
</script>
<style>
@media print {
    body * { visibility: hidden; }
    #kt_content, #kt_content * { visibility: visible; }
    #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
    #filter_form, #apply_filter, #print_btn, #search_input { display: none !important; }
    .dataTables_length, .dataTables_filter, .dataTables_paginate, .dataTables_info { display: none !important; }
    .card-body { padding: 0 !important; }
    .print-header { display: block !important; margin-bottom: 8px; }
    #unshipped_table { border-collapse: collapse !important; width: 100%; }
    #unshipped_table thead th, #unshipped_table tbody td { border: 1px solid #000 !important; padding: 4px 6px !important; }
    #unshipped_table thead th { background: #fff !important; color: #000 !important; font-weight: 700 !important; }
    #unshipped_table thead { display: table-header-group; }
    #unshipped_table tfoot { display: table-footer-group; }
    html, body { font-size: 11pt; }
    .screen-header { display: none !important; }
    /* Hide certain columns in print */
    #unshipped_table th.no-print-col, #unshipped_table td.no-print-col { display: none !important; }
}

/* Screen table polishing for document look */
#unshipped_table thead th, #unshipped_table tbody td { border-bottom: 1px solid #e5e7eb; }
.doc-title-mini { font-weight: 800; letter-spacing: .4px; }
</style>
@endpush
