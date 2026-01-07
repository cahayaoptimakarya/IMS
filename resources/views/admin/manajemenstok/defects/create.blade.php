@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Barang Rusak',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Barang Rusak', 'Tambah'],
    ])
@endpush

@push('styles')
<style>
    .item-row-card {
        border: 1px solid #dfe3ea;
        border-radius: 1rem;
        padding: 1.25rem;
        background: #ffffff;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
    }
    .item-row-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 0.75rem;
    }
    .item-row-card .form-control-white {
        background: #f4f6fa;
    }
    .item-row-card .form-control.form-control-solid,
    .item-row-card .form-select.form-select-solid {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #111827;
    }
    .item-row-card label {
        color: #1f2937;
    }
    @media (max-width: 768px) {
        .item-row-card { padding: 1rem; }
    }
</style>
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
            <div class="row g-5">
                <div class="col-md-4">
                    <label class="form-label">Kode (otomatis)</label>
                    <input type="text" class="form-control form-control-solid" value="{{ $newCode }}" readonly>
                    <div class="form-text">Kode akan dibuat otomatis untuk tiap item.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Tanggal</label>
                    <input type="text" name="date" class="form-control form-control-solid flatpickr-input @error('date') is-invalid @enderror" value="{{ old('date', date('Y-m-d')) }}" required>
                    @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label required">Gudang</label>
                    @if (auth()->user()->warehouse_id)
                        <input type="hidden" name="warehouse_id" value="{{ auth()->user()->warehouse_id }}">
                        <input type="text" class="form-control form-control-solid" value="{{ auth()->user()->warehouse->name }}" readonly>
                    @else
                        <select name="warehouse_id" class="form-select form-select-solid @error('warehouse_id') is-invalid @enderror" data-control="select2" data-placeholder="Pilih Gudang" required>
                            <option></option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" {{ old('warehouse_id') == $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        @error('warehouse_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @endif
                </div>
            </div>

            <div class="separator my-6"></div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Daftar Barang Rusak</h4>
                    <div class="text-muted fs-7">Tambah beberapa item rusak dalam satu pengajuan.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle table-row-dashed" id="items-table">
                    <thead>
                        <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                            <th>Item &amp; Detail</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="button" class="btn btn-light-primary" id="add-row-btn">
                    <i class="fas fa-plus me-2"></i>Tambah Item
                </button>
            </div>

            <div class="d-flex gap-3 mt-6">
                <button type="submit" class="btn btn-primary">
                    Simpan
                </button>
                <a href="{{ route('admin.manajemenstok.defects.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    const itemsData = @json($items);
    const oldItems = @json(old('items', []));
    let rowIndex = 0;

    function warehouseId(){
        const wh = document.querySelector('select[name="warehouse_id"]') || document.querySelector('input[name="warehouse_id"][type="hidden"]');
        return wh ? wh.value : '';
    }

    function getPerKoliForOption(optionEl){
        const v = parseFloat(optionEl?.getAttribute('data-koli') || '1');
        return (!isNaN(v) && v > 0) ? v : 1;
    }

    function recalcFromQty($tr){
        const qtyInput = $tr.find('.qty-input');
        const koliInput = $tr.find('.koli-input');
        const perKoli = getPerKoliForOption($tr.find('.item-select option:selected')[0]);
        const qty = parseFloat(qtyInput.val() || '0') || 0;
        const k = perKoli > 0 ? qty / perKoli : 0;
        koliInput.val(k.toFixed(2));
        enforceStock($tr);
    }

    function recalcFromKoli($tr){
        const qtyInput = $tr.find('.qty-input');
        const koliInput = $tr.find('.koli-input');
        const perKoli = getPerKoliForOption($tr.find('.item-select option:selected')[0]);
        const k = parseFloat(koliInput.val() || '0') || 0;
        const qty = k * perKoli;
        qtyInput.val(qty);
        enforceStock($tr);
    }

    function enforceStock($tr){
        const maxQty = parseFloat($tr.data('stock') || 0);
        if (!maxQty) return;
        const qtyInput = $tr.find('.qty-input');
        const koliInput = $tr.find('.koli-input');
        const perKoli = getPerKoliForOption($tr.find('.item-select option:selected')[0]);
        let qtyVal = parseFloat(qtyInput.val() || '0') || 0;
        if (qtyVal > maxQty + 1e-9) {
            qtyVal = maxQty;
            qtyInput.val(qtyVal);
            koliInput.val(perKoli > 0 ? (qtyVal / perKoli).toFixed(2) : 0);
            if (window.toastr) toastr.warning('Jumlah melebihi stok tersedia, disesuaikan otomatis.');
        }
    }

    function fetchStock($tr){
        const itemId = $tr.find('.item-select').val();
        const whId = warehouseId();
        const stockEl = $tr.find('.stock-info');
        const perKoliText = $tr.find('.koli-info');
        if (!itemId || !whId) {
            stockEl.text('-');
            $tr.data('stock', 0);
            return;
        }
        const perKoli = getPerKoliForOption($tr.find('.item-select option:selected')[0]);
        perKoliText.text(`1 koli = ${perKoli} qty`);
        $.get(`{{ route('admin.manajemenstok.defects.stock-info') }}`, { item_id: itemId, warehouse_id: whId })
            .done(function(res){
                const qty = parseFloat(res.quantity || 0);
                const koli = perKoli > 0 ? (qty / perKoli) : 0;
                stockEl.text(`${qty} qty (${koli.toFixed(2)} koli)`);
                $tr.data('stock', qty);
                enforceStock($tr);
            })
            .fail(function(){
                stockEl.text('-');
                $tr.data('stock', 0);
            });
    }

    function addRow(prefill = {}){
        const row = `
        <tr data-index="${rowIndex}">
            <td class="p-0">
                <div class="item-row-card mb-4">
                    <div class="d-flex flex-wrap gap-4 align-items-start">
                        <div class="flex-grow-1 min-w-240px">
                            <label class="form-label required">Barang</label>
                            <select name="items[${rowIndex}][item_id]" class="form-select form-select-solid item-select" data-control="select2" required></select>
                            <div class="text-muted small mt-2">Stok: <span class="stock-info">-</span></div>
                            <div class="text-muted small">Konversi: <span class="koli-info">-</span></div>
                        </div>
                        <div class="flex-grow-1 w-100">
                            <div class="item-row-grid">
                                <div>
                                    <label class="form-label required">Qty</label>
                                    <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][quantity]" class="form-control form-control-solid qty-input" value="${prefill.quantity ?? 0}" required>
                                </div>
                                <div>
                                    <label class="form-label">Koli</label>
                                    <input type="number" step="0.01" name="items[${rowIndex}][koli]" class="form-control form-control-solid koli-input" value="${prefill.koli ?? 0}">
                                </div>
                            </div>
                        </div>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-sm btn-light-danger remove-row"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label required">Catatan</label>
                        <textarea name="items[${rowIndex}][notes]" class="form-control form-control-solid" rows="2" required placeholder="Alasan rusak">${prefill.notes ?? ''}</textarea>
                    </div>
                </div>
            </td>
        </tr>`;
        $('#items-table tbody').append(row);

        const $tr = $(`#items-table tbody tr[data-index="${rowIndex}"]`);
        const sel = $tr.find('.item-select');
        sel.append(new Option('', '', true, true));
        itemsData.forEach(function(it){
            const labelSku = it.sku ? `${it.sku} - ` : '';
            const opt = new Option(`${labelSku}${it.nama_barang}`, it.id, false, false);
            opt.setAttribute('data-koli', it.koli || 1);
            sel.append(opt);
        });
        if ($.fn.select2) {
            sel.select2({ placeholder: 'Pilih Barang', width: '100%' });
        }
        if (prefill.item_id) {
            sel.val(prefill.item_id).trigger('change');
        } else {
            sel.val(null).trigger('change');
        }
        $tr.data('stock', 0);
        fetchStock($tr);
        rowIndex++;
    }

    $(function(){
        if (window.flatpickr) {
            flatpickr('input[name="date"]', { dateFormat: 'Y-m-d' });
        }
        if ($.fn.select2) {
            $('[data-control="select2"]').select2({ placeholder: function(){ return $(this).data('placeholder') || 'Pilih'; } });
        }

        if (Array.isArray(oldItems) && oldItems.length) {
            oldItems.forEach(it => addRow(it));
        } else {
            addRow();
        }

        $('#add-row-btn').on('click', function(){ addRow(); });

        $('#items-table').on('change', '.item-select', function(){
            const $tr = $(this).closest('tr');
            fetchStock($tr);
        });

        $('#items-table').on('input change', '.qty-input', function(){
            recalcFromQty($(this).closest('tr'));
        });
        $('#items-table').on('input change', '.koli-input', function(){
            recalcFromKoli($(this).closest('tr'));
        });

        $('select[name="warehouse_id"]').on('change', function(){
            $('#items-table tbody tr').each(function(){ fetchStock($(this)); });
        });

        $('#items-table').on('click', '.remove-row', function(){
            $(this).closest('tr').remove();
            if ($('#items-table tbody tr').length === 0) addRow();
        });
    });
</script>
@endpush
