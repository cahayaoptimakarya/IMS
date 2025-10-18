@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Barang Rusak',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Barang Rusak'],
    ])
@endpush

@push('styles')
    <style>
        .status-card-icon .symbol-label {
            background: #F3E8FF;
        }

        .status-card-icon i {
            color: #7C3AED;
        }

        .status-metrics {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .status-metrics .metric {
            text-align: center;
            min-width: 88px;
        }

        .status-metrics .chip {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1;
        }

        .chip-warning {
            background: #FFF7E6;
            color: #F59E0B;
        }

        .chip-primary {
            background: #E6F0FF;
            color: #3B82F6;
        }

        .chip-success {
            background: #ECFDF5;
            color: #10B981;
        }

        .chip-danger {
            background: #FEE2E2;
            color: #EF4444;
        }

        @media (max-width: 576px) {
            .status-metrics {
                justify-content: flex-start;
            }

            .status-metrics .metric {
                min-width: 70px;
            }
        }
    </style>
@endpush
@php
    $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
    $canCreate = $permissionResolver->userCan('create');
    $canEdit = $permissionResolver->userCan('edit');
    $canApprove = $permissionResolver->userCan('approve');
@endphp

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card card-xl-stretch mb-xl-8">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center mb-4 mb-md-0">
                        <div class="symbol symbol-50px me-5 status-card-icon">
                            <div class="symbol-label">
                                <i class="fas fa-dumpster-fire fs-2x"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-dark fw-bolder fs-5 mb-1">Status Barang Rusak</div>
                            <div class="text-muted fw-bold">Dokumen Defect</div>
                        </div>
                    </div>
                    <div class="status-metrics">
                        <div class="metric">
                            <div class="chip chip-warning" id="status_pending">0</div>
                            <div class="text-muted fs-8 mt-1">Pending</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-success" id="status_approved">0</div>
                            <div class="text-muted fs-8 mt-1">Approved</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-danger" id="status_cancelled">0</div>
                            <div class="text-muted fs-8 mt-1">Cancelled</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="separator border-gray-200"></div>
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1"
                                    transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path
                                    d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z"
                                    fill="black"></path>
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Data...">
                    </div>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex justify-content-end">
                        @if ($canCreate)
                            <a href="{{ route('admin.manajemenstok.defects.create') }}" class="btn btn-primary">Tambah
                                Data</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="d-flex align-items-center justify-content-between mb-5">
                    <div class="d-flex align-items-center gap-3">
                        <select id="status_filter" class="form-select form-select-solid" style="width:220px">
                            <option value="semua">Semua Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input class="form-control form-control-solid" placeholder="Pilih Tanggal" id="date_filter"
                            style="width:220px" />
                    </div>
                </div>
                <div class="table-responsive min-h-500px">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="defects-table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode</th>
                                <th>Tanggal</th>
                                @if (is_null(auth()->user()->warehouse_id))
                                    <th>Gudang</th>
                                @endif
                                <th>Barang</th>
                                <th>Jumlah</th>
                                <th>Koli</th>
                                <th>Status</th>
                                <th>Action</th>
                                <th>Action Status</th>
                                <th>Dicatat Oleh</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fw-bold text-gray-600">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // load status counts
            function loadStatusMetrics() {
                $.get("{{ route('admin.manajemenstok.defects.status-counts') }}", function(res) {
                    $('#status_pending').text(res.pending ?? 0);
                    $('#status_approved').text(res.approved ?? 0);
                    $('#status_cancelled').text(res.cancelled ?? 0);
                });
            }
            loadStatusMetrics();

            // date picker
            if (window.flatpickr) {
                flatpickr('#date_filter', {
                    dateFormat: 'Y-m-d',
                    mode: 'range'
                });
            }

            let showWarehouse = {{ auth()->user()->warehouse_id === null ? 'true' : 'false' }};
            let columns = [{
                    data: 'code',
                    name: 'code'
                },
                {
                    data: 'date',
                    name: 'date'
                },
            ];
            if (showWarehouse) {
                columns.push({
                    data: 'warehouse.name',
                    name: 'warehouse.name'
                });
            }
            columns = columns.concat([{
                    data: 'item.nama_barang',
                    name: 'item.nama_barang',
                    render: function(data, type, row) {
                        try {
                            return `${row.item.nama_barang} (${row.item.sku})`;
                        } catch (e) {
                            return data || '';
                        }
                    }
                },
                {
                    data: 'quantity',
                    name: 'quantity',
                    className: 'text-end'
                },
                {
                    data: 'koli',
                    name: 'koli',
                    className: 'text-end'
                },
                {
                    data: 'status',
                    name: 'status',
                    render: function(data) {
                        if (data === 'pending')
                        return '<span class="badge badge-light-warning">Pending</span>';
                        if (data === 'approved')
                        return '<span class="badge badge-light-success">Approved</span>';
                        if (data === 'cancelled')
                        return '<span class="badge badge-light-danger">Cancelled</span>';
                        return data || '';
                    }
                },
                {
                    data: 'action',
                    name: 'action',
                    render: function(data) {
                        if (!data) return '<span class="badge badge-light">-</span>';
                        const map={
                            disposal:'Disposal',
                            return_to_vendor:'Return to Vendor',
                            rework:'Rework',
                            return_to_stock:'Return to Stock',
                            other:'Other'
                        };
                        return `<span class="badge badge-light-primary">${map[data]||data}</span>`;
                    }
                },
                {
                    data: 'action_status',
                    name: 'action_status',
                    render: function(data) {
                        if (!data) return '<span class="badge badge-light">-</span>';
                        if (data === 'pending') return '<span class="badge badge-light-warning">Pending</span>';
                        if (data === 'in_progress') return '<span class="badge badge-light-info">In Progress</span>';
                        if (data === 'completed') return '<span class="badge badge-light-success">Completed</span>';
                        if (data === 'cancelled') return '<span class="badge badge-light-danger">Cancelled</span>';
                        return data || '';
                    }
                },
                {
                    data: 'user_name',
                    name: 'user.name'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: function(data, type, row) {
                        let editUrl = `{{ route('admin.manajemenstok.defects.index') }}`.replace(
                            '/defects', `/defects/${row.id}/edit`);
                        let html = '';
                        @if ($canEdit)
                            if (row.status === 'pending') {
                                html += `<a href="${editUrl}" class="btn btn-sm btn-light-primary me-2">Edit</a>`;
                            }
                            if (row.status === 'approved') {
                                html += `<button type="button" class="btn btn-sm btn-light-info btn-handle-action" data-id="${row.id}">Penanganan</button>`;
                            }
                        @endif
                        return html;
                    }
                }
            ]);

            let table = $('#defects-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.manajemenstok.defects.index') }}",
                    data: function(d) {
                        d['search.value'] = $('#search_input').val();
                        d['status'] = $('#status_filter').val();
                        d['date'] = $('#date_filter').val();
                    }
                },
                columns: columns,
                order: [
                    [1, 'desc']
                ]
            });

            const debounce = (callback, wait) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => {
                        callback.apply(null, args);
                    }, wait);
                };
            }

            $('#search_input').on('keyup', debounce(function() {
                table.draw();
            }, 400));

            $('#status_filter').on('change', function() {
                table.draw();
            });
            $('#date_filter').on('change', function() {
                table.draw();
            });

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif
            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif
        });
    </script>
