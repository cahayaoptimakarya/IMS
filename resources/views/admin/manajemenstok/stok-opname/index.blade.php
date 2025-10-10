@extends('layouts.app')

@push('styles')
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-warning { background: #FFF7E6; color: #F59E0B; }
        .chip-primary { background: #E6F0FF; color: #3B82F6; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        .chip-danger { background: #FEE2E2; color: #EF4444; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Stock Opname',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Stock Opname'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="row g-5 g-xl-8">
            <div class="col-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-4 mb-md-0">
                                <div class="symbol symbol-50px me-5 status-card-icon">
                                    <div class="symbol-label">
                                        <i class="fas fa-clipboard-check fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Stok Opname</div>
                                    <div class="text-muted fw-bold">Dokumen Stok Opname</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-warning" id="status_in_progress">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">In Progress</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="status_completed">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" data-kt-filter="search" class="form-control form-control-solid w-250px ps-14" placeholder="Cari..." id="search_input" />
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <i class="fas fa-filter"></i>
                        </span>
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            <div class="mb-7">
                                <label class="form-label fs-6 fw-bold mb-3">Tanggal:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="date_filter_options" data-dropdown-parent="#kt_toolbar" data-hide-search="true">
                                    <option value="semua">Semua</option>
                                    <option value="pilih_tanggal">Pilih Tanggal</option>
                                </select>
                                <input class="form-control form-control-solid mt-3" placeholder="Pilih Rentang Tanggal" id="date_filter" style="display: none;" />
                            </div>
                            @if (is_null(auth()->user()->warehouse_id))
                            <div class="mb-7">
                                <label class="form-label fs-6 fw-bold mb-3">Gudang:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="warehouse_filter" data-dropdown-parent="#kt_toolbar">
                                    <option value="semua">Semua Gudang</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('admin.manajemenstok.stok-opname.create') }}" class="btn btn-primary">Tambah</a>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Stock Opname</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode Dokumen</th>
                                <th>Periode</th>
                                @if(auth()->user()->warehouse_id === null)
                                <th>Gudang</th>
                                @endif
                                <th class="text-end">Total Item</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
let table;

function loadStatusCounts() {
    const dateFilter = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();
    const warehouseFilter = $('#warehouse_filter').length ? ($('#warehouse_filter').val() || 'semua') : '';

    $.ajax({
        url: "{{ route('admin.manajemenstok.stok-opname.status-counts') }}",
        type: "GET",
        data: {
            date: dateFilter,
            warehouse: warehouseFilter
        },
        beforeSend: function() {
            $('#status_in_progress').html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>');
            $('#status_completed').html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>');
        },
        success: function(response) {
            $('#status_in_progress').text(response.in_progress || 0);
            $('#status_completed').text(response.completed || 0);
        },
        error: function() {
            $('#status_in_progress').text('N/A');
            $('#status_completed').text('N/A');
        }
    });
}

