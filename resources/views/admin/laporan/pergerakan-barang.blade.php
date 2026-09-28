@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Pergerakan Barang',
        'breadcrumbs' => ['Admin', 'Laporan', 'Pergerakan Barang'],
    ])
@endpush

@push('styles')
    <style>
        .movement-card { cursor: pointer; border: 2px solid transparent; transition: border-color .15s ease; }
        .movement-card.active { border-color: var(--bs-primary); }
        .movement-card .share-bar { height: 6px; }
        #movement_table td { vertical-align: middle; }
    </style>
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">

        <!--begin::Filters-->
        <div class="card mb-7">
            <div class="card-body">
                <div class="row g-6 align-items-end">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label fw-bold">Periode (Tanggal Kirim)</label>
                        <div class="position-relative">
                            <span class="position-absolute top-50 translate-middle-y ms-4"><i class="fas fa-calendar-alt"></i></span>
                            <input class="form-control form-control-solid ps-12" id="date_range" placeholder="Pilih rentang tanggal" readonly />
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label fw-bold">Gudang</label>
                        <select id="warehouse_filter" class="form-select form-select-solid" data-kt-select2="true" data-hide-search="true" {{ $lockWarehouse ? 'disabled' : '' }}>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ ($activeWarehouse->id ?? null) == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label fw-bold">Klasifikasi</label>
                        <select id="class_filter" class="form-select form-select-solid" data-kt-select2="true" data-hide-search="true">
                            <option value="">Semua Klasifikasi</option>
                            @foreach ($classLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label fw-bold">Days Cover (hari)</label>
                        <div class="input-group input-group-solid">
                            <input type="number" min="0" step="0.1" class="form-control form-control-solid" id="cover_min" placeholder="Min" />
                            <span class="input-group-text">s/d</span>
                            <input type="number" min="0" step="0.1" class="form-control form-control-solid" id="cover_max" placeholder="Maks" />
                        </div>
                    </div>
                    <div class="col-12 col-xl-2 text-xl-end">
                        <button type="button" class="btn btn-light me-2" id="reset_filter">Reset</button>
                        <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                    </div>
                </div>
                <div class="text-muted fs-7 mt-4" id="filter_info">&nbsp;</div>
            </div>
        </div>
        <!--end::Filters-->

        <!--begin::Classification cards-->
        @php
            $cardMeta = [
                'fast' => ['color' => 'success', 'icon' => 'fa-bolt', 'hint' => 'Kumulatif 0 - ' . (int) $fastThreshold . '% qty keluar'],
                'medium' => ['color' => 'primary', 'icon' => 'fa-tachometer-alt', 'hint' => 'Kumulatif ' . (int) $fastThreshold . ' - ' . (int) $mediumThreshold . '% qty keluar'],
                'slow' => ['color' => 'warning', 'icon' => 'fa-hourglass-half', 'hint' => 'Kumulatif > ' . (int) $mediumThreshold . '% qty keluar'],
                'non_moving' => ['color' => 'danger', 'icon' => 'fa-pause-circle', 'hint' => 'Ada stok, tidak ada qty keluar'],
            ];
        @endphp
        <div class="alert alert-light-primary d-none align-items-center py-3 mb-4" id="cover_card_notice">
            <i class="fas fa-info-circle text-primary me-3"></i>
            <div class="fs-7">
                Kartu hanya menghitung item dengan days cover <span class="fw-bolder" id="cover_card_label"></span>
                (<span class="fw-bolder" id="cover_card_count"></span> dari <span id="cover_card_total"></span> SKU).
                Persentase qty tetap terhadap total qty keluar keseluruhan.
            </div>
        </div>
        <div class="row g-5 g-xl-8 mb-7">
            @foreach ($cardMeta as $key => $meta)
                <div class="col-6 col-xl-3">
                    <div class="card card-xl-stretch movement-card h-100" data-class="{{ $key }}">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge badge-light-{{ $meta['color'] }} fs-7 fw-bolder">{{ $classLabels[$key] }}</span>
                                <i class="fas {{ $meta['icon'] }} fs-2 text-{{ $meta['color'] }}"></i>
                            </div>
                            <div class="text-gray-900 fw-bolder fs-2x mt-4"><span data-summary="{{ $key }}.sku_count">-</span> <span class="fs-6 text-gray-500 fw-bold">SKU</span></div>
                            @if ($key !== 'non_moving')
                                <div class="text-gray-600 fw-bold fs-7 mt-1">
                                    <span data-summary="{{ $key }}.qty_share">-</span> dari total qty keluar
                                </div>
                                <div class="progress share-bar bg-light-{{ $meta['color'] }} mt-2">
                                    <div class="progress-bar bg-{{ $meta['color'] }}" data-summary-bar="{{ $key }}" style="width: 0%"></div>
                                </div>
                            @else
                                <div class="text-gray-600 fw-bold fs-7 mt-1">
                                    Stok tertahan: <span data-summary="{{ $key }}.stock">-</span>
                                </div>
                            @endif
                            <div class="text-muted fs-8 mt-2">{{ $meta['hint'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <!--end::Classification cards-->

        <!--begin::Table-->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="position-absolute ms-5"><i class="fas fa-search"></i></span>
                        <input type="text" id="table_search" class="form-control form-control-solid w-250px ps-13" placeholder="Cari SKU / nama item" />
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="text-muted fs-7 text-end me-4">
                        Total qty keluar: <span class="fw-bolder text-gray-800" id="total_out">-</span>
                        &middot; Hari periode: <span class="fw-bolder text-gray-800" id="period_days">-</span>
                    </div>
                    <a href="#" class="btn btn-sm btn-light-success" id="export_excel" title="Export laporan lengkap (ringkasan, detail, pareto, tren, perlu perhatian) untuk gudang & periode terpilih">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="table-responsive">
                    <table id="movement_table" class="table align-middle table-row-dashed fs-7 gy-4">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                                <th class="w-40px">No</th>
                                <th class="min-w-200px">SKU / Item</th>
                                <th class="text-end">Stok</th>
                                <th>Klasifikasi</th>
                                <th class="text-end">Qty Keluar</th>
                                <th class="text-end">Rata-rata / Hari</th>
                                <th class="text-end">Kontribusi (%)</th>
                                <th class="text-end">Frequency</th>
                                <th class="text-end">Days Cover</th>
                                <th>Terakhir Keluar</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-700"></tbody>
                    </table>
                </div>
                <div class="text-muted fs-8 mt-4">
                    Qty keluar dihitung dari barang yang dikirim atas Permintaan Barang dengan gudang asal sesuai filter, berdasarkan tanggal kirim.
                    Klasifikasi diurutkan dari qty keluar terbesar; item yang melintasi batas {{ (int) $fastThreshold }}% / {{ (int) $mediumThreshold }}% masuk ke lapisan tempat kontribusinya dimulai.
                    Frequency = jumlah dokumen Permintaan Barang yang dikirim. Days cover = stok saat ini &divide; rata-rata keluar per hari (Kritis &lt; 7, Waspada 7-30, Aman 30-90, Berlebih &gt; 90 hari).
                    Terakhir keluar = tanggal kirim terakhir hingga akhir periode.
                </div>
            </div>
        </div>
        <!--end::Table-->
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var classMeta = {
                fast: { color: 'success' },
                medium: { color: 'primary' },
                slow: { color: 'warning' },
                non_moving: { color: 'danger' }
            };
            var defaultWarehouse = $('#warehouse_filter').val();

            if ($.fn.select2) {
                $('[data-kt-select2="true"]').select2({ minimumResultsForSearch: Infinity });
            }

            function fmt(value, digits) {
                var n = parseFloat(value || 0);
                return n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: digits === undefined ? 2 : digits });
            }

            function escapeHtml(text) {
                return $('<div>').text(text == null ? '' : text).html();
            }

            // Date range (default 30 hari terakhir)
            var defaultStart = moment().subtract(29, 'days');
            var defaultEnd = moment();
            function setRange(start, end) {
                $('#date_range').val(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
            }
            $('#date_range').daterangepicker({
                startDate: defaultStart,
                endDate: defaultEnd,
                ranges: {
                    '30 Hari Terakhir': [moment().subtract(29, 'days'), moment()],
                    '60 Hari Terakhir': [moment().subtract(59, 'days'), moment()],
                    '90 Hari Terakhir': [moment().subtract(89, 'days'), moment()],
                    'Bulan Ini': [moment().startOf('month'), moment().endOf('month')],
                    'Bulan Lalu': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'Tahun Ini': [moment().startOf('year'), moment()]
                },
                locale: { format: 'DD/MM/YYYY' }
            }, setRange);
            setRange(defaultStart, defaultEnd);

            function coverInfo() {
                var min = $('#cover_min').val(), max = $('#cover_max').val();
                if (min === '' && max === '') return 'Semua';
                if (min === '') return '<= ' + max + ' hari';
                if (max === '') return '>= ' + min + ' hari';
                return min + ' - ' + max + ' hari';
            }

            function updateFilterInfo() {
                var cls = $('#class_filter').val();
                $('#filter_info').text(
                    'Periode: ' + $('#date_range').val()
                    + ' | Gudang: ' + $('#warehouse_filter option:selected').text()
                    + ' | Klasifikasi: ' + (cls ? $('#class_filter option:selected').text() : 'Semua')
                    + ' | Days Cover: ' + coverInfo()
                );
                $('#export_excel').attr('href', "{{ route('admin.laporan.pergerakanBarang.export') }}?" + $.param({
                    date_range: $('#date_range').val(),
                    warehouse_filter: $('#warehouse_filter').val()
                }));
                $('.movement-card').removeClass('active');
                if (cls) {
                    $('.movement-card[data-class="' + cls + '"]').addClass('active');
                }
            }

            function renderSummary(summary) {
                if (!summary) {
                    $('[data-summary]').text('-');
                    $('[data-summary-bar]').css('width', '0%');
                    $('#total_out, #period_days').text('-');
                    $('#cover_card_notice').addClass('d-none').removeClass('d-flex');
                    return;
                }
                var coveredSku = 0;
                $.each(summary.classes, function (key, data) {
                    coveredSku += data.sku_count;
                    $('[data-summary="' + key + '.sku_count"]').text(fmt(data.sku_count, 0));
                    $('[data-summary="' + key + '.qty_share"]').text(fmt(data.qty_share, 1) + '%');
                    $('[data-summary="' + key + '.stock"]').text(fmt(data.stock));
                    $('[data-summary-bar="' + key + '"]').css('width', Math.min(100, data.qty_share) + '%');
                });
                $('#total_out').text(fmt(summary.total_out));
                $('#period_days').text(fmt(summary.period_days, 0));

                $('#cover_card_notice').toggleClass('d-none', !summary.cover_filtered).toggleClass('d-flex', !!summary.cover_filtered);
                if (summary.cover_filtered) {
                    $('#cover_card_label').text(coverInfo());
                    $('#cover_card_count').text(fmt(coveredSku, 0));
                    $('#cover_card_total').text(fmt(summary.total_sku, 0));
                }
            }

            var table = $('#movement_table').DataTable({
                processing: true,
                serverSide: true,
                searching: true,
                dom: 'rt<"row mt-4"<"col-sm-12 col-md-5"li><"col-sm-12 col-md-7"p>>',
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                order: [[0, 'asc']],
                ajax: {
                    url: "{{ route('admin.laporan.pergerakanBarang.data') }}",
                    type: 'GET',
                    data: function (d) {
                        d.date_range = $('#date_range').val();
                        d.warehouse_filter = $('#warehouse_filter').val();
                        d.class_filter = $('#class_filter').val();
                        d.cover_min = $('#cover_min').val();
                        d.cover_max = $('#cover_max').val();
                    },
                    dataSrc: function (json) {
                        renderSummary(json.summary);
                        return json.data;
                    }
                },
                columns: [
                    { data: 'rank', render: function (data, type, row, meta) {
                        return meta.settings._iDisplayStart + meta.row + 1;
                    } },
                    { data: 'sku', render: function (data, type, row) {
                        return '<div class="d-flex flex-column">'
                            + '<span class="text-primary fw-bolder fs-6">' + escapeHtml(row.sku || '-') + '</span>'
                            + '<span class="text-muted fw-bold">' + escapeHtml(row.nama_barang || '-') + '</span>'
                            + '</div>';
                    } },
                    { data: 'stock', className: 'text-end', render: function (data, type, row) {
                        var cls = parseFloat(data) <= 0 ? 'text-danger' : 'text-gray-800';
                        return '<span class="fw-bolder ' + cls + '">' + fmt(data) + '</span>'
                            + (row.uom ? ' <span class="text-muted fs-8">' + escapeHtml(row.uom) + '</span>' : '');
                    } },
                    { data: 'classification', render: function (data, type, row) {
                        var color = (classMeta[data] || {}).color || 'secondary';
                        return '<span class="badge badge-light-' + color + ' fw-bolder">' + escapeHtml(row.classification_label) + '</span>';
                    } },
                    { data: 'qty_out', className: 'text-end', render: function (data) {
                        return '<span class="fw-bolder text-gray-800">' + fmt(data) + '</span>';
                    } },
                    { data: 'avg_per_day', className: 'text-end', render: function (data) { return fmt(data); } },
                    { data: 'contribution', className: 'text-end', render: function (data, type, row) {
                        if (row.cumulative === null) return '<span class="text-muted">0%</span>';
                        return '<div class="fw-bolder text-gray-800">' + fmt(data) + '%</div>'
                            + '<div class="text-muted fs-8">Kum. ' + fmt(row.cumulative) + '%</div>';
                    } },
                    { data: 'frequency', className: 'text-end', render: function (data) {
                        return fmt(data, 0) + ' <span class="text-muted fs-8">dok</span>';
                    } },
                    { data: 'days_cover', className: 'text-end', render: function (data) {
                        if (data === null) return '<span class="text-muted">-</span>';
                        var n = parseFloat(data);
                        var cls = n < 7 ? 'text-danger' : (n < 30 ? 'text-warning' : (n > 90 ? 'text-info' : 'text-success'));
                        return '<span class="fw-bolder ' + cls + '">' + fmt(n, 1) + '</span> <span class="text-muted fs-8">hari</span>';
                    } },
                    { data: 'last_out_date', render: function (data, type, row) {
                        return row.last_out_date_label ? escapeHtml(row.last_out_date_label) : '<span class="text-muted">Belum pernah</span>';
                    } }
                ],
                language: {
                    emptyTable: 'Tidak ada data untuk filter ini',
                    zeroRecords: 'Tidak ada item yang cocok',
                    processing: 'Memuat...'
                }
            });

            function reload() {
                updateFilterInfo();
                table.ajax.reload();
            }

            var searchTimer;
            $('#table_search').on('keyup', function () {
                var value = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { table.search(value).draw(); }, 400);
            });

            $('#apply_filter').on('click', reload);
            $('#date_range').on('apply.daterangepicker', reload);
            $('#class_filter, #warehouse_filter').on('change', reload);

            var coverTimer;
            $('#cover_min, #cover_max').on('input', function () {
                clearTimeout(coverTimer);
                coverTimer = setTimeout(reload, 500);
            }).on('keydown', function (e) {
                if (e.key === 'Enter') { clearTimeout(coverTimer); reload(); }
            });

            $('.movement-card').on('click', function () {
                var cls = $(this).data('class');
                $('#class_filter').val($('#class_filter').val() === cls ? '' : cls).trigger('change');
            });

            $('#reset_filter').on('click', function () {
                var picker = $('#date_range').data('daterangepicker');
                picker.setStartDate(moment().subtract(29, 'days'));
                picker.setEndDate(moment());
                setRange(picker.startDate, picker.endDate);
                $('#warehouse_filter').val(defaultWarehouse);
                $('#class_filter').val('');
                $('#cover_min, #cover_max, #table_search').val('');
                $('#warehouse_filter, #class_filter').trigger('change.select2');
                table.search('').order([[0, 'asc']]);
                reload();
            });

            updateFilterInfo();
        });
    </script>
@endpush
