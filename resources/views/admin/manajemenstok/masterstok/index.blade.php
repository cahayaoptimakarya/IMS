@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        #kt-toolbar-filter .select2-container .select2-selection--single {
            height: 2.65rem !important;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            background-color: #eff2f5;
            color: #5e6278;
            border: 1px solid transparent;
            border-radius: 0.475rem;
        }

        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            line-height: 1.2;
        }

        #kt-toolbar-filter .select2-container .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }

        .item-cell .name { color:#7e8299; font-size: 0.82rem; line-height: 1.1; }
        .item-cell .sku { color:#1b84ff; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Master Stok',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Master Stok'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
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
                            placeholder="Cari Stok">
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" id="export_excel" class="btn btn-success me-3">
                        <span class="svg-icon svg-icon-2 me-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path opacity="0.3" d="M5 8H19V19C19 20.1046 18.1046 21 17 21H7C5.89543 21 5 20.1046 5 19V8Z" fill="black"/>
                                <path d="M12 3C12.5523 3 13 3.44772 13 4V12C13 12.5523 12.5523 13 12 13C11.4477 13 11 12.5523 11 12V4C11 3.44772 11.4477 3 12 3Z" fill="black"/>
                                <path d="M8.29289 8.29289C8.68342 7.90237 9.31658 7.90237 9.70711 8.29289L12 10.5858L14.2929 8.29289C14.6834 7.90237 15.3166 7.90237 15.7071 8.29289C16.0976 8.68342 16.0976 9.31658 15.7071 9.70711L12.7071 12.7071C12.3166 13.0976 11.6834 13.0976 11.2929 12.7071L8.29289 9.70711C7.90237 9.31658 7.90237 8.68342 8.29289 8.29289Z" fill="black"/>
                            </svg>
                        </span>
                        Export Excel
                    </button>
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <path
                                    d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z"
                                    fill="black" />
                            </svg>
                        </span>
                        Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true"
                        id="kt-toolbar-filter">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Gudang:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                    id="warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                    <option value="all">Semua Gudang</option>
                                    @foreach ($warehouses ?? [] as $w)
                                        <option value="{{ $w->id }}" {{ isset($defaultWarehouseId) && (int)$defaultWarehouseId === (int)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Kategori Produk:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                    id="category_filter" data-dropdown-parent="#kt-toolbar-filter">
                                    <option value="all">Semua Kategori</option>
                                    @foreach ($categories ?? [] as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-10">
                                <label class="form-label fs-5 fw-bold mb-3">Tipe Item:</label>
                                <select class="form-select form-select-solid fw-bolder" data-kt-select2="true"
                                    id="item_type_filter" data-dropdown-parent="#kt-toolbar-filter">
                                    <option value="all">Semua Tipe</option>
                                    @foreach ($itemTypes ?? [] as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="reset" class="btn btn-light btn-active-light-primary me-2"
                                    data-kt-menu-dismiss="true">Reset</button>
                                <button type="button" class="btn btn-primary" id="apply_filter"
                                    data-kt-menu-dismiss="true">Terapkan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="text-center mb-5">
                    <h3 class="mb-0">Master Stok</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer" id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-220px" rowspan="2">Item</th>
                                    <th class="min-w-120px text-center" rowspan="2">Per Koli</th>
                                    <th colspan="2" class="text-center">Stok Akhir</th>
                                    <th class="min-w-220px text-center" rowspan="2">TurnOver <br> (Nanggewer) <br> (pcs)</th>
                                    <th class="min-w-180px text-center" rowspan="2">Gudang</th>
                                </tr>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="min-w-120px text-center">Qty</th>
                                    <th class="min-w-120px text-center">Koli</th>
                                </tr>

                            </thead>
                            <tbody class="fw-bold text-gray-600"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const $searchInput = $('#search_input');
            const $filterInfo = $('#filter-info');
            const $warehouseFilter = $('#warehouse_filter');
            const $categoryFilter = $('#category_filter');
            const $typeFilter = $('#item_type_filter');
            const DEFAULT_WAREHOUSE_ID = '{{ $defaultWarehouseId ?? '' }}';

            let table = null;

            const formatNumber = (value) => {
                const number = parseFloat(value ?? 0);
                if (isNaN(number)) {
                    return '0';
                }
                return number.toLocaleString('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2,
                });
            };

            const formatItemCell = (name, sku) => {
                const displayName = name || '-';
                const skuOnly = sku || '-';
                return `
                    <div class="d-flex flex-column item-cell">
                        <span class="sku text-primary fw-bold mb-1">SKU: ${skuOnly}</span>
                        <span class="name">${displayName}</span>
                    </div>
                `;
            };

            const resolveWarehouseText = () => {
                const opt = $warehouseFilter.find('option:selected');
                return opt.length ? opt.text() : 'Semua Gudang';
            };

            const resolveCategoryText = () => {
                const opt = $categoryFilter.find('option:selected');
                return opt.length ? opt.text() : 'Semua Kategori';
            };

            const resolveTypeText = () => {
                const opt = $typeFilter.find('option:selected');
                return opt.length ? opt.text() : 'Semua Tipe';
            };

            const metricKeys = ['quantity', 'koli'];

            const loadDataTable = () => {
                const warehouseText = resolveWarehouseText();
                const categoryText = resolveCategoryText();
                const typeText = resolveTypeText();
                $filterInfo.text(`Gudang: ${warehouseText} • Kategori: ${categoryText} • Tipe: ${typeText}`);

                if ($.fn.DataTable.isDataTable('#table-on-page')) {
                    $('#table-on-page').DataTable().destroy();
                }

                const columns = [
                    { data: 'item_name', name: 'items.nama_barang' },
                    { data: 'per_koli', name: 'per_koli', className: 'text-center', render: function(data, type, row){ var val = formatNumber(data); var u = row.uom_symbol || ''; return u ? (val + ' ' + u) : val; } },
                    { data: 'quantity', name: 'quantity', className: 'text-center' },
                    { data: 'koli', name: 'koli', className: 'text-center' },
                    { data: 'avg_daily_turnover', name: 'avg_daily_turnover', className: 'text-center' },
                    { data: 'warehouse', name: 'warehouse' },
                ];

                table = $('#table-on-page').DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ajax: {
                        url: "{{ route('admin.manajemenstok.masterstok.index') }}",
                        type: 'GET',
                        data: function(d) {
                            d.search = d.search || {};
                            d.search.value = $searchInput.val();
                            d.warehouse_id = $warehouseFilter.val();
                            d.category_id = $categoryFilter.val();
                            d.item_type_id = $typeFilter.val();
                        },
                    },
                    order: [
                        [0, 'asc']
                    ],
                    columns,
                    columnDefs: [{
                            targets: 0,
                            render: function(data, type, row) {
                                return formatItemCell(data, row.sku);
                            },
                        },
                        { targets: [1, 3, 4], render: function(data) { return formatNumber(data); } },
                    ],
                });
            };

            const debounce = (callback, wait = 400) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => callback.apply(null, args), wait);
                };
            };

            loadDataTable();

            $('#apply_filter').on('click', function() {
                loadDataTable();
            });

            $('#kt-toolbar-filter [type="reset"]').on('click', function() {
                $warehouseFilter.val(DEFAULT_WAREHOUSE_ID || 'all').trigger('change');
                $categoryFilter.val('all').trigger('change');
                $typeFilter.val('all').trigger('change');
                loadDataTable();
            });

            $searchInput.on('keyup', debounce(function() {
                if (table) {
                    table.ajax.reload();
                }
            }));

            $('#export_excel').on('click', function() {
                const params = new URLSearchParams({
                    warehouse_id: $warehouseFilter.val() || '',
                    category_id: $categoryFilter.val() || '',
                    item_type_id: $typeFilter.val() || '',
                    search: $searchInput.val() || ''
                });
                const url = "{{ route('admin.manajemenstok.masterstok.export-excel') }}" + '?' + params.toString();
                window.location.href = url;
            });
        });
    </script>
@endpush