$(document).ready(function(){
    table = $('#table-on-page').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: `{{ route('admin.manajemenstok.stok-opname.index') }}`,
            data: function(d){
                d.search.value = $('#search_input').val();
                const dateFilter = $('#date_filter_options').val() === 'semua' ? 'semua' : $('#date_filter').val();
                d.date = dateFilter;
                d.warehouse = $('#warehouse_filter').length ? ($('#warehouse_filter').val() || 'semua') : '';
            }
        },
        drawCallback: function() {
            if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) {
                KTMenu.createInstances();
            }
        },
        order: [[1,'desc']],
        columns: (function() {
            const hasWarehouse = @json(auth()->user()->warehouse_id === null);
            let cols = [
                { data: 'code', name: 'so.code', render: function(data, type, row){
                    const showUrl = `{{ route('admin.manajemenstok.stok-opname.show', ':id') }}`.replace(':id', row.id);
                    const items = parseInt(row.items_count || 0).toLocaleString('id-ID');
                    return `
                        <div class="d-flex flex-column">
                            <a href="${showUrl}" class="text-gray-900 fw-bolder text-hover-primary">${data}</a>
                            <span class="text-muted fs-7">Item: ${items}</span>
                        </div>
                    `;
                }},
                { data: 'start_date', name: 'so.start_date', render: function(data, type, row){
                    const s = new Date(row.start_date);
                    const start = s.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
                    let end = '-';
                    if (row.completed_date) {
                        const e = new Date(row.completed_date);
                        end = e.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
                    }
                    return `<div class="text-gray-900 fw-bolder">${start}</div><div class="text-muted fs-7">s/d ${end}</div>`;
                }},
            ];

            if (hasWarehouse) {
                cols.push({ data: 'warehouse_name', name: 'w.name', render: function(data, type, row){
                    const started = row.started_by_name ? row.started_by_name : '-';
                    const completed = row.completed_by_name ? row.completed_by_name : null;
                    return `
                        <div class="d-flex flex-column">
                            <span class="text-gray-900 fw-bolder">${data || '-'}</span>
                            <span class="text-muted fs-7">Disusun: ${started}${completed ? ` · Diselesaikan: ${completed}` : ''}</span>
                        </div>
                    `;
                }});
            }

            cols.push(
                { data: 'items_count', name: 'items_count', className: 'text-end', render: function(val){
                    return parseInt(val || 0).toLocaleString('id-ID');
                }},
                { data: 'status_text', name: 'status_text', render: function(data){
                    if (data === 'in_progress') return '<span class="badge badge-light-warning">Proses</span>';
                    return '<span class="badge badge-light-success">Selesai</span>';
                }},
                { data: 'id', orderable: false, searchable: false, render: function(id, t, row){
                    const editUrl = `{{ route('admin.manajemenstok.stok-opname.edit', ':id') }}`.replace(':id', id);
                    const delUrl = `{{ route('admin.manajemenstok.stok-opname.destroy', ':id') }}`.replace(':id', id);
                    const showUrl = `{{ route('admin.manajemenstok.stok-opname.show', ':id') }}`.replace(':id', id);
                    const csrf = `{{ csrf_token() }}`;
                    let actionsHtml = `
                    <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions
                        <span class="svg-icon svg-icon-5 m-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                            </svg>
                        </span>
                    </a>
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                        <div class="menu-item px-3">
                            <a href="${showUrl}" class="menu-link px-3">View</a>
                        </div>`;
                    if (row.status_text === 'in_progress') {
                        actionsHtml += `
                        <div class="menu-item px-3">
                            <a href="${editUrl}" class="menu-link px-3">Edit</a>
                        </div>`;
                        actionsHtml += `
                        <div class="menu-item px-3">
                            <a href="#" class="menu-link px-3" onclick="confirmOpnameStatus(${id}, 'completed', '${row.code}')">Completed</a>
                        </div>
                        <div class="menu-item px-3">
                            <form class="form-delete" action="${delUrl}" method="POST">
                                <input type="hidden" name="_token" value="${csrf}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start" data-document-code="${row.code}">Delete</button>
                            </form>
                        </div>`;
                    }
                    actionsHtml += `</div>`;
                    return actionsHtml;
                }}
            );
            return cols;
        })(),
    });

    loadStatusCounts();

    $('#search_input').on('keyup', function(){ table.draw(); });

    // Date filter UI
    $('#date_filter').flatpickr({
        mode: 'range',
        dateFormat: 'Y-m-d',
        onChange: function(){
            if ($('#date_filter_options').val() !== 'pilih_tanggal') {
                $('#date_filter_options').val('pilih_tanggal').trigger('change');
            }
        }
    });
    $('#date_filter_options').on('change', function(){
        if ($(this).val() === 'pilih_tanggal') {
            $('#date_filter').show();
        } else {
            $('#date_filter').hide();
            $('#date_filter').val('');
        }
    });
    if ($.fn.select2) {
        $('#warehouse_filter').select2({ placeholder: 'Semua Gudang', allowClear: true, width: '100%' });
    }

    // Apply filter
    $('#apply_filter').on('click', function(){
        // Update filter info text
        const dateVal = $('#date_filter_options').val() === 'semua' ? 'Semua Tanggal' : ($('#date_filter').val() || '-');
        let info = `Tanggal: ${dateVal}`;
        if ($('#warehouse_filter').length) {
            const whText = $('#warehouse_filter').val() ? $('#warehouse_filter option:selected').text() : 'Semua Gudang';
            info += ` | Gudang: ${whText}`;
        } else {
            info += ` | Gudang: {{ auth()->user()->warehouse->name ?? 'N/A' }}`;
        }
        $('#filter-info').text(info);
        table.ajax.reload();
        loadStatusCounts();
        $('[data-kt-menu-dismiss="true"]').click();
    });
    // delete inside dropdown form
    $('#table-on-page').on('submit', '.form-delete', function(e){
        e.preventDefault();
        var form = $(this);
        var n = form.find('button[data-document-code]').data('document-code');
        var url = form.attr('action');
        var data = form.serialize();
        Swal.fire({
            text: `Apakah yakin ingin menghapus dokumen ${n}?`,
            icon: 'warning',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Ya, hapus!',
            cancelButtonText: 'Tidak, batalkan',
            customClass: { confirmButton: 'btn fw-bold btn-danger', cancelButton: 'btn fw-bold btn-active-light-light' }
        }).then(function(result){
            if (result.value) {
                $.post(url, data).done(function(resp){ toastr.success(`Dokumen ${n} berhasil dihapus.`); table.ajax.reload(); })
                .fail(function(){ toastr.error(`Gagal menghapus dokumen ${n}. Silakan coba lagi.`); });
            } else if (result.dismiss === 'cancel') {
                toastr.info(`Penghapusan dokumen ${n} dibatalkan.`);
            }
        });
    });
});

