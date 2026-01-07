@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Barang Rusak',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Barang Rusak'],
    ])
@endpush

@push('styles')
    <style>
        .status-card-icon .symbol-label {
            background: #F3E8FF;
        }

        .status-card-icon i {
            color: #7C3AED;
        }

        .status-metrics {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .status-metrics .metric {
            text-align: center;
            min-width: 88px;
        }

        .status-metrics .chip {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1;
        }

        .chip-warning {
            background: #FFF7E6;
            color: #F59E0B;
        }

        .chip-primary {
            background: #E6F0FF;
            color: #3B82F6;
        }

        .chip-success {
            background: #ECFDF5;
            color: #10B981;
        }

        .chip-danger {
            background: #FEE2E2;
            color: #EF4444;
        }

        /* Grouped table (per tanggal) */
        .table-group-row td {
            background: #f5f8ff;
            font-weight: 700;
            color: #1e293b;
            border-bottom: 1px solid #e4e6ef;
        }
        .table-group-row .group-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-group-row .group-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #3b82f6;
            display: inline-block;
        }

        @media (max-width: 576px) {
            .status-metrics {
                justify-content: flex-start;
            }

            .status-metrics .metric {
                min-width: 70px;
            }
        }
    </style>
@endpush
@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canApprove = $permissionResolver->userCan('approve');
@endphp

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card card-xl-stretch mb-xl-8">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center mb-4 mb-md-0">
                        <div class="symbol symbol-50px me-5 status-card-icon">
                            <div class="symbol-label">
                                <i class="fas fa-dumpster-fire fs-2x"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-dark fw-bolder fs-5 mb-1">Status Barang Rusak</div>
                            <div class="text-muted fw-bold">Dokumen Defect</div>
                        </div>
                    </div>
                    <div class="status-metrics">
                        <div class="metric">
                            <div class="chip chip-warning" id="status_pending">0</div>
                            <div class="text-muted fs-8 mt-1">Pending</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-success" id="status_approved">0</div>
                            <div class="text-muted fs-8 mt-1">Approved</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-danger" id="status_cancelled">0</div>
                            <div class="text-muted fs-8 mt-1">Cancelled</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="separator border-gray-200"></div>
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1"
                                    transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path
                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black"></path>
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Data...">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end">
                        @if ($canCreate)
                            <a href="{{ route('admin.manajemenstok.defects.create') }}" class="btn btn-primary">Tambah
                                Data</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#tab-documents">Dokumen</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#tab-stock">Stok Defect</a>
                    </li>
                </ul>
                <div class="tab-content" id="defectTabs">
                    <div class="tab-pane fade show active" id="tab-documents" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between mb-5">
                            <div class="d-flex align-items-center gap-3">
                                <select id="status_filter" class="form-select form-select-solid" style="width:220px">
                                    <option value="semua">Semua Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <input class="form-control form-control-solid" placeholder="Pilih Tanggal" id="date_filter" style="width:220px" />
                                @if (is_null(auth()->user()->warehouse_id))
                                <select id="warehouse_filter" class="form-select form-select-solid" style="width:260px">
                                    <option value="">Semua Gudang</option>
                                    @foreach(($warehouses ?? collect()) as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @endif
                            </div>
                        </div>
                        <div class="table-responsive min-h-500px">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="defects-table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>Kode</th>
                                        <th>Tanggal</th>
                                        @if (is_null(auth()->user()->warehouse_id))
                                            <th>Gudang</th>
                                        @endif
                                        <th>Barang</th>
                                        <th>Jumlah</th>
                                        <th>Koli</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                        <th>Action Status</th>
                                        <th>Alasan Rusak</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-bold text-gray-600"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-stock" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between mb-5">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center position-relative my-1">
                                    <span class="svg-icon svg-icon-1 position-absolute ms-6">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                            <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path>
                                        </svg>
                                    </span>
                                    <input type="text" id="stock_search_input" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Barang (SKU/Nama)">
                                </div>
                                @if (is_null(auth()->user()->warehouse_id))
                                <select id="stock_warehouse_filter" class="form-select form-select-solid" style="width:260px">
                                    <option value="">Semua Gudang</option>
                                    @foreach(($warehouses ?? collect()) as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @endif
                            </div>
                        </div>
                        <div class="table-responsive min-h-500px">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="defects-stock-table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>SKU</th>
                                        <th>Barang</th>
                                        @if (is_null(auth()->user()->warehouse_id))
                                            <th>Gudang</th>
                                        @endif
                                        <th>Total Defect</th>
                                        <th>Disposal</th>
                                        <th>Return Stock</th>
                                        <th>Stok Defect Akhir</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-bold text-gray-600"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Shared date helpers for defect tables (ID locale)
        const formatDate = (value) => {
            if (!value) return '';
            const dt = new Date(value);
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        };
        const formatDateTime = (value) => {
            if (!value) return '';
            const dt = new Date(value);
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };
        window.formatDate = formatDate;
        window.formatDateTime = formatDateTime;

        $(document).ready(function() {
            // load status counts
            function loadStatusMetrics() {
                $.get("{{ route('admin.manajemenstok.defects.status-counts') }}", function(res) {
                    $('#status_pending').text(res.pending ?? 0);
                    $('#status_approved').text(res.approved ?? 0);
                    $('#status_cancelled').text(res.cancelled ?? 0);
                });
            }
            loadStatusMetrics();

            // date picker
            if (window.flatpickr) {
                flatpickr('#date_filter', {
                    dateFormat: 'Y-m-d',
                    mode: 'range'
                });
            }

            let showWarehouse = {{ auth()->user()->warehouse_id === null ? 'true' : 'false' }};
            const columnCount = showWarehouse ? 10 : 9;
            let columns = [{
                    data: 'code',
                    name: 'code'
                },
                {
                    data: 'date',
                    name: 'date',
                    render: function(data, type) {
                        if (type === 'sort' || type === 'type') {
                            return data || '';
                        }
                        return formatDate(data);
                    }
                },
            ];
            if (showWarehouse) {
                columns.push({
                    data: 'warehouse.name',
                    name: 'warehouse.name'
                });
            }
            columns = columns.concat([{
                    data: 'item.nama_barang',
                    name: 'item.nama_barang',
                    render: function(data, type, row) {
                        try {
                            return `${row.item.nama_barang} (${row.item.sku})`;
                        } catch (e) {
                            return data || '';
                        }
                    }
                },
                {
                    data: 'quantity',
                    name: 'quantity',
                    className: 'text-end'
                },
                {
                    data: 'koli',
                    name: 'koli',
                    className: 'text-end'
                },
                {
                    data: 'status',
                    name: 'status',
                    render: function(data) {
                        if (data === 'pending')
                        return '<span class="badge badge-light-warning">Pending</span>';
                        if (data === 'approved')
                        return '<span class="badge badge-light-success">Approved</span>';
                        if (data === 'cancelled')
                        return '<span class="badge badge-light-danger">Cancelled</span>';
                        return data || '';
                    }
                },
                {
                    data: 'action',
                    name: 'action',
                    render: function(data) {
                        if (!data) return '<span class="badge badge-light">-</span>';
                        const map={
                            disposal:'Disposal',
                            return_to_vendor:'Return to Vendor',
                            rework:'Rework',
                            return_to_stock:'Return to Stock',
                            other:'Other'
                        };
                        return `<span class="badge badge-light-primary">${map[data]||data}</span>`;
                    }
                },
                {
                    data: 'action_status',
                    name: 'action_status',
                    render: function(data) {
                        if (!data) return '<span class="badge badge-light">-</span>';
                        if (data === 'pending') return '<span class="badge badge-light-warning">Pending</span>';
                        if (data === 'in_progress') return '<span class="badge badge-light-info">In Progress</span>';
                        if (data === 'completed') return '<span class="badge badge-light-success">Completed</span>';
                        if (data === 'cancelled') return '<span class="badge badge-light-danger">Cancelled</span>';
                        return data || '';
                    }
                },
                {
                    data: 'notes',
                    name: 'notes',
                    render: function(data){
                        if (!data) return '-';
                        const txt = data.toString();
                        return txt.length > 80 ? txt.substring(0,77) + '...' : txt;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function(data, type, row) {
                        let editUrl = `{{ route('admin.manajemenstok.defects.index') }}`.replace('/defects', `/defects/${row.id}/edit`);
                        let approveBtn = '';
                        let handleBtn = '';
                        @if ($canEdit)
                            if (row.status === 'pending') {
                                approveBtn = `<div class="menu-item px-3"><button type="button" class="menu-link px-3 w-100 text-start border-0 bg-transparent btn-approve-defect" data-id="${row.id}" data-code="${row.code}">Approve</button></div>`;
                            }
                            if (row.status === 'approved') {
                                handleBtn = `<div class="menu-item px-3"><button type="button" class="menu-link px-3 w-100 text-start border-0 bg-transparent btn-handle-action" data-id="${row.id}">Penanganan</button></div>`;
                            }
                        @endif
                        let editBtn = (row.status === 'pending') ? `<div class="menu-item px-3"><a href="${editUrl}" class="menu-link px-3">Edit</a></div>` : '';
                        return `
                          <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Aksi <span class="svg-icon svg-icon-5 m-0"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path></svg></span></a>
                          <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                            ${editBtn}
                            ${approveBtn}
                            ${handleBtn}
                          </div>`;
                    }
                }
            ]);

            let table = $('#defects-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.manajemenstok.defects.index') }}",
                    data: function(d) {
                        d['search.value'] = $('#search_input').val();
                        d['status'] = $('#status_filter').val();
                        d['date'] = $('#date_filter').val();
                        d['warehouse_id'] = $('#warehouse_filter').val();
                    }
                },
                columns: columns,
                order: [
                    [1, 'desc']
                ],
                drawCallback: function(){
                    const api = this.api();
                    const rows = api.rows({ page: 'current' }).nodes();
                    const dates = api.column(1, { page: 'current' }).data(); // date column index always 1
                    let last = null;
                    dates.each(function(dateVal, i){
                        const label = formatDate(dateVal) || '-';
                        if (last !== label) {
                            $(rows).eq(i).before(`
                                <tr class="table-group-row">
                                    <td colspan="${columnCount}">
                                        <div class="group-date"><span class="group-dot"></span>${label}</div>
                                    </td>
                                </tr>
                            `);
                            last = label;
                        }
                    });
                    if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) {
                        KTMenu.createInstances();
                    }
                }
            });

            const debounce = (callback, wait) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => {
                        callback.apply(null, args);
                    }, wait);
                };
            }

            $('#search_input').on('keyup', debounce(function() {
                table.draw();
            }, 400));

            $('#status_filter').on('change', function() {
                table.draw();
            });
            $('#date_filter').on('change', function() {
                table.draw();
            });
            $('#warehouse_filter').on('change', function() {
                table.draw();
            });

            // Stock summary table (init on first show)
            let stockTable = null;
            const initStockTable = function(){
                if (stockTable) return;
                const stockShowWarehouse = {{ auth()->user()->warehouse_id === null ? 'true' : 'false' }};
                let stockColumns = [
                    { data: 'item.sku', name: 'i.sku' },
                    { data: 'item.nama_barang', name: 'i.nama_barang' },
                ];
                if (stockShowWarehouse) {
                    stockColumns.push({ data: 'warehouse.name', name: 'w.name' });
                }
                stockColumns = stockColumns.concat([
                    { data: 'total_defect_qty', name: 'total_defect_qty', className: 'text-end', render: d => Number(d||0).toFixed(2) },
                    { data: 'disposed_qty', name: 'disposed_qty', className: 'text-end', render: d => Number(d||0).toFixed(2) },
                    { data: 'returned_qty', name: 'returned_qty', className: 'text-end', render: d => Number(d||0).toFixed(2) },
                    { data: 'ending_defect_qty', name: 'ending_defect_qty', className: 'text-end', render: d => Number(d||0).toFixed(2) },
                ]);

                stockTable = $('#defects-stock-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.manajemenstok.defects.stock-summary') }}",
                        data: function(d){
                            d['search.value'] = $('#stock_search_input').val();
                            d['warehouse_id'] = $('#stock_warehouse_filter').val();
                        }
                    },
                    columns: stockColumns,
                    order: [ [0, 'asc'] ],
                });

                const debounce2 = (callback, wait) => {
                    let timeoutId = null;
                    return (...args) => {
                        window.clearTimeout(timeoutId);
                        timeoutId = window.setTimeout(() => { callback.apply(null, args); }, wait);
                    };
                }
                $('#stock_search_input').on('keyup', debounce2(function(){
                    stockTable.draw();
                }, 400));
                $('#stock_warehouse_filter').on('change', function(){
                    stockTable.draw();
                });
            };

            // Hook to tab change
            $('a[data-bs-toggle="tab"][href="#tab-stock"]').on('shown.bs.tab', function(){
                initStockTable();
            });
        
            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif
            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif
        });
    </script>
