@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Reorder Point',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Reorder Point'],
    ])
@endpush

@push('styles')
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        .chip-danger { background: #FEE2E2; color: #EF4444; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <!-- Status Card (separate from table card) -->
        <div class="row g-5 g-xl-8">
            <div class="col-12">
                <div class="card card-xl-stretch mb-xl-8">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="d-flex align-items-center mb-4 mb-md-0">
                                <div class="symbol symbol-50px me-5 status-card-icon">
                                    <div class="symbol-label">
                                        <i class="fas fa-bell fs-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-dark fw-bolder fs-5 mb-1">Status Reorder Point</div>
                                    <div class="text-muted fw-bold">Ringkasan kondisi stok terhadap ROP</div>
                                </div>
                            </div>
                            <div class="status-metrics">
                                <div class="metric">
                                    <div class="chip chip-danger" id="status_reorder">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Perlu Reorder</div>
                                </div>
                                <div class="metric">
                                    <div class="chip chip-success" id="status_ok">
                                        <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                                    </div>
                                    <div class="text-muted fs-8 mt-1">Aman</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                    <a href="#" id="btn-export-excel" class="btn btn-primary me-3">
                        <span class="svg-icon svg-icon-2 me-1"><i class="fas fa-file-excel"></i></span> Export Excel
                    </a>
                    <button type="button" class="btn btn-light-danger me-3" id="btn-recalc-rop">
                        <span class="svg-icon svg-icon-2 me-1"><i class="fas fa-sync-alt"></i></span> Recalc ROP
                    </button>
                    <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
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
                            <div class="mb-7">
                                <label class="form-label fs-6 fw-bold mb-2">Status:</label>
                                <select class="form-select form-select-solid fw-bolder" id="status_filter" data-kt-select2="true">
                                    <option value="all">Semua</option>
                                    <option value="reorder">Perlu Reorder</option>
                                    <option value="ok">Aman</option>
                                </select>
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
                <div class="text-center mb-5">
                    <h3 class="mb-0">Daftar Reorder Point</h3>
                    <small id="filter-info" class="text-muted"></small>
                </div>
                <div class="alert alert-light-primary border p-5 mb-7">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <span class="svg-icon svg-icon-2 me-3 text-primary"><i class="fas fa-info-circle"></i></span>
                            <div class="fw-bolder">Keterangan Metrik</div>
                        </div>
                        <button class="btn btn-sm btn-light-primary" id="rp_metric_toggle" type="button" data-bs-toggle="collapse" data-bs-target="#rp_metric_notes" aria-expanded="false" aria-controls="rp_metric_notes">Tampilkan</button>
                    </div>
                    <div class="collapse mt-4" id="rp_metric_notes">
                        <div class="row g-5 text-gray-700 small">
                            <div class="col-md-6">
                                <ul class="mb-0 list-unstyled">
                                    <li class="mb-2"><span class="fw-bold text-dark">Avg OUT / hari</span>: Rata-rata kuantitas keluar per hari untuk SKU di gudang terpilih. Pembagi hari dibakukan: <code>30 hari</code> bila umur data <30 hari, <code>60 hari</code> bila umur 30–<60 hari, dan <code>90 hari</code> bila umur ≥60 hari. Total OUT dijumlah dari jendela 30/60/90 hari terakhir sesuai bucket. Sumber data dari Stock Movement bertipe keluar (stock_out, transfer_out; fallback quantity < 0).</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">Lead Time PO (hari)</span>: Diambil dari field item <code>lead_time_po_days</code> (dapat diubah di form Item).</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">Lead Time Safety (hari)</span>: Diambil dari field item <code>lead_time_safety_days</code> (buffer hari sebagai safety).</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0 list-unstyled">
                                    <li class="mb-2"><span class="fw-bold text-dark">Safety Stock</span>: <code>Avg OUT / hari × Lead Time Safety</code> (mengacu ke kolom "Safety (hari)" pada item).</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">ROP (Qty)</span>: <code>(Rata-rata permintaan harian × Lead Time PO) + Safety Stock</code>.</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">On‑hand (Qty/Koli)</span>: Diambil dari Inventory per gudang untuk SKU terkait.</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">On‑Order (Qty)</span>: Jumlah yang masih outstanding dari pesanan masuk (PO) ke gudang yang sama. Diambil dari remaining_quantity pada Stock In Orders berstatus <code>requested</code>/<code>on_shipping</code> dan item berstatus <code>pending</code>/<code>on_progress</code>.</li>
                                    <li class="mb-2"><span class="fw-bold text-dark">ROP (Koli)</span>: <code>ROP (Qty) ÷ koli per item</code>.</li>
                                    <li class="mb-0"><span class="fw-bold text-dark">Selisih (Qty)</span>: <code>ROP (Qty) − On‑hand (Qty)</code>. Positif berarti perlu reorder; negatif/0 berarti aman.</li>
                                </ul>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="text-muted fst-italic">Catatan: Perhitungan rata-rata memakai bucket 30/60/90 hari. Indikator di tabel: <span class="badge badge-light-secondary">30d</span> = pembagi 30 hari, <span class="badge badge-light-info">60d</span> = 60 hari, <span class="badge badge-light-primary">90d</span> = 90 hari.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                        <thead>
                            <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0 border-1 border-gray-200">
                                <th rowspan="2" class="min-w-200px">SKU / Nama</th>
                                <th class="text-center" rowspan="2">Gudang</th>
                                <th class="text-center" colspan="3">Pergerakan</th>
                                <th class="text-center" colspan="2">Lead Time</th>
                                <th class="text-center" colspan="3">ROP</th>
                                <th class="text-center" colspan="2">Stok</th>
                                <th class="text-center" colspan="3">Ringkasan</th>
                            </tr>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0 border-1 border-gray-200">
                                <th class="text-center min-w-50px">Avg OUT / hari</th>
                                <th class="text-center min-w-50px">Total OUT (Qty)</th>
                                <th class="text-center min-w-50px">Hari Perhitungan</th>
                                <th class="text-center min-w-50px">PO (hari)</th>
                                <th class="text-center min-w-50px">Safety (hari)</th>
                                <th class="text-center min-w-50px">Safety Stock</th>
                                <th class="text-center min-w-50px">ROP (Qty)</th>
                                <th class="text-center min-w-50px">ROP (Koli)</th>
                                <th class="text-center min-w-50px">On-hand (Qty)</th>
                                <th class="text-center min-w-50px">On-hand (Koli)</th>
                                <th class="text-center min-w-50px">On-Order (Qty)</th>
                                <th class="text-center min-w-50px">Selisih (Qty)</th>
                                <th class="text-center min-w-50px">Status</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600 border-1 border-gray-200"></tbody>
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
    const $status = $('#status_filter');
    

    const formatName = (sku, name, perKoli, uom) => {
        sku = sku || '-'; name = name || '-';
        const pk = (perKoli !== undefined && perKoli !== null) ? perKoli : '';
        const unit = uom || '';
        const perKoliLine = pk ? `<span class="text-gray-600 fs-7">Per Koli: ${fmt(pk)} ${unit}</span>` : '';
        return `<div class="d-flex flex-column">`
            + `<span class="text-primary fw-bolder">${sku}</span>`
            + `<span class="text-muted fs-7">${name}</span>`
            + perKoliLine
            + `</div>`;
    };

    const fmt = (v) => {
        const n = parseFloat(v||0); return isNaN(n) ? '0' : n.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    };

    const resolveText = (sel, def) => {
        const $opt = sel.find('option:selected');
        return $opt.length ? $opt.text() : def;
    };

    const loadStatusCounts = () => {
        $.get(`{{ route('admin.manajemenstok.reorder-point.status-counts') }}`, {
            warehouse_id: $wh.val(),
            category_id: $cat.val()
        }).done(function(res){
            $('#status_reorder').text(res.reorder ?? 0);
            $('#status_ok').text(res.ok ?? 0);
        }).fail(function(){
            $('#status_reorder').text('0');
            $('#status_ok').text('0');
        });
    };

    const load = () => {
        const whText = resolveText($wh, 'Semua Gudang');
        const catText = resolveText($cat, 'Semua Kategori');
        const stText = resolveText($status, 'Semua Status');
        $('#filter-info').text(`Gudang: ${whText} • Kategori: ${catText} • Status: ${stText}`);
        loadStatusCounts();
        if ($.fn.DataTable.isDataTable('#table-on-page')) { $table.DataTable().destroy(); }
        $table.DataTable({
            processing:true, serverSide:true, searching:false,
            ajax:{ url: `{{ route('admin.manajemenstok.reorder-point.data') }}`, data: function(d){
                d.search = d.search || {}; d.search.value = $search.val();
                d.warehouse_id = $wh.val(); d.category_id = $cat.val(); d.status = $status.val();
            }},
            order:[[0,'asc']],
            drawCallback: function(){
                if (window.bootstrap) {
                    var list = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    list.forEach(function(el){ try { new bootstrap.Tooltip(el); } catch(e){} });
                }
            },
            columns:[
                { data:null, render:(r)=>formatName(r.sku, r.nama_barang, r.per_koli, r.uom) },
                { data:'warehouse', className:'text-center' },
                { data:null, className:'text-center', render:(r)=>{
                    let cls = 'badge-light-secondary', label = (r.avg_source||'').toString();
                    if (label === '90d') cls = 'badge-light-primary';
                    else if (label === '60d') cls = 'badge-light-info';
                    else if (label === '30d') cls = 'badge-light-secondary';
                    const days = parseInt(r.total_days||0) || 0;
                    const total = parseFloat(r.total_out_qty||0) || 0;
                    const avg = parseFloat(r.avg_daily_out||0) || 0;
                    const d = new Date(); if (days > 0) d.setDate(d.getDate() - (days - 1));
                    const y = d.getFullYear(); const m = String(d.getMonth()+1).padStart(2,'0'); const dd = String(d.getDate()).padStart(2,'0');
                    const startStr = `${y}-${m}-${dd}`;
                    const tip = `Periode mulai: ${startStr}<br/>Total OUT: ${fmt(total)}<br/>Avg dipakai: ${fmt(avg)}`;
                    return fmt(r.avg_daily_out) + ' ' + `<span class="badge ${cls} ms-1" data-bs-toggle="tooltip" data-bs-html="true" title="${tip}">${label||''}</span>`;
                } },
                { data:'total_out_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'total_days', className:'text-center' },
                { data:'lead_time_po', className:'text-center' },
                { data:'lead_time_safety_stock', className:'text-center' },
                { data:'safety_stock_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'rop_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'rop_koli', className:'text-center', render:(v)=>fmt(v) },
                { data:'onhand_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'onhand_koli', className:'text-center', render:(v)=>fmt(v) },
                { data:'on_order_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'gap_qty', className:'text-center', render:(v)=>fmt(v) },
                { data:'status', className:'text-center', render:(s)=> s==='reorder' ? '<span class="badge badge-light-danger">Reorder</span>' : '<span class="badge badge-light-success">Aman</span>' },
            ],
        });
    };

    // Simple debounce helper to avoid external libs
    const debounce = (fn, wait=400) => { let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn.apply(null,args), wait); }; };
    $('#apply_filter').on('click', load);
    $('#reset_filter').on('click', function(){ $wh.val('{{ $defaultWarehouseId ?? '' }}').trigger('change'); $cat.val('all').trigger('change'); $status.val('all').trigger('change'); load();});
    $search.on('keyup', debounce(load, 400));
    $('#btn-export-excel').on('click', function(e){
        e.preventDefault();
        const params = new URLSearchParams({
            warehouse_id: $wh.val() || '',
            category_id: $cat.val() || '',
            status: $status.val() || 'all',
            search: $search.val() || ''
        }).toString();
        Swal.fire({
            text: 'Export dalam format Excel?',
            icon: 'question',
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Ya, export',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-primary' }
        }).then(function(result){
            if (result.isConfirmed) {
                window.location = `{{ route('admin.manajemenstok.reorder-point.export-excel') }}?` + params;
            }
        });
    });
    // Recalc ROP handler
    $('#btn-recalc-rop').on('click', function(){
        const $btn = $(this);
        Swal.fire({
            text: 'Hitung ulang metrik ROP untuk seluruh SKU? Proses ini bisa memakan waktu.',
            icon: 'question', showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: 'Ya, proses', cancelButtonText: 'Batal',
            customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' }
        }).then(function(res){
            if (!res.isConfirmed) return;
            $btn.prop('disabled', true).addClass('disabled');
            $.ajax({
                url: `{{ route('admin.manajemenstok.reorder-point.recalc') }}`,
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
                success: function(resp){
                    const c = resp && resp.processed ? resp.processed : 0;
                    Swal.fire('Selesai', `Recalc selesai. Diproses: ${c} pasangan item-gudang.`, 'success');
                    load();
                },
                error: function(xhr){
                    let msg = 'Gagal melakukan recalc.';
                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    Swal.fire('Error', msg, 'error');
                },
                complete: function(){ $btn.prop('disabled', false).removeClass('disabled'); }
            });
        });
    });
    // Toggle button label for metric notes
    const $metricBtn = $('#rp_metric_toggle');
    const $metricNotes = $('#rp_metric_notes');
    $metricNotes.on('shown.bs.collapse', function(){ $metricBtn.text('Sembunyikan'); });
    $metricNotes.on('hidden.bs.collapse', function(){ $metricBtn.text('Tampilkan'); });
    load();
});
</script>
@endpush
