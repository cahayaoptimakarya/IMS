@extends('layouts.app')

@push('styles')
    <style>
        .adjust-summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 12px; }
        .adjust-metric { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; background: #fff; }
        .adjust-metric .label { color: #6b7280; font-size: .85rem; }
        .adjust-metric .value { color: #111827; font-weight: 700; font-size: 1.25rem; margin-top: 4px; }
        .table-adjust th { white-space: nowrap; }
        .table-adjust td { vertical-align: middle; }
        .readonly-number { background: #f9fafb; border-radius: 6px; padding: 8px 10px; min-width: 92px; display: inline-block; }
        .new-request-qty, .new-request-koli { font-weight: 700; color: #2563eb; }
        @media (max-width: 992px) { .adjust-summary { grid-template-columns: repeat(2, minmax(150px, 1fr)); } }
        @media (max-width: 576px) { .adjust-summary { grid-template-columns: 1fr; } }
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
        $totalCurrentQty = (float) $transferRequest->items->sum('quantity');
        $totalCurrentKoli = (float) $transferRequest->items->sum('koli');
        $totalShippedQty = $transferRequest->items->sum(fn ($item) => (float) ($shipmentTotals[$item->id]['quantity'] ?? 0));
        $totalShippedKoli = $transferRequest->items->sum(fn ($item) => (float) ($shipmentTotals[$item->id]['koli'] ?? 0));
        $totalRemainingQty = (float) $transferRequest->items->sum('ship_remaining_quantity');
        $totalRemainingKoli = (float) $transferRequest->items->sum('ship_remaining_koli');
    @endphp

    <div class="content flex-row-fluid" id="kt_content">
        <div class="card mb-6">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-4 mb-5">
                    <div>
                        <div class="text-muted">Kode Request</div>
                        <div class="fw-bolder fs-3">{{ $transferRequest->code }}</div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted">Status Saat Ini</div>
                        <span class="badge badge-light-primary fs-7">{{ ucwords(str_replace('_', ' ', $transferRequest->status)) }}</span>
                    </div>
                </div>

                <div class="row g-4 mb-5">
                    <div class="col-md-6">
                        <div class="text-muted">Gudang Asal</div>
                        <div class="fw-bold">{{ optional($transferRequest->fromWarehouse)->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted">Gudang Tujuan</div>
                        <div class="fw-bold">{{ optional($transferRequest->toWarehouse)->name ?? '-' }}</div>
                    </div>
                </div>

                <div class="adjust-summary">
                    <div class="adjust-metric">
                        <div class="label">Request Qty Saat Ini</div>
                        <div class="value">{{ number_format($totalCurrentQty, 0, ',', '.') }}</div>
                    </div>
                    <div class="adjust-metric">
                        <div class="label">Sudah Dikirim</div>
                        <div class="value">{{ number_format($totalShippedQty, 0, ',', '.') }}</div>
                    </div>
                    <div class="adjust-metric">
                        <div class="label">Sisa Qty Saat Ini</div>
                        <div class="value">{{ number_format($totalRemainingQty, 0, ',', '.') }}</div>
                    </div>
                    <div class="adjust-metric">
                        <div class="label">Sisa Koli Saat Ini</div>
                        <div class="value">{{ number_format($totalRemainingKoli, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.stok-masuk.request-transfer.update-remaining-adjustment', $transferRequest) }}" id="adjust-form">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="mb-0">Atur Sisa Yang Belum Dikirim</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        Isi kolom <strong>Sisa Baru</strong> sesuai kebutuhan. Sistem otomatis menghitung <strong>Request Baru = Sudah Dikirim + Sisa Baru</strong>. Mutasi dan surat jalan yang sudah dibuat tidak berubah.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-adjust align-middle table-row-dashed">
                            <thead>
                                <tr class="fw-bolder text-muted">
                                    <th>Item</th>
                                    <th class="text-end">Sudah Dikirim</th>
                                    <th class="text-end">Sisa Qty Saat Ini</th>
                                    <th class="text-end">Sisa Qty Baru</th>
                                    <th class="text-end">Request Qty Baru</th>
                                    <th class="text-end">Sudah Dikirim Koli</th>
                                    <th class="text-end">Sisa Koli Saat Ini</th>
                                    <th class="text-end">Sisa Koli Baru</th>
                                    <th class="text-end">Request Koli Baru</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transferRequest->items as $item)
                                    @php
                                        $shippedQty = (float) ($shipmentTotals[$item->id]['quantity'] ?? 0);
                                        $shippedKoli = (float) ($shipmentTotals[$item->id]['koli'] ?? 0);
                                        $remainingQty = (float) ($item->ship_remaining_quantity ?? 0);
                                        $remainingKoli = (float) ($item->ship_remaining_koli ?? 0);
                                    @endphp
                                    <tr data-shipped-qty="{{ $shippedQty }}" data-shipped-koli="{{ $shippedKoli }}">
                                        <td>
                                            <div class="fw-bold">{{ optional($item->item)->sku ?? '-' }}</div>
                                            <div class="text-muted">{{ optional($item->item)->nama_barang ?? optional($item->item)->name ?? '-' }}</div>
                                            <div class="small text-muted mt-1">
                                                Request saat ini: {{ number_format((float) $item->quantity, 0, ',', '.') }} qty / {{ number_format((float) $item->koli, 2, ',', '.') }} koli
                                            </div>
                                        </td>
                                        <td class="text-end"><span class="readonly-number">{{ number_format($shippedQty, 0, ',', '.') }}</span></td>
                                        <td class="text-end"><span class="readonly-number">{{ number_format($remainingQty, 0, ',', '.') }}</span></td>
                                        <td>
                                            <input
                                                type="number"
                                                name="items[{{ $item->id }}][remaining_quantity]"
                                                class="form-control form-control-solid text-end remaining-qty-input @error('items.' . $item->id . '.remaining_quantity') is-invalid @enderror"
                                                min="0"
                                                step="1"
                                                value="{{ old('items.' . $item->id . '.remaining_quantity', (int) round($remainingQty)) }}"
                                            >
                                        </td>
                                        <td class="text-end"><span class="new-request-qty">{{ number_format($shippedQty + $remainingQty, 0, ',', '.') }}</span></td>
                                        <td class="text-end"><span class="readonly-number">{{ number_format($shippedKoli, 2, ',', '.') }}</span></td>
                                        <td class="text-end"><span class="readonly-number">{{ number_format($remainingKoli, 2, ',', '.') }}</span></td>
                                        <td>
                                            <input
                                                type="number"
                                                name="items[{{ $item->id }}][remaining_koli]"
                                                class="form-control form-control-solid text-end remaining-koli-input @error('items.' . $item->id . '.remaining_koli') is-invalid @enderror"
                                                min="0"
                                                step="0.01"
                                                value="{{ old('items.' . $item->id . '.remaining_koli', number_format($remainingKoli, 2, '.', '')) }}"
                                            >
                                        </td>
                                        <td class="text-end"><span class="new-request-koli">{{ number_format($shippedKoli + $remainingKoli, 2, ',', '.') }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        <label class="form-label required">Catatan Penyesuaian</label>
                        <textarea name="adjustment_note" rows="3" class="form-control form-control-solid @error('adjustment_note') is-invalid @enderror" required placeholder="Contoh: sisa tidak dikirim karena dibatalkan oleh cabang / digabung ke request berikutnya">{{ old('adjustment_note') }}</textarea>
                        @error('adjustment_note')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    @if($errors->has('items'))
                        <div class="alert alert-danger mt-4">{{ $errors->first('items') }}</div>
                    @endif
                </div>
                <div class="card-footer d-flex justify-content-between flex-wrap gap-3">
                    <div class="text-muted">
                        Jika semua sisa menjadi 0 dan tidak ada shipment terbuka, status akan menjadi completed.
                    </div>
                    <div class="d-flex gap-3">
                        <a href="{{ route('admin.stok-masuk.request-transfer.show', $transferRequest) }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
                    </div>
                </div>
            </div>
        </form>
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
            }

            $('.table-adjust tbody tr').each(function () {
                refreshRow($(this));
            });

            $(document).on('input', '.remaining-qty-input, .remaining-koli-input', function () {
                const input = $(this);
                if (parseNumber(input.val()) < 0) {
                    input.val(0);
                }
                refreshRow(input.closest('tr'));
            });
        });
    </script>
@endpush
