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
                                                <option value="{{ $w->id }}" @if(isset($selectedFrom) && (string)$selectedFrom===(string)$w->id) selected @endif>{{ $w->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="mb-10">
                                        <label class="form-label fs-5 fw-bold mb-3">Gudang Tujuan:</label>
                                        <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" name="to_warehouse_id" id="to_warehouse_filter" data-dropdown-parent="#kt-toolbar-filter">
                                            <option value="">Semua</option>
                                            @foreach(($warehouses ?? []) as $w)
                                                <option value="{{ $w->id }}" @if((isset($selectedTo) && (string)$selectedTo===(string)$w->id) || (isset($userWarehouseId) && $userWarehouseId==$w->id)) selected @endif>{{ $w->name }}</option>
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
                <div class="text-center mb-4 screen-header">
                    <div class="fw-bolder" style="font-size:1.1rem;">Daftar barang belum dikirim</div>
                    <div class="text-muted" id="filters_summary" style="font-size:.9rem;">
                        Gudang Asal: <span id="sum_from">Semua</span> | Gudang Tujuan: <span id="sum_to">Semua</span>
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
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->date)->format('Y-m-d') }}</td>
                                    <td><a href="{{ route('admin.stok-keluar.permintaan-barang.show', $row->tr_id) }}" class="text-primary fw-bold">{{ $row->code }}</a></td>
                                    <td>{{ $row->sku }}</td>
                                    <td>{{ $row->nama_barang }}</td>
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
