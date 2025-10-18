@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Stok Produk',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Stok Gudang', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <!--begin::Info-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-body">
                <div class="d-flex flex-wrap flex-sm-nowrap">
                    <!--begin::Symbol-->
                    <div class="me-7 mb-4">
                        <div class="symbol symbol-100px symbol-lg-160px symbol-fixed position-relative">
                            <div class="symbol-label fs-2x fw-semibold text-primary bg-light-primary">
                                <i class="ki-duotone ki-cube-2 fs-5x">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                            </div>
                        </div>
                    </div>
                    <!--end::Symbol-->
                    <!--begin::Info-->
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-gray-800 fs-2 fw-bolder me-1">{{ $item->nama_barang }}</span>
                                </div>
                                <div class="d-flex flex-wrap fw-semibold fs-6 mb-4 pe-2">
                                    <span class="d-flex align-items-center text-gray-400 me-5 mb-2">
                                        <i class="ki-duotone ki-key fs-4 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        SKU: {{ $item->sku }}
                                    </span>
                                    <span class="d-flex align-items-center text-gray-400 me-5 mb-2">
                                        <i class="ki-duotone ki-warehouse fs-4 me-1">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Gudang: {{ $warehouse->name }}
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex my-4">
                                <a href="{{ route('admin.manajemenstok.warehousestok.index') }}"
                                    class="btn btn-sm btn-light me-3">
                                    <i class="ki-duotone ki-arrow-left fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Kembali
                                </a>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap flex-stack">
                            <div class="d-flex flex-column flex-grow-1 pe-8">
                                <div class="d-flex flex-wrap">
                                    <div
                                        class="border border-gray-300 border-dashed rounded min-w-125px py-3 px-4 me-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="ki-duotone ki-package fs-3 text-primary me-2">
                                                <span class="path1"></span>
                                                <span class="path2"></span>
                                                <span class="path3"></span>
                                                <span class="path4"></span>
                                            </i>
                                            <div class="fs-2 fw-bolder" id="current_stock_value">-</div>
                                        </div>
                                        <div class="fw-semibold fs-6 text-gray-400">Stok Saat Ini</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Info-->
                </div>
            </div>
        </div>
        <!--end::Info-->

        <!--begin::Filters-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-header border-0">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bolder text-dark">Filter Data</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Filter pergerakan stok berdasarkan rentang tanggal
                        dan tipe</span>
                </h3>
                <div class="card-toolbar">
                    <button id="apply_filters_top" class="btn btn-primary">
                        <i class="ki-duotone ki-filter-search fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Terapkan Filter
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Rentang Tanggal:</label>
                        <input type="text" id="date_range_top" class="form-control form-control-solid"
                            placeholder="Pilih rentang tanggal" />
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tipe Pergerakan:</label>
                        <select id="type_filter_top" class="form-select form-select-solid" data-control="select2"
                            data-hide-search="true" data-placeholder="Semua Tipe">
                            <option></option>
                            <option value="all" selected>Semua Tipe</option>
                            <option value="transfer_in">Transfer In</option>
                            <option value="transfer_out">Transfer Out</option>
                            <option value="stock_out">Stock Out</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Filters-->

        <!--begin::Summary-->
        <div class="card mb-5 mb-xl-8">
            <div class="card-header border-0">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bolder text-dark">Ringkasan Periode</span>
                    <span class="text-muted mt-1 fw-semibold fs-7">Ikhtisar stok dalam rentang terpilih</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <div id="summary_cards" class="row g-6">
                    <!-- Summary cards injected by JS -->
                </div>
            </div>
        </div>
        <!--end::Summary-->


        <div class="row g-5 g-xl-8">
            <!--begin::Daily Snapshot-->
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Snapshot Harian</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Perubahan stok per hari</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="daily_table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>Tanggal</th>
                                        <th class="text-end">Perubahan</th>
                                        <th class="text-end">Stok</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    <!-- Daily data injected by JS -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center" id="daily_pagination">
                            <button class="btn btn-sm btn-light" id="prev_daily">Sebelumnya</button>
                            <span id="daily_page_info" class="text-muted fs-7"></span>
                            <button class="btn btn-sm btn-light" id="next_daily">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Daily Snapshot-->

            <!--begin::Stock Movements-->
            <div class="col-xl-8">
                <div class="card card-flush h-xl-100">
                    <div class="card-header pt-7">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Detail Pergerakan Stok</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Urut dari terbaru</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5" id="movement_table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th class="ps-4 w-25px">No</th>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Deskripsi</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Koli</th>
                                        <th class="text-end">Stok Akhir</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-600">
                                    <!-- Movement data injected by JS -->
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 d-flex justify-content-between align-items-center" id="pagination">
                            <button class="btn btn-sm btn-light" id="prev_page">Sebelumnya</button>
                            <span id="page_info" class="text-muted fs-7"></span>
                            <button class="btn btn-sm btn-light" id="next_page">Berikutnya</button>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Stock Movements-->
        </div>
    </div>
