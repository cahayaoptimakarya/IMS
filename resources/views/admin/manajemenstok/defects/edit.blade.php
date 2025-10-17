@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Ubah Barang Rusak',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Barang Rusak', 'Ubah'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Ubah Draft Barang Rusak</h3>
        @if(($defect->status ?? 'pending') === 'pending')
        <form action="{{ route('admin.manajemenstok.defects.approve', $defect) }}" method="POST" onsubmit="return confirm('Approve barang rusak ini? Tindakan ini akan mengurangi stok.');">
            @csrf
            <button type="submit" class="btn btn-success">Approve</button>
        </form>
        @endif
    </div>
    <div class="card-body">
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form id="defect-edit-form" action="{{ route('admin.manajemenstok.defects.update', $defect) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Kode Dokumen</label>
                    <input type="text" class="form-control form-control-solid" value="{{ $defect->code }}" readonly>
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Tanggal</label>
                    <input type="text" name="date" class="form-control form-control-solid flatpickr-input @error('date') is-invalid @enderror" value="{{ old('date', $defect->date) }}" required>
                    @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Gudang</label>
                    <select name="warehouse_id" id="warehouse_id" class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Gudang" required>
                        <option></option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (int)old('warehouse_id', $defect->warehouse_id) === $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label required">Barang</label>
                    <select name="item_id" id="item_id" class="form-select form-select-solid @error('item_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Barang" required>
                        <option></option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}" data-koli="{{ (int)($item->koli ?? 1) }}" {{ (int)old('item_id', $defect->item_id) === $item->id ? 'selected' : '' }}>{{ $item->nama_barang }} ({{ $item->sku }})</option>
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
                    <input type="number" step="0.01" name="quantity" id="quantity" class="form-control form-control-solid @error('quantity') is-invalid @enderror" value="{{ old('quantity', $defect->quantity) }}" required>
                    @error('quantity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 mb-5">
                    <label class="form-label">Koli <span class="text-muted">(Opsional)</span></label>
                    <input type="number" step="0.01" name="koli" id="koli" class="form-control form-control-solid @error('koli') is-invalid @enderror" value="{{ old('koli', $defect->koli) }}">
                    @error('koli') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="mb-5">
                <label class="form-label required">Catatan (Alasan Rusak)</label>
                <textarea class="form-control form-control-solid @error('notes') is-invalid @enderror" name="notes" rows="3" required>{{ old('notes', $defect->notes) }}</textarea>
                @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.manajemenstok.defects.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>

        @if(($defect->status ?? 'pending') === 'approved')
        <div id="action" class="mt-10">
            <div class="separator my-8"></div>
            <h4 class="mb-5">Penanganan Setelah Approved</h4>
            <div class="row">
                <div class="col-lg-8">
                    @php
                        $action = $defect->action;
                        $actionStatus = $defect->action_status;
                    @endphp
                    @if(!$action || $actionStatus === 'pending')
                        <form action="{{ route('admin.manajemenstok.defects.action', $defect) }}" method="POST">
                            @csrf
                            <div class="mb-5">
                                <label class="form-label required">Pilih Tindakan</label>
                                <select name="action" class="form-select form-select-solid" data-control="select2" required>
                                    <option value="">Pilih</option>
                                    <option value="disposal" {{ old('action', $defect->action) === 'disposal' ? 'selected' : '' }}>Disposal</option>
                                    <option value="rework" {{ old('action', $defect->action) === 'rework' ? 'selected' : '' }}>Rework</option>
                                    <option value="return_to_stock" {{ old('action', $defect->action) === 'return_to_stock' ? 'selected' : '' }}>Return to Stock</option>
                                    <option value="other" {{ old('action', $defect->action) === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Catatan Tindakan</label>
                                <textarea name="action_notes" class="form-control form-control-solid" rows="3" placeholder="Catatan tambahan (opsional)">{{ old('action_notes', $defect->action_notes) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-info">Mulai Penanganan</button>
                        </form>
                    @elseif($actionStatus === 'in_progress')
                        <div class="alert alert-info">
                            Sedang diproses: <strong>{{ ucfirst(str_replace('_',' ', $action)) }}</strong>
                        </div>
                        <form action="{{ route('admin.manajemenstok.defects.action.complete', $defect) }}" method="POST" onsubmit="return confirm('Selesaikan penanganan?');">
                            @csrf
                            <div class="mb-5">
                                <label class="form-label">Catatan Tindakan</label>
                                <textarea name="action_notes" class="form-control form-control-solid" rows="3" placeholder="Catatan tambahan (opsional)">{{ old('action_notes', $defect->action_notes) }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-success">Selesaikan Penanganan</button>
                        </form>
                    @elseif($actionStatus === 'completed')
                        <div class="alert alert-success">
                            Tindakan <strong>{{ ucfirst(str_replace('_',' ', $action)) }}</strong> telah selesai.
                        </div>
                        <div class="mb-2"><strong>Catatan:</strong></div>
                        <div class="p-3 border rounded bg-light">{{ $defect->action_notes ?: '-' }}</div>
                    @elseif($actionStatus === 'cancelled')
                        <div class="alert alert-warning">Tindakan dibatalkan.</div>
                    @endif
                </div>
            </div>
        </div>
        @endif
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
    function fetchStockInfo(){
        const itemId = document.getElementById('item_id')?.value;
        const whId = document.getElementById('warehouse_id')?.value;
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
        $('#warehouse_id').on('change', fetchStockInfo);
        $('#quantity').on('input change', recalcFromQuantity);
        $('#koli').on('input change', recalcFromKoli);
    });
</script>
@endpush
