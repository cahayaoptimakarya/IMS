@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* Ensure flatpickr calendar overlays inside modal and menus */
        .flatpickr-calendar { z-index: 200000 !important; }
        /* Polish pack modal spacing */
        #packModal .modal-body { padding-top: .75rem; }
        #pm_new_shipment_fields .section-title { font-weight: 600; font-size: .95rem; }

        /* Improve contrast for inputs inside modal */
        #packModal .form-control.form-control-solid,
        #packModal .form-select.form-select-solid {
            background-color: #ffffff !important;
            border: 1px solid #DDE2E8 !important;
            color: #1F2937 !important;
        }
        #packModal .form-control.form-control-solid::placeholder {
            color: #94A3B8 !important;
        }
        #packModal .input-group-text {
            background-color: #F4F6F8 !important;
            border: 1px solid #DDE2E8 !important;
            color: #64748B !important;
        }
        #packModal .form-control:focus, #packModal .form-select:focus {
            background-color: #ffffff !important;
            border-color: #3B82F6 !important;
            box-shadow: 0 0 0 .2rem rgba(59,130,246,.15) !important;
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Permintaan Barang - Belum Dikirim',
        'breadcrumbs' => ['Admin', 'Stok Keluar', 'Permintaan Barang', 'Belum Dikirim'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap mb-4">
                    <form id="filter_form" method="GET" class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center mb-2">
                            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                <span class="svg-icon svg-icon-2"><i class="fas fa-filter"></i></span>
                                Filter
                            </button>
                            <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt-toolbar-filter">
                                <div class="px-7 py-5"><div class="fs-4 text-dark fw-bolder">Filter Options</div></div>
                                <div class="separator border-gray-200"></div>
                                <div class="px-7 py-5">
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Asal:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="from_warehouse_id" id="from_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter" @if($userWarehouseId) disabled @endif>
                                            <option value="">Semua</option>
                                            @foreach(($warehouses ?? []) as $w)
                                                <option value="{{ $w->id }}" @if((isset($selectedFrom) && (string)$selectedFrom===(string)$w->id) || (isset($userWarehouseId) && (string)$userWarehouseId===(string)$w->id)) selected @endif>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Tujuan:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="to_warehouse_id" id="to_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="">Semua</option>
                                            @foreach(($warehouses ?? []) as $w)
                                                <option value="{{ $w->id }}" @if(isset($selectedTo) && (string)$selectedTo===(string)$w->id) selected @endif>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="reset" class="btn btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">Batal</button>
                                        <button type="submit" class="btn btn-primary" id="apply_filter">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <button type="button" id="print_btn" class="btn btn-success" title="Cetak">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Title (screen) -->
                <div class="text-center mb-2 screen-header">
                    <div class="fw-bolder" style="font-size:1.1rem;">Daftar barang belum dikirim</div>
                    <div class="text-muted" id="filters_summary" style="font-size:.9rem;">
                        Gudang Asal: <span id="sum_from">Semua</span> | Gudang Tujuan: <span id="sum_to">Semua</span>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" id="bulk_pack_btn" class="btn btn-primary d-none">
                            <i class="fas fa-box-open me-1"></i> Packing (<span id="bulk_selected_count">0</span>)
                        </button>
                        <small id="bulk_pack_hint" class="text-muted d-none">Pilih item dari Transfer Request yang sama.</small>
                    </div>
                </div>

                <div class="text-center mb-4 print-header" style="display:none;">
                    <div class="fw-bolder doc-title-mini" style="font-size:1.2rem;">Daftar Barang Belum Dikirim</div>
                    <div class="text-muted" id="filters_summary" style="font-size:.9rem;">
                        Gudang Asal: <span id="sum_from">Semua</span> | Gudang Tujuan: <span id="sum_to">Semua</span>
                    </div>
                </div>

                <div class="mb-4" style="max-width:320px;">
                    <input type="text" id="search_input" class="form-control form-control-solid" placeholder="Cari Barang Belum Dikirim" />
                </div>
                <div class="table-responsive">
                    <table id="unshipped_table" class="table align-middle table-row-dashed fs-6 gy-5">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th class="no-print-col" style="width:36px;"><input type="checkbox" id="select_all_rows"></th>
                                <th>Tanggal</th>
                                <th>Kode TR</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Sisa Qty Kirim</th>
                                <th class="text-end">Sisa Koli Kirim</th>
                                <th class="no-print-col">Dari</th>
                                <th class="no-print-col">Ke</th>
                                <th class="no-print-col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                            @forelse(($pendingItems ?? []) as $row)
                                <tr data-tr-id="{{ $row->tr_id }}" data-tr-code="{{ $row->code }}" data-from-id="{{ $row->from_warehouse_id }}" data-to-id="{{ $row->to_warehouse_id }}" data-tri-id="{{ $row->tri_id }}" data-item-id="{{ $row->item_id }}" data-sku="{{ $row->sku }}" data-name="{{ $row->nama_barang }}" data-place="{{ $row->tempat ?? '-' }}" data-ratio="{{ (float)($row->item_koli_ratio ?? 1) }}" data-rem-qty="{{ (int)($row->ship_remaining_quantity ?? 0) }}" data-rem-koli="{{ (float)($row->ship_remaining_koli ?? 0) }}">
                                    <td class="no-print-col"><input type="checkbox" class="row-select"></td>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('Y-m-d') }}</td>
                                    <td><a href="{{ route('admin.stok-keluar.permintaan-barang.show', $row->tr_id) }}" class="text-primary fw-bold">{{ $row->code }}</a></td>
                                    <td>{{ $row->sku }}</td>
                                    <td>{{ $row->nama_barang }} <span class="text-muted">({{ $row->tempat ?? '-' }})</span></td>
                                    <td class="text-end">{{ number_format((int)($row->ship_remaining_quantity ?? 0), 0, ',', '.') }}</td>
                                    <td class="text-end">{{ number_format((float)($row->ship_remaining_koli ?? 0), 2, ',', '.') }}</td>
                                    <td class="no-print-col">{{ $row->from_warehouse_name }}</td>
                                    <td class="no-print-col">{{ $row->to_warehouse_name }}</td>
                                    <td class="no-print-col">
                                        <button type="button"
                                           class="btn btn-sm btn-primary btn-pack"
                                           data-tr-id="{{ $row->tr_id }}"
                                           data-tr-code="{{ $row->code }}"
                                           data-tri-id="{{ $row->tri_id }}"
                                           data-item-id="{{ $row->item_id }}"
                                           data-sku="{{ $row->sku }}"
                                           data-name="{{ $row->nama_barang }}"
                                           data-place="{{ $row->tempat ?? '-' }}"
                                           data-ratio="{{ (float)($row->item_koli_ratio ?? 1) }}"
                                           data-rem-qty="{{ (int)($row->ship_remaining_quantity ?? 0) }}"
                                           data-rem-koli="{{ (float)($row->ship_remaining_koli ?? 0) }}">
                                            Pack
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function(){
        if ($.fn.select2) {
            $('#from_warehouse_filter, #to_warehouse_filter').select2();
        }
        // Update summary on change
        function updateSummary(){
            var fromText = $('#from_warehouse_filter option:selected').text() || 'Semua';
            var toText = $('#to_warehouse_filter option:selected').text() || 'Semua';
            if(!$('#from_warehouse_filter').val()){ fromText = 'Semua'; }
            if(!$('#to_warehouse_filter').val()){ toText = 'Semua'; }
            $('#sum_from').text(fromText);
            $('#sum_to').text(toText);
        }
        updateSummary();
        $('#from_warehouse_filter, #to_warehouse_filter').on('change', updateSummary);

        // Submit filter form
        $('#apply_filter').on('click', function(){ $('#filter_form').submit(); });

        // Client-side search (filter rows)
        $('#search_input').on('input', function(){
            var q = ($(this).val() || '').toString().toLowerCase().trim();
            var $rows = $('#unshipped_table tbody tr');
            if (!q) { $rows.show(); return; }
            $rows.each(function(){
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(q) !== -1);
            });
        });

        // Simple print
        $('#print_btn').on('click', function(){ window.print(); });

        // Bulk selection handling
        function refreshBulkUI(){
            const $rows = $('#unshipped_table tbody tr');
            const selected = $rows.find('input.row-select:checked').closest('tr');
            const count = selected.length;
            if (count === 0){
                $('#bulk_pack_btn').addClass('d-none');
                $('#bulk_pack_hint').addClass('d-none');
                $('#bulk_selected_count').text('0');
                return;
            }
            $('#bulk_selected_count').text(count);
            // Multi TR didukung: cukup tampilkan tombol aktif jika ada pilihan
            $('#bulk_pack_btn').removeClass('d-none').prop('disabled', false);
            $('#bulk_pack_hint').addClass('d-none');
        }
        $('#select_all_rows').on('change', function(){
            const checked = $(this).is(':checked');
            $('#unshipped_table tbody input.row-select').prop('checked', checked);
            refreshBulkUI();
        });
        $(document).on('change', '#unshipped_table tbody input.row-select', function(){
            if (!$(this).is(':checked')) $('#select_all_rows').prop('checked', false);
            refreshBulkUI();
        });

        // Bulk packing modal open
        $('#bulk_pack_btn').on('click', function(){
            const selected = $('#unshipped_table tbody tr').has('input.row-select:checked');
            if (selected.length === 0) return;
            const trId = selected.first().data('tr-id');
            const trCode = selected.first().data('tr-code');
            BulkPack.open(trId, trCode, selected);
        });
    });
