@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Barang Rusak',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Barang Rusak', 'Tambah'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Tambah Data Barang Rusak</h3>
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="defect-form" action="{{ route('admin.manajemenstok.defects.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Kode Dokumen</label>
                    <input type="text" name="code" class="form-control form-control-solid" value="{{ $newCode }}" readonly>
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Tanggal</label>
                    <input type="text" name="date" class="form-control form-control-solid flatpickr-input @error('date') is-invalid @enderror" value="{{ old('date', date('Y-m-d')) }}" required>
                    @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-5">
                    @if (auth()->user()->warehouse_id)
                        <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                        <label class="form-label required">Gudang</label>
                        <input type="text" class="form-control form-control-solid" value="{{ auth()->user()->warehouse->name }}" readonly>
                    @else
                        <label class="form-label required">Gudang</label>
                        <select name="warehouse_id" class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Gudang" required>
                            <option></option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @endif
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Barang</label>
                    <select name="item_id" class="form-select form-select-solid @error('item_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Barang" required>
                        <option></option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->nama_barang }} ({{ $item->sku }})
                            </option>
                        @endforeach
                    </select>
                    @error('item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Jumlah</label>
                    <input type="number" step="0.01" name="quantity" class="form-control form-control-solid @error('quantity') is-invalid @enderror" value="{{ old('quantity', 1) }}" required>
                    @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label">Koli <span class="text-muted">(Opsional)</span></label>
                    <input type="number" step="0.01" name="koli" class="form-control form-control-solid @error('koli') is-invalid @enderror" value="{{ old('koli', 0) }}">
                    @error('koli') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label required">Catatan (Alasan Rusak)</label>
                <textarea class="form-control form-control-solid @error('notes') is-invalid @enderror" name="notes" rows="3" required>{{ old('notes') }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.manajemenstok.defects.index') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    if (window.flatpickr) {
        flatpickr('input[name="date"]', { dateFormat: 'Y-m-d' });
    }
    if ($.fn.select2) {
        $('[data-control="select2"]').select2({ placeholder: function(){ return $(this).data('placeholder') || 'Pilih'; } });
    }
</script>
@endpush
