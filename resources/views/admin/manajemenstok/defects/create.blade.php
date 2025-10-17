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
                    <select name="item_id" id="item_id" class="form-select form-select-solid @error('item_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Barang" required>
                        <option></option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-koli="{{ (int)($item->koli ?? 1) }}" {{ old('item_id') == $item->id ? 'selected' : '' }}>
                                {{ $item->nama_barang }} ({{ $item->sku }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text text-muted" id="koli_info">Konversi: -</div>
                    <div class="form-text" id="stock_info"></div>
                    @error('item_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Jumlah (Qty)</label>
                    <input type="number" step="0.01" name="quantity" id="quantity" class="form-control form-control-solid @error('quantity') is-invalid @enderror" value="{{ old('quantity', 1) }}" required>
                    @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label">Koli <span class="text-muted">(Opsional)</span></label>
                    <input type="number" step="0.01" name="koli" id="koli" class="form-control form-control-solid @error('koli') is-invalid @enderror" value="{{ old('koli', 0) }}">
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
    var DEFECT_STOCK_QTY = 0;
    function getPerKoli(){
        const sel = document.querySelector('#item_id');
        if(!sel) return 1;
        const opt = sel.options[sel.selectedIndex];
        const v = parseFloat(opt?.getAttribute('data-koli') || '1');
        return isNaN(v) || v <= 0 ? 1 : v;
    }
    function updateKoliInfo(){
        const perKoli = getPerKoli();
        document.getElementById('koli_info').textContent = `Konversi: 1 koli = ${perKoli} qty`;
    }
    function recalcFromQuantity(){
        const perKoli = getPerKoli();
        const qty = parseFloat(document.getElementById('quantity').value || '0') || 0;
        const k = perKoli > 0 ? (qty / perKoli) : 0;
        document.getElementById('koli').value = k.toFixed(2);
        enforceNotExceedStock();
    }
    function recalcFromKoli(){
        const perKoli = getPerKoli();
        const k = parseFloat(document.getElementById('koli').value || '0') || 0;
        const qty = k * perKoli;
        document.getElementById('quantity').value = qty;
        enforceNotExceedStock();
    }
    function currentWarehouseId(){
        const wh = document.querySelector('select[name="warehouse_id"]') || document.querySelector('input[name="warehouse_id"][type="hidden"]');
        return wh ? wh.value : '';
    }
    function fetchStockInfo(){
        const itemId = document.getElementById('item_id')?.value;
        const whId = currentWarehouseId();
        const el = document.getElementById('stock_info');
        if(!itemId || !whId){ el.textContent=''; return; }
        $.get(`{{ route('admin.manajemenstok.defects.stock-info') }}`, { item_id: itemId, warehouse_id: whId })
            .done(function(res){
                const perKoli = getPerKoli();
                const qty = parseFloat(res.quantity||0);
                DEFECT_STOCK_QTY = qty;
                const koli = perKoli>0 ? (qty/perKoli) : 0;
                el.textContent = `Stok tersedia: ${qty} qty (${koli.toFixed(2)} koli)`;
                enforceNotExceedStock();
            })
            .fail(function(){ el.textContent=''; });
    }
    function enforceNotExceedStock(){
        const perKoli = getPerKoli();
        const qtyEl = document.getElementById('quantity');
        const koliEl = document.getElementById('koli');
        const qtyVal = parseFloat(qtyEl.value || '0') || 0;
        const maxQty = parseFloat(DEFECT_STOCK_QTY || 0);
        if (qtyVal > maxQty + 1e-9) {
            if (window.Swal) {
                Swal.fire({
                    text: 'Jumlah melebihi stok tersedia. Nilai direset ke 0.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            }
            qtyEl.value = 0;
            koliEl.value = 0;
            return;
        }
        const koliVal = parseFloat(koliEl.value || '0') || 0;
        const maxKoli = perKoli > 0 ? (maxQty / perKoli) : 0;
        if (koliVal > maxKoli + 1e-9) {
            if (window.Swal) {
                Swal.fire({
                    text: 'Koli melebihi stok tersedia. Nilai direset ke 0.',
                    icon: 'error',
                    buttonsStyling: false,
                    confirmButtonText: 'Ok',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            }
            qtyEl.value = 0;
            koliEl.value = 0;
        }
    }
    $(function(){
        if (window.flatpickr) {
            flatpickr('input[name="date"]', { dateFormat: 'Y-m-d' });
        }
        if ($.fn.select2) {
            $('[data-control="select2"]').select2({ placeholder: function(){ return $(this).data('placeholder') || 'Pilih'; } });
        }

        updateKoliInfo();
        fetchStockInfo();
        $('#item_id').on('change', function(){
            updateKoliInfo();
            // Reset nilai saat ganti item agar sesuai stok baru
            $('#quantity').val(0);
            $('#koli').val(0);
            fetchStockInfo();
        });
        $('select[name="warehouse_id"]').on('change', fetchStockInfo);
        $('#quantity').on('input change', recalcFromQuantity);
        $('#koli').on('input change', recalcFromKoli);
    });
</script>
@endpush
