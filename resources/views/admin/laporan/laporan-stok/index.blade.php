@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Laporan Stok',
        'breadcrumbs' => ['Admin', 'Laporan', 'Laporan Stok'],
    ])
@endpush

@push('styles')
    <style>
        .item-cell .sku { font-size: 0.75rem; color: #a1a5b7; }
    </style>
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <!--begin::Top Filters-->
        <div class="card mb-7">
            <div class="card-body">
                <div class="row g-6 g-xl-9 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold">Item</label>
                        <select id="item_filter" class="form-select form-select-solid" data-kt-select2="true" data-placeholder="Pilih item (opsional)">
                            <option value="">Semua Item</option>
                            @foreach ($items as $it)
                                <option value="{{ $it->id }}">{{ $it->sku }} - {{ $it->nama_barang }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if(!$hideWarehouseFilter)
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold">Gudang</label>
                        <select id="warehouse_filter" class="form-select form-select-solid" data-kt-select2="true" data-placeholder="Pilih Gudang (opsional)">
                            <option value=""></option>
                            <option value="semua">Semua Gudang</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    
                    <div class="col-12 col-md-3 text-md-end">
                        <button type="button" class="btn btn-light me-3" id="reset_filter">Reset</button>
                        <button type="button" class="btn btn-primary" id="apply_filter_top">Terapkan</button>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Top Filters-->
        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <div class="col-xl-4">
                <div class="card bg-body-white hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-4.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-primary ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path d="M21.5 6.5C21.5 5.9 21.1 5.5 20.5 5.5H2.5C1.9 5.5 1.5 5.9 1.5 6.5V17.5C1.5 18.1 1.9 18.5 2.5 18.5H20.5C21.1 18.5 21.5 18.1 21.5 17.5V6.5Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-5" id="total_unique_items">-</div>
                        <div class="fw-bold text-gray-400">Total Item Unik</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card bg-primary hoverable card-xl-stretch mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-2.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 5.9 2.9 5 4 5H20C21.1 5 22 5.9 22 7V13C22 14.1 21.1 15 20 15Z" fill="black"/>
								<path d="M20 7H4C3.4 7 3 6.6 3 6V6C3 5.4 3.4 5 4 5H20C20.6 5 21 5.4 21 6V6C21 6.6 20.6 7 20 7Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5" id="total_stock_quantity">-</div>
                        <div class="fw-bold text-white">Total Stok Keseluruhan</div>
                        <div class="text-white fw-bold fs-6 mt-3">Total Koli: <span id="total_stock_koli">-</span></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <a href="{{ route('admin.laporan.laporanstok.menipis') }}" class="card bg-danger hoverable card-xl-stretch mb-5 mb-xl-8 bgi-no-repeat" style="background-position: right top; background-size: 30% auto; background-image: url({{ asset('metronic/assets/media/svg/shapes/abstract-1.svg') }})">
                    <div class="card-body">
                        <span class="svg-icon svg-icon-3x svg-icon-white ms-n1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
								<path opacity="0.3" d="M12 22C17.5 22 22 17.5 22 12C22 6.5 17.5 2 12 2C6.5 2 2 6.5 2 12C2 17.5 6.5 22 12 22Z" fill="black"/>
								<path d="M12 15C13.7 15 15 13.7 15 12C15 10.3 13.7 9 12 9C10.3 9 9 10.3 9 12C9 13.7 10.3 15 12 15Z" fill="black"/>
							</svg>
                        </span>
                        <div class="text-white fw-bolder fs-2 mb-2 mt-5" id="low_stock_items">-</div>
                        <div class="fw-bold text-white">Item Stok Menipis</div>
                    </div>
                </a>
            </div>
        </div>
        <!--end::Row-->

        <!--begin::Row-->
        <div class="row g-5 g-xl-8">
            <div class="col-xl-7">
                <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Tren Pergerakan Stok</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Stok Masuk vs Stok Keluar</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div id="stock_movement_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                 <div class="card card-xl-stretch mb-5 mb-xl-8">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Top 5 Item Stok Terbanyak</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Berdasarkan kuantitas</span>
                        </h3>
                    </div>
                    <div class="card-body pt-5">
                        <div id="top_items_chart" style="height: 350px;"></div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row-->

        <div class="card">
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
                            placeholder="Cari Stok by SKU/Nama">
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="inventory_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0 text-center">
                                @if(!$hideWarehouseFilter)
                                <th rowspan="2">Gudang</th>
                                @endif
                                <th rowspan="2">Item</th>
                                <th rowspan="2">Kategori</th>
                                <th rowspan="2">UOM</th>
                                <th colspan="2">Stok Saat Ini</th>
                                <th colspan="2">Saldo Awal</th>
                                <th colspan="2">Stok Masuk</th>
                                <th colspan="2">Stok Keluar</th>
                                <th rowspan="2">Terakhir Gerak</th>
                            </tr>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0 text-center">
                                <th>Qty</th>
                                <th>Koli</th>
                                <th>Qty</th>
                                <th>Koli</th>
                                <th>Qty</th>
                                <th>Koli</th>
                                <th>Qty</th>
                                <th>Koli</th>
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
    <script src="{{ asset('metronic/assets/plugins/custom/apexcharts/apexcharts.bundle.js') }}"></script>
    <script>
        $(document).ready(function() {
            let stockMovementChart, topItemsChart;
            let table;

            // Initialize Select2 with clear button (X)
            const select2Options = { allowClear: true, width: '100%' };
            $('#item_filter').select2(Object.assign({}, select2Options, { placeholder: $('#item_filter').data('placeholder') || 'Pilih item (opsional)' }));
            @if(!$hideWarehouseFilter)
            $('#warehouse_filter').select2(Object.assign({}, select2Options, { placeholder: $('#warehouse_filter').data('placeholder') || 'Pilih Gudang (opsional)' }));
            @endif

            function initCharts(trendData, topItemsData) {
                if (stockMovementChart) stockMovementChart.destroy();
                var movementElement = document.getElementById('stock_movement_chart');
                var movementOptions = {
                    series: [{
                        name: 'Stok Masuk',
                        data: trendData.stock_in
                    }, {
                        name: 'Stok Keluar',
                        data: trendData.stock_out
                    }],
                    chart: { type: 'area', height: 350, toolbar: { show: false } },
                    xaxis: {
                        categories: trendData.dates,
                        axisBorder: { show: false },
                        axisTicks: { show: false },
                        labels: { style: { colors: '#A1A5B7', fontSize: '12px' } }
                    },
                    yaxis: { labels: { style: { colors: '#A1A5B7', fontSize: '12px' } } },
                    fill: { opacity: 1, type: 'solid' },
                    dataLabels: { enabled: false },
                    stroke: { curve: 'smooth', show: true, width: 3 },
                    colors: ['#50CD89', '#F1416C']
                };
                stockMovementChart = new ApexCharts(movementElement, movementOptions);
                stockMovementChart.render();

                if (topItemsChart) topItemsChart.destroy();
                var topItemsElement = document.getElementById('top_items_chart');
                var topItemsOptions = {
                    series: [{ name: 'Kuantitas', data: topItemsData.quantities }],
                    chart: { type: 'bar', height: 350, toolbar: { show: false } },
                    plotOptions: { bar: { borderRadius: 4, horizontal: false, } },
                    xaxis: {
                        categories: topItemsData.names,
                        labels: { style: { colors: '#A1A5B7', fontSize: '12px' } }
                    },
                    yaxis: { labels: { style: { colors: '#A1A5B7', fontSize: '12px' } } },
                    dataLabels: { enabled: false },
                    colors: ['#009EF7']
                };
                topItemsChart = new ApexCharts(topItemsElement, topItemsOptions);
                topItemsChart.render();
            }

            function loadSummaryData() {
                $.ajax({
                    url: "{{ route('admin.laporan.laporanstok.index') }}",
                    type: "GET",
                    data: {
                        warehouse_filter: $('#warehouse_filter').val(),
                        item_filter: $('#item_filter').val(),
                    },
                    success: function(response) {
                        $('#total_unique_items').text(response.totalUniqueItems);
                        $('#total_stock_quantity').text(response.totalStockQuantity);
                        $('#total_stock_koli').text(response.totalStockKoli);
                        $('#low_stock_items').text(response.lowStockItems);
                        initCharts(response.trendData, response.topItemsData);
                    },
                    error: function() {
                        toastr.error('Gagal memuat data ringkasan.');
                    }
                });
            }

            function initializeDataTable() {
                table = $('#inventory_table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: "{{ route('admin.laporan.laporanstok.index') }}",
                        type: "GET",
                        data: function(d) {
                            d.warehouse_filter = $('#warehouse_filter').val();
                            d.item_filter = $('#item_filter').val();
                            
                            d.search = { value: $('#search_input').val() };
                        }
                    },
                    columns: (function(){
                        const cols = [];
                        const hasWh = @json(!$hideWarehouseFilter);
                        if (hasWh) cols.push({ data: 'warehouse.name', name: 'warehouse.name' });
                        cols.push({ data: 'item.nama_barang', name: 'item.nama_barang' });
                        cols.push({ data: 'item.item_category.name', name: 'item.item_category.name' });
                        cols.push({ data: 'item.uom.name', name: 'item.uom.name' });
                        cols.push({ data: 'quantity', name: 'quantity' });
                        cols.push({ data: 'koli', name: 'koli' });
                        cols.push({ data: 'opening_qty', name: 'opening_qty' });
                        cols.push({ data: 'opening_koli', name: 'opening_koli' });
                        cols.push({ data: 'moved_in_qty', name: 'moved_in_qty' });
                        cols.push({ data: 'moved_in_koli', name: 'moved_in_koli' });
                        cols.push({ data: 'moved_out_qty', name: 'moved_out_qty' });
                        cols.push({ data: 'moved_out_koli', name: 'moved_out_koli' });
                        cols.push({ data: 'last_movement_date', name: 'last_movement_date' });
                        return cols;
                    })(),
                    columnDefs: (function(){
                        const defs = [];
                        const hasWh = @json(!$hideWarehouseFilter);
                        const itemIdx = hasWh ? 1 : 0;
                        const metricsStart = itemIdx + 3; // after category+uom
                        const metricIndices = [];
                        for(let i=0;i<8;i++){ metricIndices.push(metricsStart + i); }

                        // Render Item as SKU + Name stacked
                        defs.push({
                            targets: itemIdx,
                            render: function(data, type, row){
                                const sku = row.item && row.item.sku ? row.item.sku : '-';
                                const name = data || (row.item?.nama_barang) || '-';
                                return `<div class="d-flex flex-column item-cell"><span class="text-primary fw-bold mb-1">${sku}</span><span class="sku">${name}</span></div>`;
                            }
                        });

                        // Numeric formatting for qty/koli metrics including current qty/koli
                        defs.push({
                            targets: metricIndices,
                            className: 'text-center',
                            render: function(data){
                                const num = parseFloat(data || 0);
                                return isNaN(num) ? '0' : num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
                            }
                        });

                        // Last movement date formatting
                        defs.push({
                            targets: metricsStart + 8,
                            render: function(data){
                                return data ? new Date(data).toLocaleDateString('id-ID') : '-';
                            }
                        });

                        return defs;
                    })()
                });
            }

            function applyAll() {
                table.ajax.reload();
                loadSummaryData();
            }

            $('#apply_filter_top').on('click', applyAll);

            $('#reset_filter').on('click', function(){
                $('#item_filter').val('').trigger('change');
                @if(!$hideWarehouseFilter)
                $('#warehouse_filter').val('semua').trigger('change');
                @endif
                
                applyAll();
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
            }, 500));

            initializeDataTable();
            loadSummaryData();
        });
    </script>
@endpush
