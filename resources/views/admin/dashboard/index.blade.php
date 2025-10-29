@extends('layouts.app')

@push('styles')
    @include('admin.dashboard._styles')
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <!--begin::Row-->
        <div class="row g-5 g-xxl-8">
            <div class="col-12">
                <div class="card bg-light-primary border-0 card-animate">
                    <div class="card-body">
                        <h3 class="card-title">Selamat Datang, {{ $user->name }}!</h3>
                        <p class="text-muted">Berikut adalah ringkasan aktivitas Anda.</p>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row-->

        <!--begin::Row: Warehouse Filter Card-->
        <div class="row g-5 g-xxl-8 mt-3">
            <div class="col-12">
                <div class="card card-animate border-0 bg-light">
                    <div class="card-body">
                        <form id="warehouseFilterForm" class="d-flex align-items-center gap-3 flex-wrap" onsubmit="return false;">
                            <label class="fw-bold me-2">Filter Gudang:</label>
                            <select id="warehouseFilterSelect" name="warehouse_id" class="form-select" style="max-width: 320px;">
                                <option value="" {{ empty($currentWarehouseId) ? 'selected' : '' }}>Semua Gudang</option>
                                @foreach(($warehouses ?? []) as $wh)
                                    <option value="{{ $wh->id }}" {{ (int)($currentWarehouseId ?? 0) === (int)$wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                                @endforeach
                            </select>
                            <div id="warehouseFilterLoading" class="spinner-border spinner-border-sm text-primary d-none" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <button type="button" id="warehouseFilterReset" class="btn btn-light">Reset</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row: Warehouse Filter Card-->

        <!--begin::Row-->
        <div class="row g-5 g-xxl-8 mt-5" id="dash-summary-row">
            @include('admin.dashboard._summary_card', [
                'delay' => '0.1s', 'icon' => 'users', 'iconBg' => 'primary', 'value' => $totalUsers ?? '-', 'label' => 'Total Pengguna', 'valueId' => 'dash-total-users'
                , 'wrapClass' => 'sys-only ' . (isset($totalUsers) ? '' : 'd-none')
            ])
            @include('admin.dashboard._summary_card', [
                'delay' => '0.2s', 'icon' => 'warehouse', 'iconBg' => 'warning', 'value' => $totalWarehouses ?? '-', 'label' => 'Total Gudang', 'valueId' => 'dash-total-warehouses'
                , 'wrapClass' => 'sys-only ' . (isset($totalUsers) ? '' : 'd-none')
            ])
            @include('admin.dashboard._summary_card', [
                'delay' => '0.1s', 'icon' => 'exchange-alt', 'iconBg' => 'warning', 'value' => $pendingTransfers ?? '-', 'label' => 'Permintaan Transfer Tertunda', 'valueId' => 'dash-pending-transfers'
                , 'wrapClass' => 'wh-only ' . (isset($totalUsers) ? 'd-none' : '')
            ])
            @include('admin.dashboard._summary_card', [
                'delay' => '0.2s', 'icon' => 'exclamation-triangle', 'iconBg' => 'info', 'value' => $lowStockItems ?? '-', 'label' => 'Stok Segera Habis', 'valueId' => 'dash-low-stock-items-card'
                , 'wrapClass' => 'wh-only ' . (isset($totalUsers) ? 'd-none' : '')
            ])

            @include('admin.dashboard._summary_card', [
                'delay' => '0.3s', 'icon' => 'arrow-down', 'iconBg' => 'success',
                'value' => number_format($todayStockIn, 0, ',', '.'),
                'label' => 'Stok Masuk Hari Ini',
                'meta' => 'Koli: ' . number_format($todayStockInKoli ?? 0, 0, ',', '.'),
                'valueId' => 'dash-today-stock-in',
                'metaId' => 'dash-today-stock-in-koli'
            ])
            @include('admin.dashboard._summary_card', [
                'delay' => '0.4s', 'icon' => 'arrow-up', 'iconBg' => 'danger',
                'value' => number_format($todayStockOut, 0, ',', '.'),
                'label' => 'Stok Keluar Hari Ini',
                'meta' => 'Koli: ' . number_format($todayStockOutKoli ?? 0, 0, ',', '.'),
                'valueId' => 'dash-today-stock-out',
                'metaId' => 'dash-today-stock-out-koli'
            ])
        </div>
        <!--end::Row-->

        <!--begin::Row: Stock Reminders-->
        <div class="row g-5 g-xxl-8 mt-1">
            @include('admin.dashboard._summary_card', [
                'delay' => '0.1s', 'icon' => 'ban', 'iconBg' => 'danger', 'value' => $zeroStockItems, 'label' => 'Stok Habis', 'bucket' => 'out_of_stock'
                , 'valueId' => 'dash-zero-stock']
            )
            @include('admin.dashboard._summary_card', [
                'delay' => '0.2s', 'icon' => 'level-down-alt', 'iconBg' => 'warning', 'value' => $lowStockItems, 'label' => 'Stok Menipis', 'bucket' => 'low_stock'
                , 'valueId' => 'dash-low-stock']
            )
            @include('admin.dashboard._summary_card', [
                'delay' => '0.3s', 'icon' => 'check', 'iconBg' => 'success', 'value' => $optimalStockItems, 'label' => 'Stok Aman', 'bucket' => 'safe'
                , 'valueId' => 'dash-optimal-stock']
            )
            @include('admin.dashboard._summary_card', [
                'delay' => '0.4s', 'icon' => 'boxes', 'iconBg' => 'primary', 'value' => $overStockItems, 'label' => 'Stok Berlebih', 'bucket' => 'over_stock'
                , 'valueId' => 'dash-over-stock']
            )
        </div>
        <!--end::Row: Stock Reminders-->

        <!--begin::Row: Stock Summary & Status-->
        <div class="row g-5 g-xxl-8 mt-1">
            @include('admin.dashboard._summary_card', ['delay' => '0.1s', 'icon' => 'barcode', 'iconBg' => 'primary', 'value' => number_format($totalSkus, 0, ',', '.'), 'label' => 'Total SKU Aktif', 'valueId' => 'dash-total-skus'])
            
            <div class="col-xl-9">
                <div class="card card-xl-stretch mb-xl-8 summary-card card-animate" style="animation-delay: 0.3s;">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-4 mb-md-0">
                                <div class="symbol symbol-50px me-5 status-card-icon">
                                    <div class="symbol-label">
                                        <i class="fas fa-truck-loading fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Penerimaan</div>
                                    <div class="text-muted fw-bold">Dokumen Stock In</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-warning" id="dash-stockin-requested">{{ $stockInRequested }}</div>
                                    <div class="text-muted fs-8 mt-1">Requested</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-primary" id="dash-stockin-shipped">{{ $stockInShipped }}</div>
                                    <div class="text-muted fs-8 mt-1">Shipped</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="dash-stockin-completed">{{ $stockInCompleted }}</div>
                                    <div class="text-muted fs-8 mt-1">Completed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row: Stock Summary & Status-->

        <!--begin::Row: Lists-->
        <div class="row g-5 g-xxl-8">
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8 card-animate" style="animation-delay: 0.5s;">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Top 5 Stok Menipis</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Urut dari kuantitas terendah</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table align-middle gs-0 gy-3">
                                <thead>
                                    <tr>
                                        <th>Item</th><th>SKU</th><th class="text-end">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="dash-toplow-tbody">
                                @forelse($topLowStocks as $inv)
                                    <tr>
                                        <td class="fw-bolder text-gray-800">{{ $inv->item->nama_barang ?? '-' }}</td>
                                        <td class="text-muted">{{ $inv->item->sku ?? '-' }}</td>
                                        <td class="text-end fw-bolder">{{ number_format($inv->quantity ?? 0, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">Tidak ada data</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8 card-animate" style="animation-delay: 0.6s;">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Pergerakan Stok Terbaru</span>
                            <span class="text-muted mt-1 fw-bold fs-7">5 transaksi terakhir</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2" id="dash-recent-movements">
                        @forelse($recentMovements as $mov)
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-35px me-4">
                                    <div class="symbol-label bg-light-{{ $mov->quantity >= 0 ? 'success' : 'danger' }}">
                                        <i class="fas fa-{{ $mov->quantity >= 0 ? 'arrow-down' : 'arrow-up' }} text-{{ $mov->quantity >= 0 ? 'success' : 'danger' }}"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-gray-800 fw-bolder fs-6 mb-0">{{ $mov->item->nama_barang ?? 'Item' }} ({{ $mov->type }})</p>
                                    @php
                                        $tz = config('app.timezone');
                                        $dateObj = \Carbon\Carbon::parse($mov->date)->timezone($tz);
                                        $nowDay = now()->timezone($tz)->startOfDay();
                                        $daysDiff = $dateObj->copy()->startOfDay()->diffInDays($nowDay);
                                        if ($daysDiff === 0) {
                                            $timeDisplay = 'Hari ini';
                                        } elseif ($daysDiff === 1) {
                                            $timeDisplay = 'Kemarin';
                                        } elseif ($daysDiff === 2) {
                                            $timeDisplay = '2 hari yang lalu';
                                        } else {
                                            $timeDisplay = $dateObj->format('d M Y');
                                        }
                                    @endphp
                                    <span class="text-muted fw-bold">{{ number_format($mov->quantity, 0, ',', '.') }} • {{ $timeDisplay }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="text-muted">Tidak ada pergerakan terakhir.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        <!--end::Row: Lists-->

        <!--begin::Row-->
        <div class="row g-5 g-xxl-8">
            <!--begin::Col-->
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8 card-animate" style="animation-delay: 0.5s;">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Aktivitas Terbaru</span>
                            <span class="text-muted mt-1 fw-bold fs-7">5 aktivitas terakhir</span>
                        </h3>
                    </div>
                    <div class="card-body pt-2" id="dash-recent-activities">
                        @foreach ($recentActivities as $activity)
                            <div class="d-flex align-items-center mb-5">
                                <div class="symbol symbol-35px me-4">
                                    <div class="symbol-label bg-light-primary">
                                        <i class="fas fa-user text-primary"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="text-gray-800 fw-bolder fs-6 mb-0">{{ $activity->description }}</p>
                                    <span class="text-muted fw-bold">
                                        @if (isset($activity->user))
                                            {{ $activity->user->name }} -
                                        @endif
                                        {{ $activity->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <!--end::Col-->
            <!--begin::Col-->
            <div class="col-xl-6">
                <div class="card card-xl-stretch mb-xl-8 card-animate" style="animation-delay: 0.6s;">
                    <div class="card-header border-0">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bolder text-dark">Grafik Stok 7 Hari Terakhir</span>
                            <span class="text-muted mt-1 fw-bold fs-7">Pergerakan stok masuk dan keluar</span>
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
            </div>
            <!--end::Col-->
        </div>
        <!--end::Row-->
    </div>
@endsection

@push('scripts')
    @include('admin.dashboard._scripts')
    
    <!-- Modal Detail Stok -->
    <div class="modal fade" id="stockBucketsModal" tabindex="-1" aria-labelledby="stockBucketsLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockBucketsLabel">Detail Stok</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-4" id="stockTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-out" data-bs-toggle="tab" data-bs-target="#pane-out" type="button" role="tab" aria-controls="pane-out" aria-selected="true" data-bucket="out_of_stock">
                                Stok Habis <span class="badge bg-light text-dark ms-2" id="badge-out">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-low" data-bs-toggle="tab" data-bs-target="#pane-low" type="button" role="tab" aria-controls="pane-low" aria-selected="false" data-bucket="low_stock">
                                Stok Menipis <span class="badge bg-light text-dark ms-2" id="badge-low">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-safe" data-bs-toggle="tab" data-bs-target="#pane-safe" type="button" role="tab" aria-controls="pane-safe" aria-selected="false" data-bucket="safe">
                                Stok Aman <span class="badge bg-light text-dark ms-2" id="badge-safe">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-over" data-bs-toggle="tab" data-bs-target="#pane-over" type="button" role="tab" aria-controls="pane-over" aria-selected="false" data-bucket="over_stock">
                                Stok Berlebih <span class="badge bg-light text-dark ms-2" id="badge-over">0</span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="stockTabsContent">
                        <div class="tab-pane fade show active" id="pane-out" role="tabpanel" aria-labelledby="tab-out">
                            <div id="table-out-wrapper">@include('admin.dashboard._stock_bucket_table', ['bucket' => 'out'])</div>
                        </div>
                        <div class="tab-pane fade" id="pane-low" role="tabpanel" aria-labelledby="tab-low">
                            <div id="table-low-wrapper">@include('admin.dashboard._stock_bucket_table', ['bucket' => 'low'])</div>
                        </div>
                        <div class="tab-pane fade" id="pane-safe" role="tabpanel" aria-labelledby="tab-safe">
                            <div id="table-safe-wrapper">@include('admin.dashboard._stock_bucket_table', ['bucket' => 'safe'])</div>
                        </div>
                        <div class="tab-pane fade" id="pane-over" role="tabpanel" aria-labelledby="tab-over">
                            <div id="table-over-wrapper">@include('admin.dashboard._stock_bucket_table', ['bucket' => 'over'])</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
@endpush
