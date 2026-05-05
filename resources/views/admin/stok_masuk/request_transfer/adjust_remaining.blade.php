@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Penyesuaian Sisa Request Transfer',
        'breadcrumbs' => ['Admin', 'Stok Masuk', 'Request Transfer', 'Penyesuaian Sisa'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card mb-6">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="text-muted">Kode</div>
                        <div class="fw-bold">{{ $transferRequest->code }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Status</div>
                        <div><span class="badge badge-light-primary">{{ ucwords(str_replace('_', ' ', $transferRequest->status)) }}</span></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Gudang Asal</div>
                        <div class="fw-bold">{{ optional($transferRequest->fromWarehouse)->name ?? '-' }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted">Gudang Tujuan</div>
                        <div class="fw-bold">{{ optional($transferRequest->toWarehouse)->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.stok-masuk.request-transfer.update-remaining-adjustment', $transferRequest) }}">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h3 class="mb-0">Sisa Item Belum Dikirim</h3>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        Penyesuaian ini hanya mengurangi sisa yang belum dikirim. Qty yang sudah masuk shipment tidak berubah dan tetap menjadi acuan mutasi serta surat jalan.
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed">
                            <thead>
                                <tr class="fw-bolder text-muted">
                                    <th>SKU</th>
                                    <th>Nama Item</th>
                                    <th class="text-end">Request Qty Saat Ini</th>
                                    <th class="text-end">Sudah Dikirim</th>
                                    <th class="text-end">Sisa Qty Saat Ini</th>
                                    <th class="text-end">Sisa Qty Baru</th>
                                    <th class="text-end">Request Koli Saat Ini</th>
                                    <th class="text-end">Sudah Dikirim Koli</th>
                                    <th class="text-end">Sisa Koli Saat Ini</th>
                                    <th class="text-end">Sisa Koli Baru</th>
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
                                    <tr>
                                        <td class="fw-bold">{{ optional($item->item)->sku ?? '-' }}</td>
                                        <td>{{ optional($item->item)->nama_barang ?? optional($item->item)->name ?? '-' }}</td>
                                        <td class="text-end">{{ number_format((float) $item->quantity, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($shippedQty, 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($remainingQty, 0, ',', '.') }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                name="items[{{ $item->id }}][remaining_quantity]"
                                                class="form-control form-control-solid text-end @error('items.' . $item->id . '.remaining_quantity') is-invalid @enderror"
                                                min="0"
                                                max="{{ $remainingQty }}"
                                                step="1"
                                                value="{{ old('items.' . $item->id . '.remaining_quantity', (int) round($remainingQty)) }}"
                                            >
                                        </td>
                                        <td class="text-end">{{ number_format((float) $item->koli, 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($shippedKoli, 2, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($remainingKoli, 2, ',', '.') }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                name="items[{{ $item->id }}][remaining_koli]"
                                                class="form-control form-control-solid text-end @error('items.' . $item->id . '.remaining_koli') is-invalid @enderror"
                                                min="0"
                                                max="{{ $remainingKoli }}"
                                                step="0.01"
                                                value="{{ old('items.' . $item->id . '.remaining_koli', number_format($remainingKoli, 2, '.', '')) }}"
                                            >
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        <label class="form-label required">Catatan Penyesuaian</label>
                        <textarea name="adjustment_note" rows="3" class="form-control form-control-solid @error('adjustment_note') is-invalid @enderror" required>{{ old('adjustment_note') }}</textarea>
                        @error('adjustment_note')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    @if($errors->has('items'))
                        <div class="alert alert-danger mt-4">{{ $errors->first('items') }}</div>
                    @endif
                </div>
                <div class="card-footer d-flex justify-content-end gap-3">
                    <a href="{{ route('admin.stok-masuk.request-transfer.show', $transferRequest) }}" class="btn btn-light">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
                </div>
            </div>
        </form>
    </div>
@endsection