@endsection


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const warehouseId = {{ $warehouse->id }};
            const itemId = {{ $item->id }};
            let currentPage = 1;
            const perPage = 15;
            let currentDailyPage = 1;
            const dailyPerPage = 10;

            const drTop = document.getElementById('date_range_top');
            const typeFilter = document.getElementById('type_filter_top');
            const contentElement = document.getElementById('kt_content');

            const blockUI = new KTBlockUI(contentElement, {
                message: '<div class="blockui-message"><span class="spinner-border text-primary"></span> Memuat data...</div>',
            });

            flatpickr(drTop, {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: [new Date(new Date().setDate(new Date().getDate() - 14)), new Date()]
            });

            function parseRange() {
                const val = (drTop.value || '');
                const parts = val.split(' to ');
                return {
                    from: parts[0] || '',
                    to: parts[1] || ''
                };
            }

            function fmt(n) {
                return (n || 0).toLocaleString('id-ID');
            }

            function fmt2(n) {
                return (n || 0).toLocaleString('id-ID', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function summaryCard(icon, color, label, value) {
                return `
                    <div class="col">
                        <div class="card card-body bg-light-${color} hoverable">
                            <div class="d-flex flex-stack">
                                <div class="d-flex flex-column">
                                    <div class="text-${color} fw-semibold fs-7">${label}</div>
                                    <div class="fw-bolder fs-2 text-gray-800">${value}</div>
                                </div>
                                <span class="symbol symbol-45px">
                                    <span class="symbol-label bg-${color} bg-opacity-25">
                                        <i class="${icon} fs-1 text-${color}"></i>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>`;
            }

            function renderSummary(s) {
                const html = [
                    summaryCard('ki-duotone ki-arrow-down-left', 'success', 'Stok Masuk', fmt(s.in)),
                    summaryCard('ki-duotone ki-arrow-up-right', 'danger', 'Stok Keluar', fmt(s.out)),
                    summaryCard('ki-duotone ki-package', 'primary', 'Stok Saat Ini', fmt(s.last_stock))
                ].join('');
                document.getElementById('summary_cards').innerHTML = html +
                    `<div class="col-12 text-muted mt-2 fs-7">Rentang: ${s.date_from} s/d ${s.date_to}</div>`;
                const cs = document.getElementById('current_stock_value');
                if (cs) cs.textContent = fmt(s.last_stock);
            }

            function renderDaily(p) {
                const tb = document.querySelector('#daily_table tbody');
                const rows = p.data || [];
                if (!rows.length) {
                    tb.innerHTML =
                        `<tr><td colspan="3" class="text-muted text-center py-10">Tidak ada data snapshot harian</td></tr>`;
                } else {
                    tb.innerHTML = rows.map(r => {
                        const cls = (parseFloat(r.delta) >= 0) ? 'text-success' : 'text-danger';
                        const d = new Date(r.d);
                        const ds = d.toLocaleDateString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                        return `<tr>
                                    <td>${ds}</td>
                                    <td class="text-end fw-bolder ${cls}">${fmt(r.delta)}</td>
                                    <td class="text-end">${fmt(r.stock)}</td>
                                </tr>`;
                    }).join('');
                }
                document.getElementById('daily_page_info').textContent = `Hal ${p.current_page} dari ${p.last_page}`;
                document.getElementById('prev_daily').disabled = (p.current_page <= 1);
                document.getElementById('next_daily').disabled = (p.current_page >= p.last_page);
            }

            function typeBadge(type) {
                const map = {
                    transfer_in: 'success',
                    transfer_out: 'warning',
                    stock_out: 'danger',
                    adjustment: 'info'
                };
                const label = (type || '').replace('_', ' ');
                const cls = map[type] || 'primary';
                return `<span class="badge badge-light-${cls} text-capitalize">${label}</span>`;
            }

            function renderMovements(p) {
                const tb = document.querySelector('#movement_table tbody');
                if (!p || !p.data || !p.data.length) {
                    tb.innerHTML =
                        `<tr><td colspan="7" class="text-muted text-center py-10">Belum ada pergerakan stok pada periode ini</td></tr>`;
                    document.getElementById('page_info').textContent = 'Hal 0 dari 0';
                    document.getElementById('prev_page').disabled = true;
                    document.getElementById('next_page').disabled = true;
                    return;
                }

                tb.innerHTML = p.data.map((m, idx) => {
                    const d = new Date(m.date);
                    const ds = d.toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                    const cls = (parseFloat(m.quantity) >= 0) ? 'text-success' : 'text-danger';
                    return `<tr>
                                <td class="ps-4">${(p.from + idx)}</td>
                                <td>${ds}</td>
                                <td>${typeBadge(m.type)}</td>
                                <td>${(m.description || '-')}</td>
                                <td class="text-end fw-bolder ${cls}">${fmt(m.quantity)}</td>
                                <td class="text-end">${fmt2(m.koli)}</td>
                                <td class="text-end">${fmt(m.stock_after)}</td>
                            </tr>`;
                }).join('');

                document.getElementById('page_info').textContent = `Hal ${p.current_page} dari ${p.last_page}`;
                document.getElementById('prev_page').disabled = (p.current_page <= 1);
                document.getElementById('next_page').disabled = (p.current_page >= p.last_page);
            }

            async function load(page = 1) {
                currentPage = page;
                const range = parseRange();
                const type = typeFilter.value || 'all'; // Default to 'all' if no type is selected
                const params = new URLSearchParams({
                    date_from: range.from,
                    date_to: range.to,
                    type,
                    page: currentPage,
                    per_page: perPage,
                    daily_page: currentDailyPage,
                    daily_per_page: dailyPerPage
                });
                const url =
                    `{{ route('admin.manajemenstok.warehousestok.data', [$warehouse->id, $item->id]) }}?${params.toString()}`;

                blockUI.block();

                try {
                    const res = await fetch(url);
                    const json = await res.json();
                    renderSummary(json.summary);
                    renderDaily(json.daily || {
                        data: [],
                        current_page: 1,
                        last_page: 1
                    });
                    renderMovements(json.movements);
                } catch (error) {
                    console.error("Failed to load data:", error);
                    Swal.fire({
                        text: "Gagal memuat data. Silakan coba lagi.",
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Ok, got it!",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                } finally {
                    blockUI.release();
                }
            }

            document.getElementById('apply_filters_top').addEventListener('click', () => {
                currentDailyPage = 1;
                load(1);
            });

            document.getElementById('prev_page').addEventListener('click', () => load(currentPage - 1));
            document.getElementById('next_page').addEventListener('click', () => load(currentPage + 1));
            document.getElementById('prev_daily').addEventListener('click', () => {
                currentDailyPage = Math.max(1, currentDailyPage - 1);
                load(currentPage);
            });
            document.getElementById('next_daily').addEventListener('click', () => {
                currentDailyPage = currentDailyPage + 1;
                load(currentPage);
            });

            // Initial load
            load(1);
        });
    </script>
@endpush