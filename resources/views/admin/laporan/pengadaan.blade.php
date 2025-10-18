@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Pengadaan',
        'breadcrumbs' => ['Admin', 'Laporan', 'Pengadaan'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">

        <!-- Filter Bar Top -->
        <div class="card mb-6">
            <div class="card-body py-5">
                <form id="filter_form" class="d-flex align-items-center flex-wrap gap-3">
                    <div class="d-flex align-items-center position-relative">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <i class="fas fa-calendar-alt"></i>
                        </span>
                        <input class="form-control form-control-solid w-300px ps-15" placeholder="Pilih Rentang Tanggal" id="kt_daterangepicker" name="date_range"/>
                    </div>
                    <div style="width: 220px;">
                        <select id="status_filter" class="form-select form-select-solid" data-kt-select2="true" name="status_filter">
                            <option value="semua">Semua Status</option>
                            <option value="requested">Requested</option>
                            <option value="on_shipping">On Shipping</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div style="width: 220px;">
                        <select id="type_filter" class="form-select form-select-solid" data-kt-select2="true" name="type_filter">
                            <option value="semua">Semua Tipe</option>
                            <option value="import">Import</option>
                            <option value="produksi">Produksi</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    @if (!auth()->user()->warehouse_id)
                        <div style="width: 260px;">
                            <select id="warehouse_filter" class="form-select form-select-solid" data-kt-select2="true" name="warehouse_filter">
                                <option value="">Semua Gudang</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button type="button" class="btn btn-primary" id="apply_filter">Apply</button>
                    <button type="button" class="btn btn-light btn-active-light-primary" id="reset_filter">Reset</button>
                </form>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-3">
                <a href="#" class="card bg-body-white hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-primary ms-n1">
                            <i class="fas fa-file-alt fs-2x text-primary"></i>
                        </span>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-5 kpi-total-orders">{{ number_format($stats['total_orders'] ?? 0, 0, ',', '.') }}</div>
                        <div class="fw-bold text-gray-400">Total Dokumen</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-success hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-box-open fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5 kpi-ordered-qty">{{ number_format($stats['ordered_qty'] ?? 0, 2, ',', '.') }}</div>
                        <div class="fw-bold text-white">Qty Diajukan</div>
                        <div class="fw-bold text-white-75 mt-2">Koli: <span class="kpi-ordered-koli">{{ number_format($stats['ordered_koli'] ?? 0, 2, ',', '.') }}</span></div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-info hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-truck-loading fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5 kpi-distributed-qty">{{ number_format($stats['distributed_qty'] ?? 0, 2, ',', '.') }}</div>
                        <div class="fw-bold text-white">Qty Terdistribusi</div>
                        <div class="fw-bold text-white-75 mt-2">Koli: <span class="kpi-distributed-koli">{{ number_format($stats['distributed_koli'] ?? 0, 2, ',', '.') }}</span></div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-primary hoverable card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <i class="fas fa-clipboard-check fs-2x text-white"></i>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5 kpi-accepted-qty">{{ number_format($stats['accepted_qty'] ?? 0, 2, ',', '.') }}</div>
                        <div class="fw-bold text-white">Qty Diterima</div>
                        <div class="fw-bold text-white-75 mt-2">Koli: <span class="kpi-accepted-koli">{{ number_format($stats['accepted_koli'] ?? 0, 2, ',', '.') }}</span></div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-7">
            <div class="col-xl-4">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-body">
                        <div class="text-muted fs-7">Sisa Qty</div>
                        <div class="text-dark fw-bolder fs-2 mt-3 kpi-remaining-qty">{{ number_format($stats['remaining_qty'] ?? 0, 2, ',', '.') }}</div>
                        <div class="text-muted fs-8">Total sisa kuantitas pengadaan</div>
                        <div class="text-muted fs-8 mt-2">Koli Sisa: <span class="kpi-remaining-koli">{{ number_format($stats['remaining_koli'] ?? 0, 2, ',', '.') }}</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-body">
                        <div class="text-muted fs-7">Progress Penyelesaian</div>
                        <div class="text-dark fw-bolder fs-2 mt-3 kpi-progress-pct">{{ number_format($stats['progress_pct'] ?? 0, 2, ',', '.') }}%</div>
                        <div class="text-muted fs-8">(Qty Diajukan - Sisa) / Diajukan</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Status Dokumen</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5 d-flex justify-content-center align-items-center">
                        <div id="status_donut_chart" style="height: 220px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">Data Pengadaan</div>
            </div>
            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table id="pengadaan_table" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode</th>
                                <th>Tanggal</th>
                                <th>Tipe</th>
                                <th>Gudang</th>
                                <th class="text-end">Jml Item</th>
                                <th class="text-end">Qty Diajukan</th>
                                <th class="text-end">Qty Sisa</th>
                                <th class="text-end">Koli Diajukan</th>
                                <th class="text-end">Koli Sisa</th>
                                <th class="text-end">Progress</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-8">
            <div class="card-header border-0 pt-6">
                <div class="card-title">Distribusi Item Pengadaan</div>
            </div>
            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table id="pengadaan_distributions_table" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tanggal</th>
                                <th>Kode Dokumen</th>
                                <th>Tipe</th>
                                <th>Tujuan Gudang</th>
                                <th>Barang</th>
                                <th class="text-end">Qty</th>
                                <th class="text-end">Koli</th>
                                <th>Status</th>
                                <th>Catatan</th>
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
    <script src="{{ asset('metronic/assets/plugins/custom/apexcharts/apexcharts.bundle.js') }}"></script>
    <script>
        var statusChart;
        var table;
        var distTable;

        document.addEventListener('DOMContentLoaded', function() {
            if ($.fn.select2) {
                $('[data-kt-select2="true"]').select2();
            }

            // Date range picker
            var start = moment().subtract(29, 'days');
            var end = moment();
            @if($dateRange)
                var dates = "{{$dateRange}}".split(' - ');
                start = moment(dates[0], 'DD/MM/YYYY');
                end = moment(dates[1], 'DD/MM/YYYY');
            @endif
            function cb(start, end) {
                $('#kt_daterangepicker').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            }
            $("#kt_daterangepicker").daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                   'Hari Ini': [moment(), moment()],
                   'Kemarin': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   '7 Hari Terakhir': [moment().subtract(6, 'days'), moment()],
                   '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                   'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                   'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                locale: { format: 'DD/MM/YYYY' }
            }, cb);
            cb(start, end);

            initStatusChart();
            initTable();
            initDistTable();

            $('#apply_filter').on('click', function() {
                refreshAll();
            });
            $('#reset_filter').on('click', function() {
                $('#status_filter').val('semua').trigger('change');
                $('#type_filter').val('semua').trigger('change');
                $('#warehouse_filter').val('').trigger('change');
                cb(moment().subtract(29, 'days'), moment());
                refreshAll();
            });
        });

        function initStatusChart() {
            var element = document.getElementById('status_donut_chart');
            if (!element) return;

            var series = [
                {{ (int)($stats['status_counts']['requested'] ?? 0) }},
                {{ (int)($stats['status_counts']['on_shipping'] ?? 0) }},
                {{ (int)($stats['status_counts']['completed'] ?? 0) }},
                {{ (int)($stats['status_counts']['rejected'] ?? 0) }},
            ];
            var labels = ['Requested','On Shipping','Completed','Rejected'];
            var options = {
                series: series,
                labels: labels,
                chart: { type: 'donut', height: 220 },
                legend: { position: 'bottom' }
            };
            statusChart = new ApexCharts(element, options);
            statusChart.render();
        }

        function refreshAll() {
            // 1) Refresh KPI + Chart via AJAX
            var wf = $('#warehouse_filter').length ? $('#warehouse_filter').val() : '';
            $.ajax({
                url: "{{ route('admin.laporan.pengadaan.index') }}",
                method: 'GET',
                data: {
                    date_range: $('#kt_daterangepicker').val(),
                    status_filter: $('#status_filter').val(),
                    type_filter: $('#type_filter').val(),
                    warehouse_filter: wf
                },
                success: function(resp) {
                    if (resp && resp.success && resp.stats) {
                        var s = resp.stats;
                        // Update KPI numbers
                        updateText('.kpi-total-orders', s.total_orders);
                        updateText('.kpi-ordered-qty', s.ordered_qty, true);
                        updateText('.kpi-ordered-koli', s.ordered_koli, true);
                        updateText('.kpi-distributed-qty', s.distributed_qty, true);
                        updateText('.kpi-distributed-koli', s.distributed_koli, true);
                        updateText('.kpi-accepted-qty', s.accepted_qty, true);
                        updateText('.kpi-accepted-koli', s.accepted_koli, true);
                        updateText('.kpi-remaining-qty', s.remaining_qty, true);
                        updateText('.kpi-remaining-koli', s.remaining_koli, true);
                        updateText('.kpi-progress-pct', s.progress_pct, true, '%');

                        // Update donut chart series
                        var series = [
                            s.status_counts.requested || 0,
                            s.status_counts.on_shipping || 0,
                            s.status_counts.completed || 0,
                            s.status_counts.rejected || 0
                        ];
                        statusChart.updateSeries(series);
                    }
                }
            });

            // 2) Refresh DataTable
            if ($.fn.DataTable.isDataTable('#pengadaan_table')) {
                $('#pengadaan_table').DataTable().ajax.reload();
            }
            if ($.fn.DataTable.isDataTable('#pengadaan_distributions_table')) {
                $('#pengadaan_distributions_table').DataTable().ajax.reload();
            }
        }

        function updateText(selector, value, isNumber, suffix) {
            var el;
            // Accept multiple elements with same marker
            document.querySelectorAll(selector).forEach(function(node) {
                if (isNumber) {
                    var num = parseFloat(value || 0);
                    node.textContent = num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + (suffix || '');
                } else {
                    node.textContent = value;
                }
            });
        }

        function initTable() {
            table = $('#pengadaan_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.laporan.pengadaan.data') }}",
                    type: "GET",
                    data: function(d) {
                        d.search.value = d.search.value || '';
                        d.date_range = $('#kt_daterangepicker').val();
                        d.status_filter = $('#status_filter').val();
                        d.type_filter = $('#type_filter').val();
                        d.warehouse_filter = $('#warehouse_filter').length ? $('#warehouse_filter').val() : '';
                    }
                },
                columns: [
                    { data: 'code', name: 'sio.code' },
                    { data: 'date', name: 'sio.date' },
                    { data: 'type', name: 'sio.type', render: function(data){
                        return '<span class="badge badge-light-primary fw-bolder">' + (data || '-') + '</span>';
                    }},
                    { data: 'warehouse_name', name: 'w.name', defaultContent: '-' },
                    { data: 'items_count', name: 'items_count', className: 'text-end' },
                    { data: 'ordered_qty', name: 'ordered_qty', className: 'text-end', render: numFormat },
                    { data: 'remaining_qty', name: 'remaining_qty', className: 'text-end', render: numFormat },
                    { data: 'ordered_koli', name: 'ordered_koli', className: 'text-end', render: numFormat },
                    { data: 'remaining_koli', name: 'remaining_koli', className: 'text-end', render: numFormat },
                    { data: 'progress', name: 'progress', className: 'text-end', render: function(val){
                        var v = parseFloat(val || 0).toFixed(2);
                        var cls = 'badge-light-success';
                        if (v < 30) cls = 'badge-light-danger'; else if (v < 70) cls = 'badge-light-warning';
                        return '<span class="badge ' + cls + ' fw-bolder">' + v + '%</span>';
                    } },
                    { data: 'status', name: 'sio.status', render: function(data){
                        var map = { requested: 'badge-light-info', on_shipping: 'badge-light-primary', completed: 'badge-light-success', rejected: 'badge-light-danger' };
                        var cls = map[data] || 'badge-light';
                        return '<span class="badge ' + cls + ' fw-bolder">' + (data || '-') + '</span>';
                    }},
                    { data: null, orderable: false, searchable: false, render: function(row){
                        var url = "{{ url('admin/stok-masuk/pengadaan') }}/" + row.id;
                        return '<a href="' + url + '" class="btn btn-sm btn-light-primary">Detail</a>';
                    }}
                ],
                order: [[1, 'desc']],
            });
        }

        function initDistTable() {
            distTable = $('#pengadaan_distributions_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.laporan.pengadaan.distributions.data') }}",
                    type: "GET",
                    data: function(d) {
                        d.search.value = d.search.value || '';
                        d.date_range = $('#kt_daterangepicker').val();
                        d.status_filter = $('#status_filter').val();
                        d.type_filter = $('#type_filter').val();
                        d.warehouse_filter = $('#warehouse_filter').length ? $('#warehouse_filter').val() : '';
                    }
                },
                columns: [
                    { data: 'date', name: 'd.date' },
                    { data: 'sio_code', name: 'sio.code' },
                    { data: 'sio_type', name: 'sio.type', render: function(v){ return '<span class="badge badge-light-primary fw-bolder">' + (v || '-') + '</span>'; } },
                    { data: 'to_warehouse', name: 'wto.name', defaultContent: '-' },
                    { data: null, name: 'i.nama_barang', render: function(row){
                        var name = row.item_name || '-';
                        var sku = row.sku || '-';
                        return '<div class="d-flex flex-column"><span class="fw-bolder">' + name + '</span><span class="text-muted fs-8">' + sku + '</span></div>';
                    } },
                    { data: 'quantity', name: 'd.quantity', className: 'text-end', render: numFormat },
                    { data: 'koli', name: 'd.koli', className: 'text-end', render: numFormat },
                    { data: 'dist_status', name: 'd.status', render: function(v){
                        var map = { draft: 'badge-light-warning', completed: 'badge-light-success' };
                        return '<span class="badge ' + (map[v] || 'badge-light') + ' fw-bolder">' + (v || '-') + '</span>';
                    } },
                    { data: 'note', name: 'd.note', render: function(v){ return v ? $('<div>').text(v).html() : '-'; } },
                ],
                order: [[0, 'desc']],
            });
        }

        function numFormat(data) {
            var val = parseFloat(data || 0);
            return val.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    </script>
    
@endpush
