@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Item Details',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items', 'Detail Item'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Detail Item: {{ $item->sku }}</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Koli:</label>
                    <p class="form-control-static">{{ $item->koli }}</p>
                </div>
                @php($num = fn ($v, $d) => rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ','))
                <div class="mb-3">
                    <label class="form-label fw-bold">Dimensi per Koli (P &times; L &times; T):</label>
                    <p class="form-control-static">
                        @if ($item->cbm_per_koli !== null)
                            {{ $num($item->panjang_koli, 2) }} &times; {{ $num($item->lebar_koli, 2) }} &times; {{ $num($item->tinggi_koli, 2) }} cm
                        @else
                            <span class="text-muted">Belum diisi</span>
                        @endif
                    </p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">CBM:</label>
                    <p class="form-control-static">
                        @if ($item->cbm_per_koli !== null)
                            {{ $num($item->cbm_per_koli, 6) }} m³ per koli
                            @if ($item->cbm_per_unit !== null)
                                <span class="text-muted">({{ $num($item->cbm_per_unit, 8) }} m³ per {{ $item->uom->name ?? 'satuan' }})</span>
                            @endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">SKU:</label>
                    <p class="form-control-static">{{ $item->sku }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Barang:</label>
                    <p class="form-control-static">{{ $item->nama_barang }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Status:</label>
                    <p class="form-control-static">
                        <span class="badge badge-light-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi:</label>
                    <p class="form-control-static">{{ $item->deskripsi }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">UOM:</label>
                    <p class="form-control-static">{{ $item->uom->name ?? '' }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Tanggal Dibuat:</label>
                    <p class="form-control-static">{{ $item->created_at }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Terakhir Diperbarui:</label>
                    <p class="form-control-static">{{ $item->updated_at }}</p>
                </div>
                <a href="{{ route('admin.masterdata.items.index') }}" class="btn btn-primary">Kembali</a>
            </div>
        </div>
    </div>
@endsection
