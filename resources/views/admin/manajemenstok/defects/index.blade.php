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
                <div class="d-flex align-items-center justify-content-between mb-5">
                    <div class="d-flex align-items-center gap-3">
                        <select id="status_filter" class="form-select form-select-solid" style="width:220px">
                            <option value="semua">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input class="form-control form-control-solid" placeholder="Pilih Tanggal" id="date_filter"
                            style="width:220px" />
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
                                <th>Dicatat Oleh</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
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
            let columns = [{
                    data: 'code',
                    name: 'code'
                },
                {
                    data: 'date',
                    name: 'date'
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
                    data: 'user_name',
                    name: 'user.name'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function(data, type, row) {
                        let editUrl = `{{ route('admin.manajemenstok.defects.index') }}`.replace(
                            '/defects', `/defects/${row.id}/edit`);
                        let html = '';
                        @if ($canEdit)
                            if (row.status === 'pending') {
                                html += `<a href="${editUrl}" class="btn btn-sm btn-light-primary me-2">Edit</a>`;
                            }
                            if (row.status === 'approved') {
                                html += `<a href="${editUrl}#action" class="btn btn-sm btn-light-info">Penanganan</a>`;
                            }
                        @endif
                        return html;
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
                    }
                },
                columns: columns,
                order: [
                    [1, 'desc']
                ]
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

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif
            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif
        });
    </script>
@endpush
