@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Stock Opname',
        'breadcrumbs' => ['Admin', 'Laporan', 'Stock Opname'],
    ])
@endpush

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .stat-card .symbol-label { background: #EEF6FF; }
        .stat-card .symbol-label i { color: #1D4ED8; }
        #opname_discrepancy_chart {
            min-height: 320px;
        }
        .recent-session .badge {
            min-width: 80px;
        }
    </style>
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card mb-7">
            <div class="card-body">
                <div class="row g-5 g-xl-8 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Rentang Tanggal</label>
                        <input type="text" id="filter_date_range" class="form-control form-control-solid"
                            placeholder="Pilih rentang tanggal">
                    </div>
                    @if (auth()->user()->warehouse_id === null)
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Gudang</label>
                            <select id="filter_warehouse" class="form-select form-select-solid" data-kt-select2="true"
                                data-placeholder="Semua gudang">
                                <option value="semua">Semua Gudang</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-4 text-md-end">
                        <button type="button" class="btn btn-light me-3" id="reset_filter">Reset</button>
                        <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-7">
            <div class="col-xl-3 col-sm-6">
                <div class="card stat-card card-xl-stretch">
                    <div class="card-body d-flex align-items-center">
                        <div class="symbol symbol-40px me-4">
                            <span class="symbol-label">
                                <i class="fas fa-clipboard-check fs-2"></i>
                            </span>
                        </div>
                        <div>
                            <div class="text-muted fw-bold fs-8">Total Opname</div>
                            <div class="fs-2 fw-bolder text-gray-900" id="stat_total_sessions">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card stat-card card-xl-stretch">
                    <div class="card-body d-flex align-items-center">
                        <div class="symbol symbol-40px me-4">
                            <span class="symbol-label">
                                <i class="fas fa-boxes fs-2"></i>
                            </span>
                        </div>
                        <div>
                            <div class="text-muted fw-bold fs-8">Total Item Dihitung</div>
                            <div class="fs-2 fw-bolder text-gray-900" id="stat_total_items">-</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card stat-card card-xl-stretch bg-light-success">
                    <div class="card-body d-flex align-items-center">
                        <div>
                            <div class="text-success fw-bold fs-8">Surplus Qty</div>
                            <div class="fs-2 fw-bolder text-success" id="stat_surplus_qty">-</div>
                            <div class="text-success fs-8">Selisih positif</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-sm-6">
                <div class="card stat-card card-xl-stretch bg-light-danger">
                    <div class="card-body d-flex align-items-center">
                        <div>
                            <div class="text-danger fw-bold fs-8">Shortage Qty</div>
                            <div class="fs-2 fw-bolder text-danger" id="stat_shortage_qty">-</div>
                            <div class="text-danger fs-8">Selisih negatif</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-7">
            <div class="col-xl-7">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Top Selisih Item</span>
                            <span class="text-muted mt-1 fw-bold fs-7">5 Item dengan selisih terbesar</span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="opname_discrepancy_chart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Opname Terbaru</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Ringkasan 5 opname terakhir</span>
                        </h3>
                    </div>
                    <div class="card-body" id="recent_sessions_list">
                        <div class="text-muted">Belum ada data.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-7">
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Item Surplus</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Stok fisik lebih tinggi</span>
                        </div>
                        <div class="card-toolbar">
                            <input type="text" class="form-control form-control-sm" id="surplus_search" placeholder="Cari SKU/Nama">
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-row-dashed fs-7 gy-4" id="surplus-table">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold text-uppercase gs-0">
                                    <th>SKU</th>
                                    <th>Nama Item</th>
                                    <th class="text-end">Jumlah Opname</th>
                                    <th class="text-end">Total Surplus</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <div class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Item Shortage</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Stok fisik lebih rendah</span>
                        </div>
                        <div class="card-toolbar">
                            <input type="text" class="form-control form-control-sm" id="shortage_search" placeholder="Cari SKU/Nama">
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="table table-row-dashed fs-7 gy-4" id="shortage-table">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bold text-uppercase gs-0">
                                    <th>SKU</th>
                                    <th>Nama Item</th>
                                    <th class="text-end">Jumlah Opname</th>
                                    <th class="text-end">Total Shortage</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bolder mb-0">Daftar Stock Opname</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock-opname-report-table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode</th>
                                <th>Tanggal Selesai</th>
                                <th>Gudang</th>
                                <th>Jumlah Item</th>
                                <th>Surplus Qty</th>
                                <th>Shortage Qty</th>
                                <th>Net Qty</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-bold"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let stockOpnameTable;
        let discrepancyChart;
        let dateRangePicker;

        const defaultRange = [
            moment().subtract(29, 'days').format('YYYY-MM-DD'),
            moment().format('YYYY-MM-DD')
        ];

        const routes = {
            summary: "{{ route('admin.laporan.stockopname.summary') }}",
            data: "{{ route('admin.laporan.stockopname.data') }}"
        };

        const formatNumber = (value) => Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });

        $(document).ready(function() {
            dateRangePicker = $('#filter_date_range').flatpickr({
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: defaultRange
            });

            if ($('#filter_warehouse').length) {
                $('#filter_warehouse').select2({
                    placeholder: "Semua gudang",
                    allowClear: true
                });
            }

            initDataTable();
            loadSummary();

            $('#apply_filter').on('click', function() {
                stockOpnameTable.ajax.reload();
                loadSummary();
            });

            $('#reset_filter').on('click', function() {
                if (dateRangePicker) {
                    dateRangePicker.clear();
                    dateRangePicker.setDate(defaultRange);
                }
                if ($('#filter_warehouse').length) {
                    $('#filter_warehouse').val('semua').trigger('change');
                }
                stockOpnameTable.ajax.reload();
                loadSummary();
            });
        });

        function getFilters() {
            const warehouseElement = $('#filter_warehouse');
            const warehouseValue = warehouseElement.length ? warehouseElement.val() : null;
            return {
                date_range: $('#filter_date_range').val(),
                warehouse_id: warehouseValue && warehouseValue !== 'semua' ? warehouseValue : null
            };
        }

        function initDataTable() {
            stockOpnameTable = $('#stock-opname-report-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: routes.data,
                    data: function(d) {
                        const filters = getFilters();
                        d.date_range = filters.date_range;
                        d.warehouse_id = filters.warehouse_id;
                    }
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'code', name: 'so.code' },
                    {
                        data: 'completed_date',
                        name: 'so.completed_date',
                        render: function(data) {
                            return data ? moment(data).format('DD MMM YYYY') : '-';
                        }
                    },
                    { data: 'warehouse_name', name: 'warehouse_name', defaultContent: '-' },
                    { data: 'items_count', name: 'items_count', render: data => KTUtil.numberString(parseFloat(data || 0)) },
                    { data: 'surplus_qty', name: 'surplus_qty', render: data => formatQty(data, 'success') },
                    { data: 'shortage_qty', name: 'shortage_qty', render: data => formatQty(data, 'danger') },
                    {
                        data: 'net_qty',
                        name: 'net_qty',
                        render: function(data) {
                            const qty = parseFloat(data) || 0;
                            const badgeClass = qty >= 0 ? 'badge-light-success' : 'badge-light-danger';
                            const value = KTUtil.numberString(qty.toFixed(2));
                            return `<span class="badge ${badgeClass}">${value}</span>`;
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            const url = "{{ route('admin.manajemenstok.stok-opname.show', ':id') }}".replace(':id', data);
                            return `<a href="${url}" class="btn btn-sm btn-light-primary">Detail</a>`;
                        }
                    }
                ]
            });
        }

        function initSurplusTable() {
            surplusTable = $('#surplus-table').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                lengthChange: false,
                pageLength: 5,
                ajax: {
                    url: "{{ route('admin.laporan.stockopname.surplus') }}",
                    data: function(d) {
                        const filters = getFilters();
                        d.date_range = filters.date_range;
                        d.warehouse_id = filters.warehouse_id;
                        d.search.value = $('#surplus_search').val();
                    }
                },
                columns: [
                    { data: 'sku', name: 'sku' },
                    { data: 'nama_barang', name: 'nama_barang' },
                    {
                        data: 'opname_count',
                        name: 'opname_count',
                        className: 'text-end',
                        render: data => KTUtil.numberString(data)
                    },
                    {
                        data: 'total_qty',
                        name: 'total_qty',
                        className: 'text-end',
                        render: data => KTUtil.numberString(parseFloat(data).toFixed(2))
                    }
                ]
            });

            $('#surplus_search').on('keyup', debounce(function() {
                surplusTable.ajax.reload();
            }, 400));
        }

        function initShortageTable() {
            shortageTable = $('#shortage-table').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                lengthChange: false,
                pageLength: 5,
                ajax: {
                    url: "{{ route('admin.laporan.stockopname.shortage') }}",
                    data: function(d) {
                        const filters = getFilters();
                        d.date_range = filters.date_range;
                        d.warehouse_id = filters.warehouse_id;
                        d.search.value = $('#shortage_search').val();
                    }
                },
                columns: [
                    { data: 'sku', name: 'sku' },
                    { data: 'nama_barang', name: 'nama_barang' },
                    {
                        data: 'opname_count',
                        name: 'opname_count',
                        className: 'text-end',
                        render: data => KTUtil.numberString(data)
                    },
                    {
                        data: 'total_qty',
                        name: 'total_qty',
                        className: 'text-end',
                        render: data => KTUtil.numberString(parseFloat(data).toFixed(2))
                    }
                ]
            });

            $('#shortage_search').on('keyup', debounce(function() {
                shortageTable.ajax.reload();
            }, 400));
        }


        function formatQty(value, tone) {
            const number = parseFloat(value) || 0;
            const badgeClass = tone === 'success' ? 'badge-light-success' : 'badge-light-danger';
            return `<span class="badge ${badgeClass}">${KTUtil.numberString(number.toFixed(2))}</span>`;
        }

        function loadSummary() {
            const filters = getFilters();
            $.ajax({
                url: routes.summary,
                data: filters,
                success: function(response) {
                    $('#stat_total_sessions').text(formatNumber(response.total_sessions));
                    $('#stat_total_items').text(formatNumber(response.total_items));
                    $('#stat_surplus_qty').text(formatNumber(response.surplus_qty));
                    $('#stat_shortage_qty').text(formatNumber(response.shortage_qty));
                    renderDiscrepancyChart(response.top_items || []);
                    renderRecentSessions(response.recent_sessions || []);
                },
                error: function() {
                    toastr.error('Gagal memuat ringkasan.');
                }
            });
        }

        function renderDiscrepancyChart(data) {
            const element = document.getElementById('opname_discrepancy_chart');
            if (!element) return;

            const labels = data.map(item => item.label);
            const series = data.map(item => item.value);

            const options = {
                series: [{
                    name: 'Selisih Qty',
                    data: series
                }],
                chart: {
                    type: 'bar',
                    height: 320,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: true,
                        dataLabels: { position: 'top' }
                    }
                },
                dataLabels: {
                    enabled: true,
                    formatter: function(val) {
                        return parseFloat(val).toFixed(2);
                    },
                    offsetX: -6,
                    style: {
                        fontSize: '12px',
                        colors: ["#304758"]
                    }
                },
                xaxis: {
                    categories: labels,
                    labels: {
                        formatter: val => parseFloat(val).toFixed(0)
                    }
                },
                tooltip: {
                    y: {
                        formatter: val => parseFloat(val).toFixed(2)
                    }
                },
                colors: ['#1D4ED8']
            };

            if (discrepancyChart) {
                discrepancyChart.updateOptions(options);
            } else {
                discrepancyChart = new ApexCharts(element, options);
                discrepancyChart.render();
            }
        }

        function renderRecentSessions(sessions) {
            const container = $('#recent_sessions_list');
            if (!sessions.length) {
                container.html('<div class="text-muted">Belum ada data.</div>');
                return;
            }

            const rows = sessions.map(session => {
                return `
                    <div class="d-flex align-items-center justify-content-between recent-session mb-4">
                        <div>
                            <a href="${session.detail_url}" class="fw-bold text-gray-900">${session.code}</a>
                            <div class="text-muted fs-8">${session.warehouse}</div>
                            <div class="text-muted fs-8">${session.completed_date ?? '-'}</div>
                        </div>
                        <div class="text-end">
                            <div class="badge badge-light-success mb-1">+${formatNumber(session.surplus_qty)}</div>
                            <div class="badge badge-light-danger mb-1">-${formatNumber(session.shortage_qty)}</div>
                            <div class="badge ${session.net_qty >= 0 ? 'badge-light-primary' : 'badge-light-warning'}">
                                Net: ${formatNumber(session.net_qty)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            container.html(rows);
        }

        function renderItemList(targetId, items, tone) {
            const container = $('#' + targetId);
            if (!items.length) {
                container.html('<div class="text-muted">Belum ada data.</div>');
                return;
            }

            const badgeClass = tone === 'success' ? 'badge-light-success' : 'badge-light-danger';
            const lists = items.map(item => `
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <div class="fw-bold text-gray-900">${item.label}</div>
                    </div>
                    <span class="badge ${badgeClass}">${formatNumber(item.value)}</span>
                </div>
            `).join('');

            container.html(lists);
        }
    </script>
@endpush
