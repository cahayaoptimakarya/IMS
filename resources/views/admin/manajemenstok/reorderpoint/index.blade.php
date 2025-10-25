@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Reorder Point',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Reorder Point'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect><path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path></svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-300px ps-15" placeholder="Cari SKU / Nama">
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                        <span class="svg-icon svg-icon-2"><i class="fas fa-filter"></i></span> Filter
                    </button>
                    <div class="menu menu-sub menu-sub-dropdown w-350px" data-kt-menu="true" id="rp_filter_menu">
                        <div class="px-7 py-5">
                            <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                        </div>
                        <div class="separator border-gray-200"></div>
                        <div class="px-7 py-5">
                            <div class="mb-7">
                                <label class="form-label fs-6 fw-bold mb-2">Gudang:</label>
                                <select class="form-select form-select-solid fw-bolder" id="warehouse_filter" data-kt-select2="true">
                                    <option value="all">Semua Gudang</option>
                                    @foreach ($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ isset($defaultWarehouseId) && (int)$defaultWarehouseId === (int)$w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-7">
                                <label class="form-label fs-6 fw-bold mb-2">Kategori:</label>
                                <select class="form-select form-select-solid fw-bolder" id="category_filter" data-kt-select2="true">
                                    <option value="all">Semua Kategori</option>
                                    @foreach ($categories as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-7 form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" value="1" id="only_reorder">
                                <label class="form-check-label" for="only_reorder">Hanya yang perlu reorder</label>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="button" class="btn btn-light btn-active-light-primary me-2" id="reset_filter">Reset</button>
                                <button type="button" class="btn btn-primary" id="apply_filter">Terapkan</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU / Nama</th>
                                <th class="text-center">Gudang</th>
                                <th class="text-center">Lead Time PO (hari)</th>
                                <th class="text-center">Lead Time Safety (hari)</th>
                                <th class="text-center">Safety Stock</th>
                                <th class="text-center">ROP (Qty)</th>
                                <th class="text-center">On-hand (Qty)</th>
                                <th class="text-center">ROP (Koli)</th>
                                <th class="text-center">On-hand (Koli)</th>
                                <th class="text-center">Selisih (Qty)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(function(){
    if ($.fn.select2) { $('[data-kt-select2="true"]').select2(); }

    const $table = $('#table-on-page');
    const $search = $('#search_input');
    const $wh = $('#warehouse_filter');
    const $cat = $('#category_filter');
    const $only = $('#only_reorder');

    const formatName = (sku, name) => {
        sku = sku || '-'; name = name || '-';
        return `<div class="d-flex flex-column"><span class="text-primary fw-bolder">${sku}</span><span class="text-muted fs-7">${name}</span></div>`;
    };

    const fmt = (v) => {
        const n = parseFloat(v||0); return isNaN(n) ? '0' : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    };

    const load = () => {
        if ($.fn.DataTable.isDataTable('#table-on-page')) { $table.DataTable().destroy(); }
        $table.DataTable({
            processing:true, serverSide:true, searching:false,
            ajax:{ url: `{{ route('admin.manajemenstok.reorder-point.data') }}`, data: function(d){
                d.search = d.search || {}; d.search.value = $search.val();
                d.warehouse_id = $wh.val(); d.category_id = $cat.val(); d.only_reorder = $only.is(':checked');
            }},
            order:[[0,'asc']],
            columns:[
                { data:null, render:(r)=>formatName(r.sku, r.nama_barang) },
                { data:'warehouse', className:'text-center' },
                { data:'lead_time_po', className:'text-center' },
                { data:'lead_time_safety_stock', className:'text-center' },
                { data:'safety_stock_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'rop_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'onhand_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'rop_koli', className:'text-center', render:(v)=>fmt(v) },
                { data:'onhand_koli', className:'text-center', render:(v)=>fmt(v) },
                { data:'gap_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'status', className:'text-center', render:(s)=> s==='reorder' ? '<span class="badge badge-light-danger">Reorder</span>' : '<span class="badge badge-light-success">Aman</span>' },
            ],
        });
    };

    $('#apply_filter').on('click', load);
    $('#reset_filter').on('click', function(){ $wh.val('{{ $defaultWarehouseId ?? '' }}').trigger('change'); $cat.val('all').trigger('change'); $only.prop('checked', false); load();});
    $search.on('keyup', _.debounce(load, 400));
    load();
});
</script>
@endpush