@endpush

@push('styles')
<style>
  .chip { display:inline-block; padding:.35rem .65rem; border-radius:.5rem; font-weight:700; font-size:.8rem; }
  .chip-default{ background:#F3F4F6; color:#374151; }
  .chip-success{ background:#ECFDF5; color:#047857; }
  .chip-warning{ background:#FEF3C7; color:#92400E; }
  .chip-danger{ background:#FEE2E2; color:#991B1B; }
  .meta-label { color:#6B7280; font-weight:600; font-size:.85rem; }
  .meta-value { font-weight:700; color:#111827; }
  .info-box { border: 1px solid #EFF2F5; border-radius:.475rem; padding:1rem; background:#fff; }
  .section-title { font-weight:700; color:#111827; margin-bottom:.5rem; }
  .modal.fade .modal-dialog { transform: translate(0, -10px); transition: transform .2s ease-out; }
  .modal.show .modal-dialog { transform: translate(0, 0); }
  .w-160px { width:160px; }
  .text-break { word-break: break-word; }
  .action-highlight { border:1px solid #DBEAFE; background:linear-gradient(180deg,#EFF6FF 0%,#FFFFFF 100%); border-radius:.75rem; padding:1rem; }
  .action-highlight .title { font-weight:800; color:#1E3A8A; display:flex; align-items:center; gap:.5rem; }
  .action-highlight .subtitle { color:#3B82F6; font-weight:600; }
  .action-highlight .chips { display:flex; gap:.5rem; flex-wrap:wrap; }
  .action-highlight .icon { color:#2563EB; }
  /* Keep table default styling */
</style>
@endpush

@push('scripts')
<script>
  $(function(){
    const modalId = '#defectActionModal';
    const $modal = $(modalId);
    const infoUrlTpl = `{{ route('admin.manajemenstok.defects.info', ':id') }}`;
    // const editUrlTpl = `{{ route('admin.manajemenstok.defects.index') }}`.replace('/defects','/defects/:id/edit#action');
    const updateUrlTpl = `{{ route('admin.manajemenstok.defects.action', ':id') }}`;
    const completeUrlTpl = `{{ route('admin.manajemenstok.defects.action.complete', ':id') }}`;
    const approveUrlTpl = `{{ route('admin.manajemenstok.defects.approve', ':id') }}`;
    const csrfToken = `{{ csrf_token() }}`;
    const formatDate = window.formatDate || function(value){
      if (!value) return '';
      const dt = new Date(value);
      if (isNaN(dt.getTime())) return value;
      return dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
    };
    const formatDateTime = window.formatDateTime || function(value){
      if (!value) return '';
      const dt = new Date(value);
      if (isNaN(dt.getTime())) return value;
      return dt.toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
    };

    // Approve defect (dropdown action)
    $(document).on('click', '.btn-approve-defect', function(){
      const id = $(this).data('id');
      const code = $(this).data('code') || '';
      const url = approveUrlTpl.replace(':id', id);
      Swal.fire({
        text: `Approve barang rusak ${code}? Tindakan ini akan mengurangi stok.`,
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal',
        buttonsStyling: false,
        customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'}
      }).then(function(res){
        if (!res.isConfirmed) return;
        $.post(url, {_token: csrfToken})
          .done(function(){
            Swal.fire({ text: 'Barang rusak di-approve.', icon: 'success', buttonsStyling:false, confirmButtonText:'OK', customClass:{confirmButton:'btn btn-primary'} });
            $('#defects-table').DataTable().ajax.reload(null, false);
          })
          .fail(function(xhr){
            const msg = xhr.responseJSON?.message || 'Gagal approve defect.';
            Swal.fire({ text: msg, icon: 'error', confirmButtonText: 'OK', buttonsStyling: false, customClass:{confirmButton:'btn btn-primary'} });
          });
      });
    });

    $(document).on('click', '.btn-handle-action', function(){
      const id = $(this).data('id');
      if(!id) return;
      const url = infoUrlTpl.replace(':id', id);
      // Reset placeholders
      fillModal({});
      $modal.modal('show');
      $.get(url).done(function(res){
        fillModal(res || {});
        // no external manage button; handled inline
        setupForms(id, res || {});
      }).fail(function(){
        $modal.find('.modal-body').html('<div class="alert alert-danger">Gagal memuat informasi defect.</div>');
      });
    });

    function badge(val, map){
      const cls = map[val] || 'chip-default';
      const text = (val||'-').toString().replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
      return `<span class="chip ${cls}">${text}</span>`;
    }

    function fillModal(data){
      const statusMap = { 'pending':'chip-warning', 'approved':'chip-success', 'cancelled':'chip-danger' };
      const actionStatusMap = { 'pending':'chip-warning', 'in_progress':'chip-default', 'completed':'chip-success', 'cancelled':'chip-danger' };
      const actionLabel = (data.action||'').toString().replace(/_/g,' ');
      const actionPretty = actionLabel ? actionLabel.replace(/\b\w/g, c=>c.toUpperCase()) : 'Belum Ada Penanganan';
      const actionChip = data.action ? `<span class="chip chip-default">${actionPretty}</span>` : `<span class="chip chip-warning">Belum Ada</span>`;
      const actionStatusChip = data.action_status ? `<span class="chip ${actionStatusMap[data.action_status]||'chip-default'}">${(data.action_status||'-').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase())}</span>` : '';
      const headerHtml = `
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
          <div>
            <div class="title"><i class="fas fa-tools icon"></i> Penanganan Defect</div>
            <div class="subtitle">${(data.item? (data.item.sku||'')+' - '+(data.item.nama_barang||'') : '-')}</div>
          </div>
          <div class="chips">${actionChip}${actionStatusChip}</div>
        </div>`;
      $modal.find('.action-highlight').html(headerHtml);
      const rows = [
        {label:'Kode', value: data.code || '-'},
        {label:'Tanggal', value: formatDate(data.date) || '-'},
        {label:'Gudang', value: (data.warehouse && data.warehouse.name) || '-'},
        {label:'Item', value: data.item ? `${data.item.sku||'-'} - ${data.item.nama_barang||'-'}` : '-'},
        {label:'Qty', value: (data.quantity != null ? data.quantity : '-')},
        {label:'Koli', value: (data.koli != null ? Number(data.koli).toFixed(2) : '-')},
        {label:'Status', value: badge(data.status, statusMap)},
        {label:'Action', value: data.action ? `<span class="chip chip-default">${actionPretty}</span>` : '<span class="chip chip-warning">Belum Ada</span>'},
        {label:'Action Status', value: data.action_status ? `<span class="chip ${actionStatusMap[data.action_status]||'chip-default'}">${(data.action_status||'-').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase())}</span>` : '<span class="text-muted">-</span>'},
        {label:'Dibuat Oleh', value: data.created_by || '-'},
        {label:'Disetujui Oleh', value: data.approved_by || '-'},
        {label:'Waktu Approve', value: formatDateTime(data.approved_at) || '-'},
      ];
      const meta = rows.map(r => `
        <div class="col-md-6 mb-3">
          <div class="meta-label">${r.label}</div>
          <div class="meta-value text-break">${r.value}</div>
        </div>
      `).join('');
      const notes = (data.notes && data.notes.length) ? data.notes : '<span class="text-muted">-</span>';
      $modal.find('.defect-meta').html(meta);
      $modal.find('.defect-notes').html(notes);
      // no external manage button; handled inline
    }

    function setupForms(id, data){
      const canHandle = (data.status || '').toLowerCase() === 'approved';
      const startVisible = canHandle && (!data.action || (data.action_status || '').toLowerCase() === 'pending');
      const completeVisible = canHandle && data.action && (data.action_status || '').toLowerCase() === 'in_progress';
      const $start = $modal.find('.defect-action-start');
      const $complete = $modal.find('.defect-action-complete');
      $start.toggleClass('d-none', !startVisible);
      $complete.toggleClass('d-none', !completeVisible);

      // Populate start form
      const $startForm = $modal.find('#defect-start-form');
      $startForm.find('input[name="defect_id"]').val(id);
      $startForm.attr('action', updateUrlTpl.replace(':id', id));
      if (data.action) $startForm.find('select[name="action"]').val(data.action);
      $startForm.find('textarea[name="action_notes"]').val(data.action_notes || '');

      const $completeForm = $modal.find('#defect-complete-form');
      $completeForm.find('input[name="defect_id"]').val(id);
      $completeForm.attr('action', completeUrlTpl.replace(':id', id));
      $completeForm.find('textarea[name="action_notes"]').val('');
    }

    // Re-evaluate visibility on modal shown (safety)
    $modal.on('shown.bs.modal', function(){
      const id = $modal.find('#defect-start-form input[name="defect_id"]').val();
      if (id) {
        const url = infoUrlTpl.replace(':id', id);
        $.get(url).done(function(res){ setupForms(id, res || {}); });
      }
    });

    // Submit handlers
    $(document).on('submit', '#defect-start-form', function(e){
      e.preventDefault();
      const $form = $(this);
      const url = $form.attr('action');
      const data = $form.serialize();
      const $btn = $form.find('button[type="submit"]');
      Swal.fire({
        text: 'Apakah Anda yakin ingin memulai penanganan untuk defect ini?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya, Mulai', cancelButtonText: 'Batal',
        buttonsStyling: false,
        customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'}
      }).then(function(result){
        if(!result.isConfirmed) return;
        $btn.prop('disabled', true);
        $.post(url, data)
          .done(function(){
            Swal.fire({ text: 'Penanganan dimulai.', icon: 'success', buttonsStyling:false, confirmButtonText:'OK', customClass:{confirmButton:'btn btn-primary'} });
            $('#defects-table').DataTable().ajax.reload(null, false);
            $(modalId).modal('hide');
          })
          .fail(function(xhr){
            const msg = xhr.responseJSON?.message || 'Gagal memulai penanganan.';
            Swal.fire({ text: msg, icon: 'error', confirmButtonText: 'OK', buttonsStyling: false, customClass:{confirmButton:'btn btn-primary'} });
          })
          .always(function(){ $btn.prop('disabled', false); });
      });
    });

    $(document).on('submit', '#defect-complete-form', function(e){
      e.preventDefault();
      const $form = $(this);
      const url = $form.attr('action');
      const data = $form.serialize();
      const $btn = $form.find('button[type="submit"]');
      Swal.fire({
        text: 'Apakah Anda yakin ingin menyelesaikan penanganan defect ini?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya, Selesaikan', cancelButtonText: 'Batal',
        buttonsStyling: false,
        customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'}
      }).then(function(result){
        if(!result.isConfirmed) return;
        $btn.prop('disabled', true);
        $.post(url, data)
          .done(function(){
            Swal.fire({ text: 'Tindakan diselesaikan.', icon: 'success', buttonsStyling:false, confirmButtonText:'OK', customClass:{confirmButton:'btn btn-primary'} });
            $('#defects-table').DataTable().ajax.reload(null, false);
            $(modalId).modal('hide');
          })
          .fail(function(xhr){
            const msg = xhr.responseJSON?.message || 'Gagal menyelesaikan tindakan.';
            Swal.fire({ text: msg, icon: 'error', confirmButtonText: 'OK', buttonsStyling: false, customClass:{confirmButton:'btn btn-primary'} });
          })
          .always(function(){ $btn.prop('disabled', false); });
      });
    });
  });
</script>

<!-- Modal: Defect Action Info -->
<div class="modal fade" id="defectActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Informasi Barang Defect</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="action-highlight mb-5"></div>
        <div class="info-box mb-5">
          <div class="section-title">Detail</div>
          <div class="row defect-meta"></div>
        </div>
        <div class="info-box">
          <div class="section-title">Catatan</div>
          <div class="defect-notes"></div>
        </div>
        <div class="info-box mt-5 defect-action-start d-none">
          <div class="section-title">Mulai Penanganan</div>
          <form id="defect-start-form" method="POST">
            @csrf
            <input type="hidden" name="defect_id" value="">
            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label required">Pilih Tindakan</label>
                <select name="action" class="form-select form-select-solid" required>
                  <option value="">Pilih</option>
                  <option value="disposal">Disposal</option>
                  {{-- <option value="return_to_vendor">Return to Vendor</option> --}}
                  {{-- <option value="rework">Rework</option> --}}
                  <option value="return_to_stock">Kembalikan ke Stok</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-md-7">
                <label class="form-label">Catatan</label>
                <textarea name="action_notes" rows="1" class="form-control form-control-solid" placeholder="Catatan (opsional)"></textarea>
              </div>
            </div>
            <div class="mt-4">
              <button type="submit" class="btn btn-info">Mulai Penanganan</button>
            </div>
          </form>
        </div>
        <div class="info-box mt-5 defect-action-complete d-none">
          <div class="section-title">Selesaikan Penanganan</div>
          <form id="defect-complete-form" method="POST">
            @csrf
            <input type="hidden" name="defect_id" value="">
            <div class="mb-3">
              <label class="form-label">Catatan Akhir</label>
              <textarea name="action_notes" rows="2" class="form-control form-control-solid" placeholder="Catatan (opsional)"></textarea>
            </div>
            <button type="submit" class="btn btn-success">Selesaikan</button>
          </form>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
 </div>
@endpush