</script>
<style>
@media print {
    body * { visibility: hidden; }
    #kt_content, #kt_content * { visibility: visible; }
    #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
    #filter_form, #apply_filter, #print_btn, #search_input { display: none !important; }
    .dataTables_length, .dataTables_filter, .dataTables_paginate, .dataTables_info { display: none !important; }
    .card-body { padding: 0 !important; }
    .print-header { display: block !important; margin-bottom: 8px; }
    #unshipped_table { border-collapse: collapse !important; width: 100%; }
    #unshipped_table thead th, #unshipped_table tbody td { border: 1px solid #000 !important; padding: 4px 6px !important; }
    #unshipped_table thead th { background: #fff !important; color: #000 !important; font-weight: 700 !important; }
    #unshipped_table thead { display: table-header-group; }
    #unshipped_table tfoot { display: table-footer-group; }
    html, body { font-size: 11pt; }
    .screen-header { display: none !important; }
    /* Hide certain columns in print */
    #unshipped_table th.no-print-col, #unshipped_table td.no-print-col { display: none !important; }
}

/* Screen table polishing for document look */
#unshipped_table thead th, #unshipped_table tbody td { border-bottom: 1px solid #e5e7eb; }
.doc-title-mini { font-weight: 800; letter-spacing: .4px; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@endpush

@push('scripts')
<!-- Bulk Pack Modal (single shipment) -->
<div class="modal fade" id="bulkPackModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Packing Massal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 align-items-end mb-3">
          <div class="col-md-6">
            <label class="form-label">Pilih Shipment (Packing)</label>
            <select id="bp_shipment" class="form-select form-select-solid"><option value="">Memuat...</option></select>
          </div>
          <div class="col-md-6 text-md-end">
            <button type="button" class="btn btn-light-primary btn-sm mt-3 mt-md-0" id="bp_fill_all">
              Isi Maks Semua
            </button>
          </div>
        </div>
        <div id="bp_new_shipment_fields" class="mt-2" style="display:none;">
          <div class="rounded border p-3 bg-light">
            <div class="section-title text-muted mb-3">Buat Shipment Baru</div>
            <div class="row g-3">
              <div class="col-md-4"><label class="form-label">Tanggal Kirim</label><div class="input-group"><span class="input-group-text"><i class="fas fa-calendar-alt"></i></span><input type="text" id="bp_ship_date" class="form-control form-control-solid" placeholder="Pilih tanggal" autocomplete="off"></div></div>
              <div class="col-md-4"><label class="form-label">Tipe Kendaraan</label><div class="input-group"><span class="input-group-text"><i class="fas fa-truck"></i></span><input type="text" id="bp_vehicle_type" class="form-control form-control-solid" placeholder="Jenis kendaraan"></div></div>
              <div class="col-md-4"><label class="form-label">No. Polisi</label><div class="input-group"><span class="input-group-text"><i class="fas fa-id-card"></i></span><input type="text" id="bp_license" class="form-control form-control-solid" placeholder="B 1234 XX"></div></div>
              <div class="col-md-6"><label class="form-label">Driver</label><div class="input-group"><span class="input-group-text"><i class="fas fa-user"></i></span><input type="text" id="bp_driver" class="form-control form-control-solid" placeholder="Nama driver"></div></div>
              <div class="col-md-6"><label class="form-label">Kontak Driver (opsional)</label><div class="input-group"><span class="input-group-text"><i class="fas fa-phone"></i></span><input type="text" id="bp_driver_contact" class="form-control form-control-solid" placeholder="No. HP / kontak"></div></div>
              <div class="col-12"><label class="form-label">Keterangan</label><input type="text" id="bp_desc" class="form-control form-control-solid" placeholder="Catatan (opsional)"></div>
            </div>
          </div>
        </div>
        <div class="table-responsive mt-4">
          <table class="table align-middle table-row-dashed fs-6" id="bp_items_table">
            <thead>
              <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                <th>Kode TR</th>
                <th>SKU</th>
                <th>Nama Item</th>
                <th class="text-end">Qty Kirim</th>
                <th class="text-end">Koli Kirim</th>
                <th>Catatan</th>
                <th class="text-center">Max</th>
                <th class="text-center">Hapus</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="bp_save">Simpan</button>
      </div>
    </div>
  </div>
</div>

<script>
  const BulkPack = (function(){
    let modalEl = document.getElementById('bulkPackModal');
    let bsModal = null;
    let rows = [];
    let firstTrId = null; let fromToKey = null;
    function show(){ if (!bsModal) bsModal = new bootstrap.Modal(modalEl); bsModal.show(); }
    function warn(msg){ if (window.toastr) toastr.error(msg); else alert(msg); }
    function open(_trId, _trCode, $selectedRows){
      // Build flat rows and validate same from/to
      rows = [];
      const fromToSet = new Set();
      let fromWarehouseId = null;
      $selectedRows.each(function(){
        const $r = $(this);
        const trId = $r.data('tr-id');
        const trCode = $r.data('tr-code');
        const fromId = $r.data('from-id');
        const toId = $r.data('to-id');
        fromToSet.add(`${fromId}:${toId}`);
        if (fromWarehouseId === null) fromWarehouseId = fromId;
        rows.push({ trId, trCode, triId: $r.data('tri-id'), itemId: $r.data('item-id'), sku: $r.data('sku'), name: $r.data('name'), place: ($r.data('place')||'-'), ratio: parseFloat($r.data('ratio')||1), remQty: parseInt($r.data('rem-qty')||0), remKoli: parseFloat($r.data('rem-koli')||0) });
      });
      if (fromToSet.size > 1){ return warn('Pilih item dengan gudang asal/tujuan yang sama untuk digabung ke satu shipment.'); }
      fromToKey = Array.from(fromToSet)[0] || null;
      firstTrId = rows[0]?.trId || null;
      // Render items table
      const tb = rows.map(r => `
        <tr data-tr-id="${r.trId}" data-tri-id="${r.triId}" data-item-id="${r.itemId}" data-ratio="${r.ratio}" data-rem-qty="${r.remQty}" data-rem-koli="${r.remKoli}" data-stock-qty="0" data-stock-koli="0">
          <td>${r.trCode}</td>
          <td>${r.sku}</td>
          <td>
            <div class="fw-bold">${r.name} <span class="text-muted">(${r.place||'-'})</span></div>
            <div class="small lh-sm mt-1">
              <div class="mb-1">
                <span class="badge badge-light-primary text-primary">Sisa Diminta: Qty <span class="bp-rem-qty">${r.remQty.toLocaleString('id-ID')}</span> | Koli <span class="bp-rem-koli">${r.remKoli.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2})}</span></span>
              </div>
              <div class="text-muted">Stok Gudang: Qty <span class="bp-stock-qty">-</span> | Koli <span class="bp-stock-koli">-</span></div>
            </div>
          </td>
          <td class="text-end"><input type="number" min="0" step="1" class="form-control form-control-sm bp-qty" style="max-width:120px; margin-left:auto;"></td>
          <td class="text-end"><input type="number" min="0" step="0.01" class="form-control form-control-sm bp-koli" style="max-width:120px; margin-left:auto;"></td>
          <td><input type="text" class="form-control form-control-sm bp-note" maxlength="255" placeholder="Catatan"></td>
          <td class="text-center"><button type="button" class="btn btn-sm btn-light bp-max">Max</button></td>
          <td class="text-center">
            <button type="button" class="btn btn-icon btn-sm btn-light-danger bp-remove" title="Hapus SKU">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>`).join('');
      $('#bp_items_table tbody').html(tb);

      // Load inventory stock for origin warehouse and selected items
      try {
        const itemIds = rows.map(r => r.itemId);
        $.get(`{{ route('admin.stok-keluar.permintaan-barang.inventory-stock') }}`,
          { warehouse_id: fromWarehouseId, item_ids: itemIds },
          function(map){
            $('#bp_items_table tbody tr').each(function(){
              const $tr = $(this);
              const itemId = $tr.data('item-id');
              const info = map && map[itemId] ? map[itemId] : { quantity: 0, koli: 0 };
              const q = parseInt(info.quantity||0);
              const k = parseFloat(info.koli||0);
              $tr.attr('data-stock-qty', q);
              $tr.attr('data-stock-koli', k);
              $tr.find('.bp-stock-qty').text(q.toLocaleString('id-ID'));
              $tr.find('.bp-stock-koli').text(k.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}));
            });
          }
        );
      } catch(e) { /* ignore */ }
      // Load packing shipments based on first TR
      $('#bp_shipment').html('<option value="">Memuat...</option>');
      $.get(`{{ url('admin/stok-keluar/permintaan-barang') }}/${firstTrId}/packing-shipments`, function(list){
        let html = '<option value="">Pilih Shipment</option>';
        html += '<option value="new">+ Buat Shipment Baru</option>';
        if (Array.isArray(list)){
          list.forEach(function(s){
            const label = (s.code||'-') + ' - ' + (s.shipping_date||'');
            html += `<option value="${s.id}" data-date="${s.shipping_date||''}" data-vehicle="${s.vehicle_type||''}" data-license="${s.license_plate||''}" data-driver-name="${s.driver_name||''}" data-driver-contact="${s.driver_contact||''}" data-desc="${s.description||''}">${label}</option>`;
          });
        }
        $('#bp_shipment').html(html);
      });
      show();
    }
    $(document).on('change', '#bp_shipment', function(){
      const val=$(this).val();
      if (val==='new') {
        $('#bp_new_shipment_fields').slideDown(120);
        // Init flatpickr for bulk pack date field
        const input = document.getElementById('bp_ship_date');
        if (input) {
          try { if (input._flatpickr) { input._flatpickr.destroy(); } } catch(e){}
          const fp = flatpickr(input, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M Y',
            allowInput: true,
            disableMobile: true,
            appendTo: document.body
          });
          if (fp && (fp.altInput || fp._input)) {
            fp.set('positionElement', fp.altInput || fp._input);
          }
        }
      } else {
        $('#bp_new_shipment_fields').slideUp(120);
      }
    });
    function clamp(val, min, max){ val = isNaN(val)?0:val; if (typeof max==='number') val = Math.min(val, max); if (typeof min==='number') val = Math.max(val, min); return val; }
    $(document).on('input','.bp-qty', function(){
      const $tr=$(this).closest('tr');
      const ratio=parseFloat($tr.data('ratio')||1);
      const remQty=parseInt($tr.data('rem-qty')||0);
      const stockQty=parseInt($tr.data('stock-qty')||0);
      const maxQty = Math.min(remQty, stockQty);
      let qty=parseInt($(this).val()||0);
      if (qty > maxQty) {
        qty = maxQty;
        if (window.Swal) {
          const stokQtyTxt = (stockQty||0).toLocaleString('id-ID');
          Swal.fire(
            'Periksa Data',
            `Input Qty tidak dapat diproses. Tolong cek kembali inputan.\n\nData referensi saat ini:\n- Stok (Qty): ${stokQtyTxt}`,
            'warning'
          );
        }
      }
      qty=clamp(qty,0,maxQty);
      $(this).val(qty);
      if(ratio>0){
        let koli=qty/ratio;
        const remKoli=parseFloat($tr.data('rem-koli')||0);
        const stockKoli=parseFloat($tr.data('stock-koli')||0);
        const maxKoli = Math.min(remKoli, stockKoli);
        if (koli > maxKoli) {
          koli = maxKoli;
          if (window.Swal) {
            const stokKoliTxt = (stockKoli||0).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2});
            Swal.fire(
              'Periksa Data',
              `Input Koli tidak dapat diproses. Tolong cek kembali inputan.\n\nData referensi saat ini:\n- Stok (Koli): ${stokKoliTxt}`,
              'warning'
            );
          }
        }
        koli=clamp(koli,0,maxKoli);
        $tr.find('.bp-koli').val(koli.toFixed(2));
      }
    });
    $(document).on('input','.bp-koli', function(){
      const $tr=$(this).closest('tr');
      const ratio=parseFloat($tr.data('ratio')||1);
      const remKoli=parseFloat($tr.data('rem-koli')||0);
      const stockKoli=parseFloat($tr.data('stock-koli')||0);
      const maxKoli = Math.min(remKoli, stockKoli);
      let koli=parseFloat($(this).val()||0);
      if (koli > maxKoli) {
        koli = maxKoli;
        if (window.Swal) {
          const stokKoliTxt = (stockKoli||0).toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2});
          Swal.fire(
            'Periksa Data',
            `Input Koli tidak dapat diproses. Tolong cek kembali inputan.\n\nData referensi saat ini:\n- Stok (Koli): ${stokKoliTxt}`,
            'warning'
          );
        }
      }
      koli=clamp(koli,0,maxKoli);
      $(this).val(koli);
      if(ratio>0){
        let qty=koli*ratio;
        const remQty=parseInt($tr.data('rem-qty')||0);
        const stockQty=parseInt($tr.data('stock-qty')||0);
        const maxQty = Math.min(remQty, stockQty);
        if (qty > maxQty) qty = maxQty;
        qty=clamp(qty,0,maxQty);
        $tr.find('.bp-qty').val(Math.round(qty));
      }
    });
    // Hitung isi maksimal untuk satu baris
    function fillRowMax($tr){
      const ratio = parseFloat($tr.data('ratio')||1);
      const remQty = parseInt($tr.data('rem-qty')||0);
      const remKoli = parseFloat($tr.data('rem-koli')||0);
      const stockQty = parseInt($tr.data('stock-qty')||0);
      const stockKoli = parseFloat($tr.data('stock-koli')||0);
      let maxQtyByKoli = ratio > 0 ? Math.floor(Math.min(remKoli, stockKoli) * ratio) : Math.min(remQty, stockQty);
      let qtyFinal = Math.min(remQty, stockQty, maxQtyByKoli);
      qtyFinal = isFinite(qtyFinal) ? qtyFinal : 0;
      let koliFinal = ratio > 0 ? (qtyFinal / ratio) : Math.min(remKoli, stockKoli);
      koliFinal = Math.min(koliFinal, remKoli, stockKoli);
      $tr.find('.bp-qty').val(qtyFinal);
      $tr.find('.bp-koli').val((koliFinal||0).toFixed(2));
    }
    // Max button per baris: isi qty/koli ke maksimum yang konsisten dengan ratio dan batas sisa
    $(document).on('click', '.bp-max', function(){
      fillRowMax($(this).closest('tr'));
    });
    // Isi maksimal untuk seluruh SKU sekaligus
    $(document).on('click', '#bp_fill_all', function(){
      $('#bp_items_table tbody tr').each(function(){
        fillRowMax($(this));
      });
    });
    // Hapus baris SKU dari packing massal
    $(document).on('click', '.bp-remove', function(){
      const $tr = $(this).closest('tr');
      $tr.remove();
      if (!$('#bp_items_table tbody tr').length){
        warn('Tidak ada SKU tersisa di packing massal.');
      }
    });

    $(document).on('click','#bp_save', function(){
      const shipSel = $('#bp_shipment').val();
      if (!shipSel) return warn('Pilih shipment (packing) atau buat baru.');
      if (!$('#bp_items_table tbody tr').length) return warn('Tidak ada SKU di packing massal.');
      const csrf = $('meta[name=csrf-token]').attr('content');
      // Build items per TR
      const itemsByTr = {};
      let any=false;
      $('#bp_items_table tbody tr').each(function(){
        const trId = $(this).data('tr-id');
        const triId=$(this).data('tri-id');
        const itemId=$(this).data('item-id');
        const qty=parseInt($(this).find('.bp-qty').val()||0);
        const koli=parseFloat($(this).find('.bp-koli').val()||0);
        const note=($(this).find('.bp-note').val()||'').toString();
        if (qty>0 || koli>0){
          if(!itemsByTr[trId]) itemsByTr[trId]={};
          itemsByTr[trId][triId] = { item_id:itemId, quantity:qty, koli:koli, description: note };
          any=true;
        }
      });
      if (!any) return warn('Isi qty/koli untuk minimal satu item.');

      function submitWithShipmentId(shipmentId, headerData){
        const reqs=[]; Object.entries(itemsByTr).forEach(([trId, items])=>{
          const payload = Object.assign({ shipment_id: shipmentId, items }, headerData||{});
          reqs.push($.ajax({ url: `{{ url('admin/stok-keluar/permintaan-barang') }}/${trId}/create-shipment`, type:'POST', headers:{'X-CSRF-TOKEN': csrf }, data: payload }));
        });
        $.when.apply($, reqs).done(function(){ Swal.fire('Berhasil','Packing massal tersimpan.','success').then(()=>{ location.reload(); }); }).fail(function(xhr){ let msg='Gagal menyimpan.'; if (xhr?.responseJSON?.message) msg=xhr.responseJSON.message; Swal.fire('Error', msg, 'error'); });
      }

      const runSave = function(){
        if (shipSel === 'new'){
          // Create once on first TR, then reuse latest shipment for same route
          if (!$('#bp_ship_date').val() || !$('#bp_vehicle_type').val() || !$('#bp_license').val()) return warn('Isi Tanggal, Tipe Kendaraan, dan No. Polisi.');
          const header = { shipping_date: $('#bp_ship_date').val(), vehicle_type: $('#bp_vehicle_type').val(), license_plate: $('#bp_license').val(), driver_name: $('#bp_driver').val()||'', driver_contact: $('#bp_driver_contact').val()||'', description: $('#bp_desc').val()||'' };
          // Create with first TR, with its subset items
          const firstItems = itemsByTr[firstTrId] || {};
          if (Object.keys(firstItems).length===0) return warn('Isi qty/koli pada salah satu item dari TR pertama.');
          const payloadFirst = Object.assign({ shipment_id: null, items: firstItems }, header);
          $.ajax({ url: `{{ url('admin/stok-keluar/permintaan-barang') }}/${firstTrId}/create-shipment`, type: 'POST', headers:{'X-CSRF-TOKEN': csrf}, data: payloadFirst })
            .done(function(resp){
              // Prefer shipment_id returned by backend to avoid ambiguity by date
              let reuseId = resp && resp.shipment_id ? resp.shipment_id : null;
              const proceedWithId = function(id){
                // Remove firstTr from itemsByTr for remaining
                delete itemsByTr[firstTrId];
                if (Object.keys(itemsByTr).length===0){ Swal.fire('Berhasil','Packing tersimpan.','success').then(()=>{ location.reload(); }); return; }
                // Reuse same header data so validation terpenuhi
                submitWithShipmentId(id, header);
              };
              if (reuseId){ return proceedWithId(reuseId); }
              // Fallback: Fetch packing shipments and pick the newest by created order if available
              $.get(`{{ url('admin/stok-keluar/permintaan-barang') }}/${firstTrId}/packing-shipments`, function(list){
                if (!Array.isArray(list) || list.length===0) { return Swal.fire('Error','Tidak dapat menemukan shipment baru.','error'); }
                const chosen = list[0];
                proceedWithId(chosen.id);
              }).fail(function(){ Swal.fire('Error','Gagal mengambil data shipment.','error'); });
            })
            .fail(function(xhr){ let msg='Gagal membuat shipment baru.'; if (xhr?.responseJSON?.message) msg=xhr.responseJSON.message; Swal.fire('Error', msg, 'error'); });
        } else {
          // Existing shipment id
          const opt = $('#bp_shipment option:selected');
          const header = {
            shipping_date: opt.data('date')||'',
            vehicle_type: opt.data('vehicle')||'',
            license_plate: opt.data('license')||'',
            driver_name: opt.data('driver-name')||'',
            driver_contact: opt.data('driver-contact')||'',
            description: opt.data('desc')||''
          };
          submitWithShipmentId($('#bp_shipment').val(), header);
        }
      };

      // Konfirmasi SweetAlert sebelum submit
      if (window.Swal){
        Swal.fire({ text:'Apakah Anda yakin ingin menyimpan packing massal?', icon:'question', showCancelButton:true, confirmButtonText:'Ya, Simpan', cancelButtonText:'Batal', buttonsStyling:false, customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'} }).then(function(res){ if(res.isConfirmed){ runSave(); } });
      } else {
        if (confirm('Apakah Anda yakin ingin menyimpan packing massal?')) runSave();
      }
    });
    return { open };
  })();