function deleteOpname(url){
    Swal.fire({
        text: "Hapus dokumen ini?",
        icon: "warning",
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: "Ya, hapus!",
        cancelButtonText: "Batal",
        customClass: { confirmButton: "btn fw-bold btn-danger", cancelButton: "btn fw-bold btn-active-light-primary" }
    }).then(function(result){
        if (result.value) {
            $.ajax({
                url: url,
                type: 'POST',
                data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
                success: function(resp){
                    toastr.success(resp.message || 'Berhasil dihapus');
                    table.ajax.reload();
                },
                error: function(){ toastr.error('Gagal menghapus'); }
            });
        }
    });
}

function confirmOpnameStatus(id, status, code){
    let confirmationText = '';
    let successText = '';
    if (status === 'completed') {
        confirmationText = `Apakah Anda yakin ingin mengubah status stock opname ${code} menjadi 'Selesai'?`;
        successText = `Status stock opname ${code} berhasil diubah menjadi 'Selesai'.`;
    }
    Swal.fire({
        text: confirmationText,
        icon: 'warning',
        showCancelButton: true,
        buttonsStyling: false,
        confirmButtonText: 'Ya, lanjutkan!',
        cancelButtonText: 'Tidak, batalkan',
        customClass: { confirmButton: 'btn fw-bold btn-success', cancelButton: 'btn fw-bold btn-active-light-light' }
    }).then(function(result){
        if (result.value) {
            $.post(`{{ url('admin/manajemen-stok/stok-opname') }}/${id}/update-status`, { _token: `{{ csrf_token() }}`, status: status })
            .done(function(){ toastr.success(successText); table.ajax.reload(); })
            .fail(function(){ toastr.error('Gagal mengubah status. Silakan coba lagi.'); });
        } else if (result.dismiss === 'cancel') {
            toastr.info('Perubahan status dibatalkan.');
        }
    });
}
</script>
@endpush