@endpush

@push('styles')
<style>
  .chip { display:inline-block; padding:.35rem .65rem; border-radius:.5rem; font-weight:700; font-size:.8rem; }
  .chip-default{ background:#F3F4F6; color:#374151; }
  .chip-success{ background:#ECFDF5; color:#047857; }
  .chip-warning{ background:#FEF3C7; color:#92400E; }
  .chip-danger{ background:#FEE2E2; color:#991B1B; }
  .meta-label { color:#6B7280; font-weight:600; font-size:.85rem; }
  .meta-value { font-weight:700; color:#111827; }
  .info-box { border: 1px solid #EFF2F5; border-radius:.475rem; padding:1rem; background:#fff; }
  .section-title { font-weight:700; color:#111827; margin-bottom:.5rem; }
  .modal.fade .modal-dialog { transform: translate(0, -10px); transition: transform .2s ease-out; }
  .modal.show .modal-dialog { transform: translate(0, 0); }
  .w-160px { width:160px; }
  .text-break { word-break: break-word; }
  .action-highlight { border:1px solid #DBEAFE; background:linear-gradient(180deg,#EFF6FF 0%,#FFFFFF 100%); border-radius:.75rem; padding:1rem; }
  .action-highlight .title { font-weight:800; color:#1E3A8A; display:flex; align-items:center; gap:.5rem; }
  .action-highlight .subtitle { color:#3B82F6; font-weight:600; }
  .action-highlight .chips { display:flex; gap:.5rem; flex-wrap:wrap; }
  .action-highlight .icon { color:#2563EB; }
  /* Table polish */
  #defects-table thead th { background:#F9FAFB; border-bottom:1px solid #E5E7EB; }
  #defects-table tbody tr:hover { background:#F8FAFC; }
  #defects-table td, #defects-table th { vertical-align: middle; }
</style>
@endpush

@push('scripts')
<script>
  $(function(){
    const modalId = '#defectActionModal';
    const $modal = $(modalId);
    const infoUrlTpl = `{{ route('admin.manajemenstok.defects.info', ':id') }}`;
    // const editUrlTpl = `{{ route('admin.manajemenstok.defects.index') }}`.replace('/defects','/defects/:id/edit#action');
    const updateUrlTpl = `{{ route('admin.manajemenstok.defects.action', ':id') }}`;
    const completeUrlTpl = `{{ route('admin.manajemenstok.defects.action.complete', ':id') }}`;

    $(document).on('click', '.btn-handle-action', function(){
      const id = $(this).data('id');
      if(!id) return;
      const url = infoUrlTpl.replace(':id', id);
      // Reset placeholders
      fillModal({});
      $modal.modal('show');
      $.get(url).done(function(res){
        fillModal(res || {});
        // no external manage button; handled inline\n        setupForms(id, res || {});
      }).fail(function(){
        $modal.find('.modal-body').html('<div class="alert alert-danger">Gagal memuat informasi defect.</div>');
      });
    });

    function badge(val, map){
      const cls = map[val] || 'chip-default';
      const text = (val||'-').toString().replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase());
      return `<span class="chip ${cls}">${text}</span>`;
    }

    function fillModal(data){
      const statusMap = { 'pending':'chip-warning', 'approved':'chip-success', 'cancelled':'chip-danger' };
      const actionStatusMap = { 'pending':'chip-warning', 'in_progress':'chip-default', 'completed':'chip-success', 'cancelled':'chip-danger' };
      const actionLabel = (data.action||'').toString().replace(/_/g,' ');
      const actionPretty = actionLabel ? actionLabel.replace(/\b\w/g, c=>c.toUpperCase()) : 'Belum Ada Penanganan';
      const actionChip = data.action ? `<span class="chip chip-default">${actionPretty}</span>` : `<span class="chip chip-warning">Belum Ada</span>`;
      const actionStatusChip = data.action_status ? `<span class="chip ${actionStatusMap[data.action_status]||'chip-default'}">${(data.action_status||'-').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase())}</span>` : '';
      const headerHtml = `
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
          <div>
            <div class="title"><i class="fas fa-tools icon"></i> Penanganan Defect</div>
            <div class="subtitle">${(data.item? (data.item.sku||'')+' - '+(data.item.nama_barang||'') : '-')}</div>
          </div>
          <div class="chips">${actionChip}${actionStatusChip}</div>
        </div>`;
      $modal.find('.action-highlight').html(headerHtml);
      const rows = [
        {label:'Kode', value: data.code || '-'},
        {label:'Tanggal', value: data.date || '-'},
        {label:'Gudang', value: (data.warehouse && data.warehouse.name) || '-'},
        {label:'Item', value: data.item ? `${data.item.sku||'-'} - ${data.item.nama_barang||'-'}` : '-'},
        {label:'Qty', value: (data.quantity != null ? data.quantity : '-')},
        {label:'Koli', value: (data.koli != null ? Number(data.koli).toFixed(2) : '-')},
        {label:'Status', value: badge(data.status, statusMap)},
        {label:'Action', value: data.action ? `<span class="chip chip-default">${actionPretty}</span>` : '<span class="chip chip-warning">Belum Ada</span>'},
        {label:'Action Status', value: data.action_status ? `<span class="chip ${actionStatusMap[data.action_status]||'chip-default'}">${(data.action_status||'-').replace(/_/g,' ').replace(/\b\w/g, c=>c.toUpperCase())}</span>` : '<span class="text-muted">-</span>'},
        {label:'Dibuat Oleh', value: data.created_by || '-'},
        {label:'Disetujui Oleh', value: data.approved_by || '-'},
        {label:'Waktu Approve', value: data.approved_at || '-'},
      ];
      const meta = rows.map(r => `
        <div class="col-md-6 mb-3">
          <div class="meta-label">${r.label}</div>
          <div class="meta-value text-break">${r.value}</div>
        </div>
      `).join('');
      const notes = (data.notes && data.notes.length) ? data.notes : '<span class="text-muted">-</span>';
      $modal.find('.defect-meta').html(meta);
      $modal.find('.defect-notes').html(notes);
      // no external manage button; handled inline
    }

    function setupForms(id, data){
      const canHandle = data.status === 'approved';
      const startVisible = canHandle && (!data.action || data.action_status === 'pending');
      const completeVisible = canHandle && data.action && data.action_status === 'in_progress';
      const $start = $modal.find('.defect-action-start');
      const $complete = $modal.find('.defect-action-complete');
      $start.toggle(startVisible);
      $complete.toggle(completeVisible);

      // Populate start form
      const $startForm = $modal.find('#defect-start-form');
      $startForm.find('input[name="defect_id"]').val(id);
      $startForm.attr('action', updateUrlTpl.replace(':id', id));
      if (data.action) $startForm.find('select[name="action"]').val(data.action);
      $startForm.find('textarea[name="action_notes"]').val(data.action_notes || '');

      const $completeForm = $modal.find('#defect-complete-form');
      $completeForm.find('input[name="defect_id"]').val(id);
      $completeForm.attr('action', completeUrlTpl.replace(':id', id));
      $completeForm.find('textarea[name="action_notes"]').val('');
    }

    // Submit handlers
    $(document).on('submit', '#defect-start-form', function(e){
      e.preventDefault();
      const $form = $(this);
      const url = $form.attr('action');
      const data = $form.serialize();
      const $btn = $form.find('button[type="submit"]');
      Swal.fire({
        text: 'Apakah Anda yakin ingin memulai penanganan untuk defect ini?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya, Mulai', cancelButtonText: 'Batal',
        buttonsStyling: false,
        customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'}
      }).then(function(result){
        if(!result.isConfirmed) return;
        $btn.prop('disabled', true);
        $.post(url, data)
          .done(function(){
            Swal.fire({ text: 'Penanganan dimulai.', icon: 'success', buttonsStyling:false, confirmButtonText:'OK', customClass:{confirmButton:'btn btn-primary'} });
            $('#defects-table').DataTable().ajax.reload(null, false);
            $(modalId).modal('hide');
          })
          .fail(function(xhr){
            const msg = xhr.responseJSON?.message || 'Gagal memulai penanganan.';
            Swal.fire({ text: msg, icon: 'error', confirmButtonText: 'OK', buttonsStyling: false, customClass:{confirmButton:'btn btn-primary'} });
          })
          .always(function(){ $btn.prop('disabled', false); });
      });
    });

    $(document).on('submit', '#defect-complete-form', function(e){
      e.preventDefault();
      const $form = $(this);
      const url = $form.attr('action');
      const data = $form.serialize();
      const $btn = $form.find('button[type="submit"]');
      Swal.fire({
        text: 'Apakah Anda yakin ingin menyelesaikan penanganan defect ini?',
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Ya, Selesaikan', cancelButtonText: 'Batal',
        buttonsStyling: false,
        customClass:{confirmButton:'btn btn-primary', cancelButton:'btn btn-light'}
      }).then(function(result){
        if(!result.isConfirmed) return;
        $btn.prop('disabled', true);
        $.post(url, data)
          .done(function(){
            Swal.fire({ text: 'Tindakan diselesaikan.', icon: 'success', buttonsStyling:false, confirmButtonText:'OK', customClass:{confirmButton:'btn btn-primary'} });
            $('#defects-table').DataTable().ajax.reload(null, false);
            $(modalId).modal('hide');
          })
          .fail(function(xhr){
            const msg = xhr.responseJSON?.message || 'Gagal menyelesaikan tindakan.';
            Swal.fire({ text: msg, icon: 'error', confirmButtonText: 'OK', buttonsStyling: false, customClass:{confirmButton:'btn btn-primary'} });
          })
          .always(function(){ $btn.prop('disabled', false); });
      });
    });
  });
</script>

<!-- Modal: Defect Action Info -->
<div class="modal fade" id="defectActionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Informasi Barang Defect</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="action-highlight mb-5"></div>
        <div class="info-box mb-5">
          <div class="section-title">Detail</div>
          <div class="row defect-meta"></div>
        </div>
        <div class="info-box">
          <div class="section-title">Catatan</div>
          <div class="defect-notes"></div>
        </div>
        <div class="info-box mt-5 defect-action-start" style="display:none;">
          <div class="section-title">Mulai Penanganan</div>
          <form id="defect-start-form" method="POST">
            @csrf
            <input type="hidden" name="defect_id" value="">
            <div class="row g-3 align-items-end">
              <div class="col-md-5">
                <label class="form-label required">Pilih Tindakan</label>
                <select name="action" class="form-select form-select-solid" required>
                  <option value="">Pilih</option>
                  <option value="disposal">Disposal</option>
                  <option value="rework">Rework</option>
                  <option value="return_to_stock">Return to Stock</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-md-7">
                <label class="form-label">Catatan</label>
                <textarea name="action_notes" rows="1" class="form-control form-control-solid" placeholder="Catatan (opsional)"></textarea>
              </div>
            </div>
            <div class="mt-4">
              <button type="submit" class="btn btn-info">Mulai Penanganan</button>
            </div>
          </form>
        </div>
        <div class="info-box mt-5 defect-action-complete" style="display:none;">
          <div class="section-title">Selesaikan Penanganan</div>
          <form id="defect-complete-form" method="POST">
            @csrf
            <input type="hidden" name="defect_id" value="">
            <div class="mb-3">
              <label class="form-label">Catatan Akhir</label>
              <textarea name="action_notes" rows="2" class="form-control form-control-solid" placeholder="Catatan (opsional)"></textarea>
            </div>
            <button type="submit" class="btn btn-success">Selesaikan</button>
          </form>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
 </div>
@endpush