</script>
@endpush

@push('scripts')
<div class="modal fade" id="packModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Pack Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="mb-2"><span class="text-muted">Kode TR</span><div id="pm_tr_code" class="fw-bolder"></div></div>
            <div class="mb-2"><span class="text-muted">SKU</span><div id="pm_sku" class="fw-bolder"></div></div>
            <div class="mb-2"><span class="text-muted">Nama Item</span><div id="pm_name" class="fw-bolder"></div></div>
          </div>
          <div class="col-md-6">
            <div class="mb-2"><span class="text-muted">Sisa Qty Kirim</span><div id="pm_rem_qty" class="fw-bolder"></div></div>
            <div class="mb-2"><span class="text-muted">Sisa Koli Kirim</span><div id="pm_rem_koli" class="fw-bolder"></div></div>
          </div>
        </div>
        <hr/>
        <div class="row g-4 align-items-end">
          <div class="col-md-4 col-sm-6">
            <label class="form-label">Qty</label>
            <input type="number" min="0" step="1" id="pm_qty" class="form-control form-control-solid" value="0">
          </div>
          <div class="col-md-4 col-sm-6">
            <label class="form-label">Koli</label>
            <input type="number" min="0" step="0.01" id="pm_koli" class="form-control form-control-solid" value="0">
          </div>
          <div class="col-md-4 col-12">
            <label class="form-label">Shipment (Packing)</label>
            <select id="pm_shipment" class="form-select form-select-solid">
              <option value="">Memuat...</option>
            </select>
          </div>
        </div>
        <div id="pm_new_shipment_fields" class="mt-4" style="display:none;">
          <div class="rounded border p-3 bg-light">
            <div class="section-title text-muted mb-3">Buat Shipment Baru</div>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Tanggal Kirim</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                  <input type="text" id="pm_ship_date" class="form-control form-control-solid" placeholder="Pilih tanggal" autocomplete="off" />
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Tipe Kendaraan</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-truck"></i></span>
                  <input type="text" id="pm_vehicle_type" class="form-control form-control-solid" placeholder="Jenis kendaraan" />
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">No. Polisi</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                  <input type="text" id="pm_license" class="form-control form-control-solid" placeholder="B 1234 XX" />
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Driver</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-user"></i></span>
                  <input type="text" id="pm_driver" class="form-control form-control-solid" placeholder="Nama driver" />
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Kontak Driver (opsional)</label>
                <div class="input-group">
                  <span class="input-group-text"><i class="fas fa-phone"></i></span>
                  <input type="text" id="pm_driver_contact" class="form-control form-control-solid" placeholder="No. HP / kontak" />
                </div>
              </div>
              <div class="col-12">
                <label class="form-label">Keterangan</label>
                <input type="text" id="pm_desc" class="form-control form-control-solid" placeholder="Catatan (opsional)" />
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
        <button type="button" class="btn btn-primary" id="pm_save">Simpan</button>
      </div>
    </div>
  </div>
  </div>
