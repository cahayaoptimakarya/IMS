@extends('layouts.app')

@push('styles')
    <style>
        .adjust-page { max-width: 1180px; margin: 0 auto; }
        .doc-head { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; padding: 20px; }
        .doc-title-line { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .doc-code { font-size: 1.35rem; font-weight: 800; color: #111827; }
        .doc-route { color: #4b5563; margin-top: 4px; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 12px; margin-top: 18px; }
        .summary-box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; background: #f9fafb; }
        .summary-label { color: #6b7280; font-size: .82rem; font-weight: 600; }
        .summary-value { color: #111827; font-size: 1.35rem; font-weight: 800; margin-top: 4px; }
        .flow-note { border-left: 4px solid #3b82f6; background: #eff6ff; color: #1e3a8a; padding: 12px 14px; border-radius: 6px; margin: 18px 0; }
        .item-adjust-card { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; margin-bottom: 14px; }
        .item-adjust-header { padding: 16px 18px; border-bottom: 1px solid #eef2f7; display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; flex-wrap: wrap; }
        .sku-badge { display: inline-flex; align-items: center; padding: 4px 9px; border-radius: 6px; background: #eef2ff; color: #3730a3; font-weight: 700; font-size: .78rem; }
        .item-name { font-weight: 800; color: #111827; margin-top: 6px; }
        .item-sub { color: #6b7280; font-size: .86rem; margin-top: 2px; }
        .quick-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .adjust-body { padding: 16px 18px; display: grid; grid-template-columns: 1fr 1.1fr 1fr; gap: 16px; align-items: stretch; }
        .adjust-panel { border: 1px solid #eef2f7; border-radius: 8px; padding: 14px; background: #fcfcfd; }
        .panel-title { color: #6b7280; font-size: .78rem; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; }
        .number-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 8px; }
        .number-row:last-child { margin-bottom: 0; }
        .number-label { color: #4b5563; }
        .number-value { font-weight: 800; color: #111827; text-align: right; }
        .input-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .input-pair label { color: #4b5563; font-weight: 700; margin-bottom: 6px; }
        .input-pair input { font-size: 1.05rem; font-weight: 800; text-align: right; }
        .result-value { color: #2563eb; font-weight: 900; }
        .status-preview { display: inline-flex; padding: 5px 10px; border-radius: 6px; background: #ecfdf5; color: #047857; font-weight: 800; font-size: .82rem; }
        .sticky-actions { position: sticky; bottom: 0; z-index: 3; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; box-shadow: 0 -8px 24px rgba(15, 23, 42, .08); }
        @media (max-width: 992px) {
            .summary-grid { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
            .adjust-body { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .summary-grid { grid-template-columns: 1fr; }
            .input-pair { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Penyesuaian Sisa Request Transfer',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Request Transfer', 'Penyesuaian Sisa'],
    ])
@endpush

@section('content')
    @php
        $totalShippedQty = $transferRequest->items->sum(fn ($item) => (float) ($shipmentTotals[$item->id]['quantity'] ?? 0));
        $totalRemainingQty = (float) $transferRequest->items->sum('ship_remaining_quantity');
        $totalRemainingKoli = (float) $transferRequest->items->sum('ship_remaining_koli');
    @endphp

    <div class="content flex-row-fluid" id="kt_content">
        <div class="adjust-page">
            <div class="doc-head mb-5">
                <div class="doc-title-line">
                    <div>
                        <div class="text-muted fw-bold">Request Transfer</div>
                        <div class="doc-code">{{ $transferRequest->code }}</div>
                        <div class="doc-route">
                            {{ optional($transferRequest->fromWarehouse)->name ?? '-' }}
                            <span class="mx-2">→</span>
                            {{ optional($transferRequest->toWarehouse)->name ?? '-' }}
                        </div>
                    </div>
                    <div class="text-md-end">
                        <div class="text-muted fw-bold mb-1">Status Saat Ini</div>
                        <span class="badge badge-light-primary fs-7">{{ ucwords(str_replace('_', ' ', $transferRequest->status)) }}</span>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-box">
                        <div class="summary-label">Item Masih Sisa</div>
                        <div class="summary-value">{{ number_format($transferRequest->items->count(), 0, ',', '.') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Sudah Dikirim Dari Item Ini</div>
                        <div class="summary-value">{{ number_format($totalShippedQty, 0, ',', '.') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Sisa Qty</div>
                        <div class="summary-value" id="total_remaining_qty_preview">{{ number_format($totalRemainingQty, 0, ',', '.') }}</div>
                    </div>
                    <div class="summary-box">
                        <div class="summary-label">Sisa Koli</div>
                        <div class="summary-value" id="total_remaining_koli_preview">{{ number_format($totalRemainingKoli, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.stok-masuk.request-transfer.update-remaining-adjustment', $transferRequest) }}" id="adjust-form">
                @csrf
                @method('PUT')

                <div class="flow-note">
                    Hanya item yang masih memiliki sisa belum dikirim yang ditampilkan di halaman ini. Ubah angka di <strong>Sisa Baru</strong>; sistem akan menghitung <strong>Request Baru = Sudah Dikirim + Sisa Baru</strong>. Mutasi dan surat jalan yang sudah ada tidak berubah.
                </div>

                @forelse($transferRequest->items as $item)
                    @php
                        $shippedQty = (float) ($shipmentTotals[$item->id]['quantity'] ?? 0);
                        $shippedKoli = (float) ($shipmentTotals[$item->id]['koli'] ?? 0);
                        $remainingQty = (float) ($item->ship_remaining_quantity ?? 0);
                        $remainingKoli = (float) ($item->ship_remaining_koli ?? 0);
                    @endphp
                    <div class="item-adjust-card adjust-row" data-shipped-qty="{{ $shippedQty }}" data-shipped-koli="{{ $shippedKoli }}">
                        <div class="item-adjust-header">
                            <div>
                                <span class="sku-badge">{{ optional($item->item)->sku ?? '-' }}</span>
                                <div class="item-name">{{ optional($item->item)->nama_barang ?? optional($item->item)->name ?? '-' }}</div>
                                <div class="item-sub">
                                    Request saat ini: {{ number_format((float) $item->quantity, 0, ',', '.') }} qty /
                                    {{ number_format((float) $item->koli, 2, ',', '.') }} koli
                                </div>
                            </div>
                            <div class="quick-actions">
                                <button type="button" class="btn btn-sm btn-light-danger set-zero">Tutup Sisa</button>
                                <button type="button" class="btn btn-sm btn-light reset-current"
                                    data-current-qty="{{ $remainingQty }}"
                                    data-current-koli="{{ $remainingKoli }}">
                                    Reset
                                </button>
                            </div>
                        </div>

                        <div class="adjust-body">
                            <div class="adjust-panel">
                                <div class="panel-title">Kondisi Saat Ini</div>
                                <div class="number-row">
                                    <span class="number-label">Sudah dikirim</span>
                                    <span class="number-value">{{ number_format($shippedQty, 0, ',', '.') }} qty</span>
                                </div>
                                <div class="number-row">
                                    <span class="number-label">Sisa sekarang</span>
                                    <span class="number-value">{{ number_format($remainingQty, 0, ',', '.') }} qty</span>
                                </div>
                                <div class="number-row">
                                    <span class="number-label">Sisa koli sekarang</span>
                                    <span class="number-value">{{ number_format($remainingKoli, 2, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="adjust-panel">
                                <div class="panel-title">Input Penyesuaian</div>
                                <div class="input-pair">
                                    <div>
                                        <label>Sisa Qty Baru</label>
                                        <input type="number"
                                            name="items[{{ $item->id }}][remaining_quantity]"
                                            class="form-control form-control-solid remaining-qty-input @error('items.' . $item->id . '.remaining_quantity') is-invalid @enderror"
                                            min="0"
                                            step="1"
                                            value="{{ old('items.' . $item->id . '.remaining_quantity', (int) round($remainingQty)) }}">
                                    </div>
                                    <div>
                                        <label>Sisa Koli Baru</label>
                                        <input type="number"
                                            name="items[{{ $item->id }}][remaining_koli]"
                                            class="form-control form-control-solid remaining-koli-input @error('items.' . $item->id . '.remaining_koli') is-invalid @enderror"
                                            min="0"
                                            step="0.01"
                                            value="{{ old('items.' . $item->id . '.remaining_koli', number_format($remainingKoli, 2, '.', '')) }}">
                                    </div>
                                </div>
                            </div>

                            <div class="adjust-panel">
                                <div class="panel-title">Hasil Setelah Disimpan</div>
                                <div class="number-row">
                                    <span class="number-label">Request qty baru</span>
                                    <span class="number-value result-value new-request-qty">{{ number_format($shippedQty + $remainingQty, 0, ',', '.') }}</span>
                                </div>
                                <div class="number-row">
                                    <span class="number-label">Request koli baru</span>
                                    <span class="number-value result-value new-request-koli">{{ number_format($shippedKoli + $remainingKoli, 2, ',', '.') }}</span>
                                </div>
                                <div class="number-row">
                                    <span class="number-label">Efek item</span>
                                    <span class="status-preview row-effect">Tetap ada sisa</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card mb-5">
                        <div class="card-body text-center py-12">
                            <div class="fw-bolder fs-4 mb-2">Tidak ada sisa item belum dikirim</div>
                            <div class="text-muted">Semua item pada request ini sudah tidak memiliki sisa pengiriman yang bisa disesuaikan.</div>
                        </div>
                    </div>
                @endforelse

                @if($transferRequest->items->count() > 0)
                    <div class="card mb-5">
                        <div class="card-body">
                            <label class="form-label required">Catatan Penyesuaian</label>
                            <textarea name="adjustment_note" rows="3" class="form-control form-control-solid @error('adjustment_note') is-invalid @enderror" required placeholder="Contoh: sisa tidak dikirim karena dibatalkan oleh cabang / digabung ke request berikutnya">{{ old('adjustment_note') }}</textarea>
                            @error('adjustment_note')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @if($errors->has('items'))
                                <div class="alert alert-danger mt-4">{{ $errors->first('items') }}</div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="sticky-actions d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="fw-bold" id="status_preview_text">Status akan dihitung ulang setelah disimpan.</div>
                        <div class="text-muted">Jika semua sisa menjadi 0 dan tidak ada shipment terbuka, request menjadi completed.</div>
                    </div>
                    <div class="d-flex gap-3">
                        <a href="{{ route('admin.stok-masuk.request-transfer.show', $transferRequest) }}" class="btn btn-light">Batal</a>
                        @if($transferRequest->items->count() > 0)
                            <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function () {
            function parseNumber(value) {
                const number = parseFloat(value);
                return Number.isFinite(number) ? number : 0;
            }

            function formatQty(value) {
                return Math.round(value).toLocaleString('id-ID');
            }

            function formatKoli(value) {
                return value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function refreshRow(row) {
                const shippedQty = parseNumber(row.data('shipped-qty'));
                const shippedKoli = parseNumber(row.data('shipped-koli'));
                const remainingQty = parseNumber(row.find('.remaining-qty-input').val());
                const remainingKoli = parseNumber(row.find('.remaining-koli-input').val());

                row.find('.new-request-qty').text(formatQty(shippedQty + remainingQty));
                row.find('.new-request-koli').text(formatKoli(shippedKoli + remainingKoli));
                row.find('.row-effect')
                    .text((remainingQty <= 0 && remainingKoli <= 0) ? 'Sisa ditutup' : 'Tetap ada sisa')
                    .toggleClass('bg-light-danger text-danger', remainingQty <= 0 && remainingKoli <= 0)
                    .toggleClass('bg-light-success text-success', remainingQty > 0 || remainingKoli > 0);
            }

            function refreshTotals() {
                let totalQty = 0;
                let totalKoli = 0;
                $('.adjust-row').each(function () {
                    const row = $(this);
                    totalQty += parseNumber(row.find('.remaining-qty-input').val());
                    totalKoli += parseNumber(row.find('.remaining-koli-input').val());
                });
                $('#total_remaining_qty_preview').text(formatQty(totalQty));
                $('#total_remaining_koli_preview').text(formatKoli(totalKoli));
                $('#status_preview_text').text(totalQty <= 0 && totalKoli <= 0
                    ? 'Semua sisa akan ditutup. Status dapat menjadi completed jika tidak ada shipment terbuka.'
                    : 'Masih ada sisa request. Status akan menjadi approved/on_progress sesuai riwayat pengiriman.');
            }

            function refreshAll() {
                $('.adjust-row').each(function () {
                    refreshRow($(this));
                });
                refreshTotals();
            }

            $(document).on('input', '.remaining-qty-input, .remaining-koli-input', function () {
                const input = $(this);
                if (parseNumber(input.val()) < 0) {
                    input.val(0);
                }
                refreshRow(input.closest('.adjust-row'));
                refreshTotals();
            });

            $(document).on('click', '.set-zero', function () {
                const row = $(this).closest('.adjust-row');
                row.find('.remaining-qty-input').val(0);
                row.find('.remaining-koli-input').val(0);
                refreshRow(row);
                refreshTotals();
            });

            $(document).on('click', '.reset-current', function () {
                const button = $(this);
                const row = button.closest('.adjust-row');
                row.find('.remaining-qty-input').val(parseNumber(button.data('current-qty')));
                row.find('.remaining-koli-input').val(parseNumber(button.data('current-koli')).toFixed(2));
                refreshRow(row);
                refreshTotals();
            });

            refreshAll();
        });
    </script>
@endpush