<script>
  (function(){
    let modalEl = document.getElementById('packModal');
    let bsModal = null;
    function showModal(){
      if (!bsModal) bsModal = new bootstrap.Modal(modalEl);
      bsModal.show();
    }

    let ctx = {};
    $(document).on('click','.btn-pack', function(){
      ctx = {
        trId: $(this).data('tr-id'),
        trCode: $(this).data('tr-code'),
        triId: $(this).data('tri-id'),
        itemId: $(this).data('item-id'),
        sku: $(this).data('sku'),
        name: $(this).data('name'),
        ratio: parseFloat($(this).data('ratio')||1),
        remQty: parseInt($(this).data('rem-qty')||0),
        remKoli: parseFloat($(this).data('rem-koli')||0)
      };
      $('#pm_tr_code').text(ctx.trCode);
      $('#pm_sku').text(ctx.sku);
      $('#pm_name').text(ctx.name);
      $('#pm_rem_qty').text(ctx.remQty.toLocaleString('id-ID'));
      $('#pm_rem_koli').text(ctx.remKoli.toLocaleString('id-ID', {minimumFractionDigits:2, maximumFractionDigits:2}));
      $('#pm_qty').val(0);
      $('#pm_koli').val(0);
      $('#pm_shipment').html('<option value="">Memuat...</option>');

      // Load packing shipments for this TR
      $.get("{{ url('admin/stok-keluar/permintaan-barang') }}/"+ctx.trId+"/packing-shipments", function(list){
        let html = '<option value="">Pilih Shipment</option>';
        html += '<option value="new">+ Buat Shipment Baru</option>';
        if (!list || !list.length){ $('#pm_shipment').html(html); return; }
        list.forEach(function(s){
          let label = (s.code||'-') + ' - ' + (s.shipping_date||'');
          // Store header info as data- attributes for reuse when posting
          html += '<option '
            + 'value="'+s.id+'" '
            + 'data-date="'+(s.shipping_date||'')+'" '
            + 'data-vehicle="'+(s.vehicle_type||'')+'" '
            + 'data-license="'+(s.license_plate||'')+'" '
            + 'data-driver-name="'+(s.driver_name||'')+'" '
            + 'data-driver-contact="'+(s.driver_contact||'')+'" '
            + 'data-desc="'+(s.description||'')+'"'
            + '>' + label + '</option>';
        });
        $('#pm_shipment').html(html);
      }).fail(function(){ $('#pm_shipment').html('<option value="">Gagal memuat</option>'); });

      showModal();
    });

    function warn(msg){ if (window.Swal){ Swal.fire('Validasi', msg, 'warning'); } else { alert(msg); } }

    // Toggle new shipment fields
    $(document).on('change', '#pm_shipment', function(){
      if ($(this).val()==='new') {
        $('#pm_new_shipment_fields').slideDown(120, function(){
          const input = modalEl.querySelector('#pm_ship_date');
          if (input) {
            input.focus();
            if (window.flatpickr && input._flatpickr) {
              const fp = input._flatpickr;
              if (fp.altInput || fp._input) { fp.set('positionElement', fp.altInput || fp._input); }
              fp.open();
            }
          }
        });
      } else {
        $('#pm_new_shipment_fields').slideUp(120);
      }
    });

    // Init flatpickr when modal is shown to avoid hidden container issues
    modalEl.addEventListener('shown.bs.modal', function(){
      if (!window.flatpickr) return;
      const input = modalEl.querySelector('#pm_ship_date');
      if (!input) return;
      if (input._flatpickr) { input._flatpickr.destroy(); }
      const fp = flatpickr(input, {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M Y',
        allowInput: true,
        disableMobile: true,
        appendTo: document.body
      });
      if (fp && (fp.altInput || fp._input)) {
        fp.set('positionElement', fp.altInput || fp._input);
      }
    });

    // Auto conversion between qty and koli
    let isCalc = false;
    function clamp(val, min, max){ val = isNaN(val)?0:val; if (typeof max==='number') val = Math.min(val, max); if (typeof min==='number') val = Math.max(val, min); return val; }
    $('#pm_qty').on('input', function(){
      if (isCalc) return; isCalc = true;
      let qty = parseInt($(this).val()||0);
      qty = clamp(qty, 0, ctx.remQty);
      $(this).val(qty);
      if (ctx.ratio>0){ let koli = qty/ctx.ratio; koli = clamp(koli, 0, ctx.remKoli); $('#pm_koli').val(koli.toFixed(2)); }
      isCalc = false;
    });
    $('#pm_koli').on('input', function(){
      if (isCalc) return; isCalc = true;
      let koli = parseFloat($(this).val()||0);
      koli = clamp(koli, 0, ctx.remKoli);
      $(this).val(koli);
      if (ctx.ratio>0){ let qty = koli*ctx.ratio; qty = clamp(qty, 0, ctx.remQty); $('#pm_qty').val(Math.round(qty)); }
      isCalc = false;
    });

    $('#pm_save').on('click', function(){
      let qty = parseInt($('#pm_qty').val()||0);
      let koli = parseFloat($('#pm_koli').val()||0);
      if (qty > ctx.remQty){ return warn('Qty tidak boleh melebihi sisa kirim ('+ctx.remQty+').'); }
      if (koli > ctx.remKoli){ return warn('Koli tidak boleh melebihi sisa kirim ('+ctx.remKoli+').'); }
      const shipSel = $('#pm_shipment').val();
      if (!shipSel) { return warn('Pilih shipment (status packing) terlebih dahulu.'); }
      if (shipSel==='new') {
        // basic required for new shipment
        if (!$('#pm_ship_date').val() || !$('#pm_vehicle_type').val() || !$('#pm_license').val()) {
          return warn('Isi Tanggal, Tipe Kendaraan, dan No. Polisi untuk shipment baru.');
        }
      }
      // Konfirmasi
      if (window.Swal){
        Swal.fire({
          text: 'Apakah Anda yakin ingin menyimpan pack ini?',
          icon: 'question', showCancelButton: true,
          confirmButtonText: 'Ya, Simpan', cancelButtonText: 'Batal',
          buttonsStyling: false,
          customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' }
        }).then(function(res){
          if(res.isConfirmed){
            // Build payload for backend
            let payload = {
              shipping_date: '', vehicle_type: '', license_plate: '', driver_name: '', driver_contact: '', description: '',
              shipment_id: null,
              items: {}
            };
            if (shipSel==='new') {
              payload.shipping_date = $('#pm_ship_date').val();
              payload.vehicle_type = $('#pm_vehicle_type').val();
              payload.license_plate = $('#pm_license').val();
              payload.driver_name = $('#pm_driver').val();
              payload.driver_contact = $('#pm_driver_contact').val() || '';
              payload.description = $('#pm_desc').val();
            } else {
              // Read from selected option data-*
              const opt = $('#pm_shipment option:selected');
              payload.shipment_id = shipSel;
              payload.shipping_date = opt.data('date') || '';
              payload.vehicle_type = opt.data('vehicle') || '';
              payload.license_plate = opt.data('license') || '';
              payload.driver_name = opt.data('driver-name') || '';
              payload.driver_contact = opt.data('driver-contact') || '';
              payload.description = opt.data('desc') || '';
            }
            // items payload expects associative array keyed by id; we can use itemId as key
            payload.items[ctx.triId] = { item_id: ctx.itemId, quantity: qty, koli: koli };

            // POST to create-shipment (append to packing or create new)
            $.ajax({
              url: "{{ url('admin/stok-keluar/permintaan-barang') }}/"+ctx.trId+"/create-shipment",
              type: 'POST',
              headers: { 'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content') },
              data: payload,
              success: function(){
                if (window.Swal){ Swal.fire('Berhasil','Item berhasil dipack.','success').then(()=>{ location.reload(); }); }
                else { alert('Berhasil'); location.reload(); }
              },
              error: function(xhr){
                let msg = 'Gagal menyimpan.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                if (window.Swal){ Swal.fire('Error', msg, 'error'); } else { alert(msg); }
              }
            });
          }
        });
      } else {
        if (confirm('Apakah Anda yakin ingin menyimpan pack ini?')) alert('Data pack tervalidasi.');
      }
    });
  })();
</script>
@endpush
