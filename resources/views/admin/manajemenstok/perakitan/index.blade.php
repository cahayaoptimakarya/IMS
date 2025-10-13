@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .status-card-icon .symbol-label { background: #F3E8FF; }
        .status-card-icon i { color: #7C3AED; }
        .status-metrics { display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
        .status-metrics .metric { text-align: center; min-width: 88px; }
        .status-metrics .chip { display: inline-block; padding: 6px 12px; border-radius: 10px; font-weight: 700; font-size: 1rem; line-height: 1; }
        .chip-warning { background: #FFF7E6; color: #F59E0B; }
    .chip-primary { background: #EFF6FF; color: #1D4ED8; }
        .chip-success { background: #ECFDF5; color: #10B981; }
        /* Fix action menu width overflowing full screen inside DataTable */
        #table-on-page .menu-sub.menu-sub-dropdown { width: auto !important; min-width: 150px; max-width: 260px; }
        #table-on-page .menu-sub.menu-sub-dropdown .menu-item { white-space: nowrap; }
        @media (max-width: 576px) { .status-metrics { justify-content: flex-start; } .status-metrics .metric { min-width: 70px; } }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Perakitan',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Perakitan'],
    ])
@endpush

@section('content')
    @php
        $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
        $canCreate = $permissionResolver->userCan('create');
        $canDelete = $permissionResolver->userCan('delete');
    @endphp
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center mb-4 mb-md-0">
                        <div class="symbol symbol-50px me-5 status-card-icon">
                            <div class="symbol-label">
                                <i class="fas fa-tools fs-2x"></i>
                            </div>
                        </div>
                        <div>
                            <div class="text-dark fw-bolder fs-5 mb-1">Status Perakitan</div>
                            <div class="text-muted fw-bold">Dokumen Perakitan</div>
                        </div>
                    </div>
                    <div class="status-metrics">
                        <div class="metric">
                            <div class="chip chip-warning" id="status_draft">
                                <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                            </div>
                            <div class="text-muted fs-8 mt-1">Draft</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-primary" id="status_in_progress">
                                <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                            </div>
                            <div class="text-muted fs-8 mt-1">Dalam Proses</div>
                        </div>
                        <div class="metric">
                            <div class="chip chip-success" id="status_completed">
                                <div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
                            </div>
                            <div class="text-muted fs-8 mt-1">Selesai</div>
                        </div>
                    </div>
                </div>
                <div class="separator my-6"></div>
            </div>
        </div>

        <div class="card mt-6">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div class="d-flex align-items-center position-relative my-1 mb-4 mb-md-0">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path>
                            </svg>
                        </span>
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Perakitan" value="{{ $search ?? '' }}">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <span class="svg-icon svg-icon-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                                </svg>
                            </span>
                            Filter
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" id="kt-toolbar-filter">
                            <div class="px-7 py-5">
                                <div class="fs-4 text-dark fw-bolder">Filter Options</div>
                            </div>
                            <div class="separator border-gray-200"></div>
                            <div class="px-7 py-5">
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Status:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="status_filter" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua" {{ ($statusFilter ?? 'semua') === 'semua' ? 'selected' : '' }}>Semua</option>
                                        <option value="draft" {{ ($statusFilter ?? '') === 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="dalam proses" {{ ($statusFilter ?? '') === 'dalam proses' ? 'selected' : '' }}>Dalam Proses</option>
                                        <option value="completed" {{ ($statusFilter ?? '') === 'completed' ? 'selected' : '' }}>Selesai</option>
                                    </select>
                                </div>
                                <div class="mb-10">
                                    <label class="form-label fs-5 fw-bold mb-3">Tanggal:</label>
                                    <select class="form-select form-select-solid fw-bolder" data-kt-select2="true" id="date_filter_options" data-dropdown-parent="#kt-toolbar-filter">
                                        <option value="semua">Semua Tanggal</option>
                                        <option value="pilih_tanggal">Pilih Tanggal</option>
                                    </select>
                                    <input class="form-control form-control-solid mt-3" placeholder="Pilih Tanggal" id="date_filter" style="display: none;" />
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="reset" class="btn btn-light btn-active-light-primary me-2" data-kt-menu-dismiss="true">Batal</button>
                                    <button type="button" class="btn btn-primary" id="apply_filter">Submit</button>
                                </div>
                            </div>
                        </div>
                        @if ($canCreate)
                            <a href="{{ route('admin.manajemenstok.perakitan.create') }}" class="btn btn-primary">Tambah Perakitan</a>
                        @endif
                    </div>
                </div>
                <div class="text-center mt-6">
                    <div class="text-dark fw-bolder fs-4">Perakitan</div>
                    <small id="filter-info" class="text-muted"></small>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                            <thead>
                             <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>Kode</th>
                                <th>Tanggal</th>
                                <th>Gudang</th>
                                <th>Produk Jadi</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                              </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection

    @push('scripts')
<script>
    $(function() {
        toastr.options = { positionClass: 'toast-top-center', progressBar: true };
        @if (Session::has('success')) toastr.success("{{ session('success') }}"); @endif
        @if (Session::has('error')) toastr.error("{{ session('error') }}"); @endif
        const canDelete = @json($canDelete);
        const csrfToken = "{{ csrf_token() }}";

        // Init date filter
        $('#date_filter').flatpickr({
            mode: 'range',
            onChange: function(selectedDates, dateStr){ if ($('#date_filter_options').val() !== 'pilih_tanggal') { $('#date_filter_options').val('pilih_tanggal').trigger('change'); } }
        });
        $('#date_filter_options').on('change', function(){ if ($(this).val() === 'pilih_tanggal') { $('#date_filter').show(); } else { $('#date_filter').hide().val(''); } });

        function loadCounts(){
            const st = $('#status_filter').val() || 'semua';
            const d = ($('#date_filter_options').val() === 'semua') ? 'semua' : ($('#date_filter').val() || 'semua');
            const s = $('#search_input').val() || '';
            $.get('{{ route('admin.manajemenstok.perakitan.status-counts') }}', { status: st, date: d, search: s })
              .done(function(resp){ $('#status_draft').text(resp.draft ?? 0); $('#status_in_progress').text(resp.in_progress ?? 0); $('#status_completed').text(resp.completed ?? 0); });
            const statusText = $('#status_filter option:selected').text();
            const dateText = ($('#date_filter_options').val() === 'semua') ? 'Semua Tanggal' : ($('#date_filter').val() || 'Pilih Tanggal');
            $('#filter-info').text(`Tanggal: ${dateText} | Status: ${statusText}`);
        }
        loadCounts();

        function loadDataTable(){
            if ($.fn.DataTable.isDataTable('#table-on-page')) { $('#table-on-page').DataTable().destroy(); }
            $('#table-on-page').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('admin.manajemenstok.perakitan.index') }}",
                    type: 'GET',
                    data: function(d){ d.search.value = $('#search_input').val(); d.status = $('#status_filter').val() || 'semua'; d.date = ($('#date_filter_options').val() === 'semua') ? 'semua' : ($('#date_filter').val() || 'semua'); }
                },
                drawCallback: function(){ KTMenu.createInstances(); },
                columns: [
                    { data: 'code', name: 'a.code' },
                    { data: 'date', name: 'a.date' },
                    { data: 'warehouse_name', name: 'w.name' },
                    { data: 'finished_sku', name: 'i.sku' },
                    { data: 'status', name: 'a.status' },
                    { data: 'id', name: 'a.id', orderable: false, searchable: false },
                ],
                order: [[1, 'desc']],
                columnDefs: [
                    { targets: 1, render: function(data){ const d=new Date(data); if(isNaN(d)) return data||''; const day=('0'+d.getDate()).slice(-2); const month=d.toLocaleString('en-GB',{month:'short'}); const year=d.getFullYear(); return `${day} ${month} ${year}`; }},
                    { targets: 3, render: function(data, type, row){ const sku=row.finished_sku||'-'; const name=row.finished_name||''; return `<div class="d-flex flex-column"><div class="fw-bold text-primary">${sku}</div><div class="text-muted small">${name}</div></div>`; }},
                    { targets: 4, render: function(data){ let cls='primary', label=data; if(data==='completed'){cls='success';} else if(data==='draft'){cls='warning';} else if(data==='dalam proses'){cls='primary';} return `<span class="badge badge-light-${cls}">${label}</span>`; }},
                    { targets: 5, render: function(data, type, row){
                        let editUrl = "{{ route('admin.manajemenstok.perakitan.edit', ':id') }}".replace(':id', row.id);
                        let showUrl = "{{ route('admin.manajemenstok.perakitan.show', ':id') }}".replace(':id', row.id);
                        let destroyUrl = "{{ route('admin.manajemenstok.perakitan.destroy', ':id') }}".replace(':id', row.id);
                        let startBtn = '';
                        let finishBtn = '';
                        let editBtn = '';
                        let detailBtn = `<div class=\"menu-item px-3\"><a href=\"${showUrl}\" class=\"menu-link px-3\">Detail</a></div>`;
                        if (row.status === 'draft') {
                            startBtn = `<div class=\"menu-item px-3\"><button type=\"button\" class=\"menu-link px-3 w-100 text-start border-0 bg-transparent btn-start\" data-id=\"${row.id}\" data-code=\"${row.code}\">Proses Perakitan</button></div>`;
                            editBtn = `<div class=\"menu-item px-3\"><a href=\"${editUrl}\" class=\"menu-link px-3\">Edit</a></div>`;
                        } else if (row.status === 'dalam proses') {
                            finishBtn = `<div class=\"menu-item px-3\"><button type=\"button\" class=\"menu-link px-3 w-100 text-start border-0 bg-transparent btn-approve\" data-id=\"${row.id}\" data-code=\"${row.code}\" data-default-real=\"${row.output_quantity_plan}\">Selesai</button></div>`;
                        }
                        let deleteBtn = (canDelete && row.status === 'draft') ? `
                            <div class=\"menu-item px-3\"> 
                                <form class=\"form-delete\" action=\"${destroyUrl}\" method=\"POST\"> 
                                    <input type=\"hidden\" name=\"_token\" value=\"${csrfToken}\"> 
                                    <input type=\"hidden\" name=\"_method\" value=\"DELETE\"> 
                                    <button type=\"submit\" class=\"menu-link px-3 border-0 bg-transparent w-100 text-start\" data-document-code=\"${row.code}\">Delete</button>
                                </form>
                            </div>` : '';
                        return `
                        <a href=\"#\" class=\"btn btn-sm btn-light btn-active-light-primary\" data-kt-menu-trigger=\"click\" data-kt-menu-placement=\"bottom-end\">Actions <span class=\"svg-icon svg-icon-5 m-0\"><svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\"><path d=\"M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z\" fill=\"black\"></path></svg></span></a>
                        <div class=\"menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4\" data-kt-menu=\"true\"> 
                            ${detailBtn}
                            ${startBtn}
                            ${finishBtn}
                            ${editBtn}
                            ${deleteBtn}
                        </div>`; 
                    }},
                ]
            });
        }
        loadDataTable();

        // Filter apply + counters
        $('#apply_filter').on('click', function(){ loadDataTable(); loadCounts(); $('[data-kt-menu-dismiss="true"]').click(); });
        const debounce = (cb, wait)=>{ let t=null; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>cb.apply(null,args), wait); }; };
        $('#search_input').on('keyup', debounce(function(){ loadDataTable(); loadCounts(); }, 500));

        // Approve modal handling (dynamic table)
       const $modal = $('#approveModal');
       $('#table-on-page').on('click', '.btn-approve', function(){
            const id = $(this).data('id');
            const code = $(this).data('code');
            const def = $(this).data('default-real') || 0;
            $('#approve_code').text(code);
            $('#approve_qty').val(def);
            $('#plan_output_qty').text('-');
            $('#plan_output_koli').text('-');
            $('#finished_info').html('-');
           $('#approve_components_body').html('<tr><td colspan="6" class="text-center text-muted">Memuat data komponen...</td></tr>');
           const action='{{ url('/admin/manajemen-stok/perakitan') }}'+'/'+id+'/approve';
           $('#approveForm').attr('action', action);

            // Init completion date picker (default today)
            if (typeof flatpickr !== 'undefined') {
                try { if ($('#approve_completed_date')[0]._flatpickr) { $('#approve_completed_date')[0]._flatpickr.destroy(); } } catch(e) {}
                $('#approve_completed_date').flatpickr({ dateFormat: 'Y-m-d', defaultDate: new Date() });
            }

            const detailUrl = '{{ url('/admin/manajemen-stok/perakitan') }}'+'/'+id+'/detail';
            $.get(detailUrl)
              .done(function(resp){
                 $('#plan_output_qty').text(Number(resp.output_quantity_plan ?? 0).toFixed(2));
                 $('#plan_output_koli').text(Number(resp.output_koli_plan ?? 0).toFixed(2));
                 const fsku = resp.finished_sku || '-';
                 const fname = resp.finished_name || '';
                 $('#finished_info').html(`<div class=\"fw-bold text-primary\">${fsku}</div><div class=\"text-muted small\">${fname}</div>`);
                 // Prefill initial Output Koli Real based on ratio (plan or finished item ratio)
                 (function(){
                     const qtyVal = parseFloat($('#approve_qty').val());
                     const planQty = Number(resp.output_quantity_plan || 0);
                     const planKoli = Number(resp.output_koli_plan || 0);
                     let ratio = planKoli > 0 ? (planQty / planKoli) : (Number(resp.finished_koli_ratio || 1) || 1);
                     if (!isFinite(ratio) || ratio <= 0) ratio = 1;
                     if (!isNaN(qtyVal)) {
                         const initKoli = qtyVal / ratio;
                         $('#approve_koli').val(isFinite(initKoli) ? initKoli.toFixed(2) : '');
                     }
                 })();
                 const rows = [];
                 (resp.components || []).forEach(function(c){
                    const sku = c.sku || '-';
                    const name = c.name || '';
                    rows.push(`
                        <tr>
                          <td><div class=\"fw-bold text-primary\">${sku}</div><div class=\"text-muted small\">${name}</div></td>
                          <td class=\"text-end\">${Number(c.quantity_recipes||0).toFixed(2)}</td>
                          <td class=\"text-end\">${Number(c.quantity_plan||0).toFixed(2)}</td>
                          <td class=\"text-end\">${Number(c.koli_plan||0).toFixed(2)}</td>
                          <td style=\"width:140px\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm form-control-solid text-end\" name=\"components[${c.id}][quantity_real]\" value=\"${Number(c.quantity_plan||0).toFixed(2)}\"></td>
                          <td style=\"width:140px\"><input type=\"number\" step=\"0.01\" class=\"form-control form-control-sm form-control-solid text-end\" name=\"components[${c.id}][koli_real]\" value=\"${Number(c.koli_plan||0).toFixed(2)}\"></td>
                        </tr>
                    `);
                 });
                 $('#approve_components_body').html(rows.join('') || '<tr><td colspan="6" class="text-center text-muted">Tidak ada komponen</td></tr>');
              })
              .fail(function(){
                 $('#approve_components_body').html('<tr><td colspan="6" class="text-center text-danger">Gagal memuat data komponen</td></tr>');
              });

            $modal.modal('show');
        });

        // Start process handling with confirm
        $('#table-on-page').on('click', '.btn-start', function(){
            const id = $(this).data('id');
            const code = $(this).data('code');
            const url = '{{ url('/admin/manajemen-stok/perakitan') }}'+'/'+id+'/start';
            Swal.fire({
                text: `Apakah anda yakin ingin memproses data ${code} ini?`,
                icon: 'warning',
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: 'Ya, proses!',
                cancelButtonText: 'Batal',
                customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-light' }
            }).then(function(res){
                if (res.value) {
                    $.post(url, { _token: csrfToken })
                      .done(function(resp){ toastr.success(resp.message || 'Dokumen diproses'); $('#table-on-page').DataTable().ajax.reload(null,false); loadCounts(); })
                      .fail(function(xhr){ toastr.error(xhr.responseJSON?.message || 'Gagal memproses dokumen'); });
                }
            });
        });

        // Delete handling with confirm
        $('#table-on-page').on('submit', '.form-delete', function(e){
            e.preventDefault();
            const form = $(this);
            const code = form.find('button[data-document-code]').data('document-code');
            Swal.fire({
                text: `Apakah yakin ingin menghapus dokumen ${code}?`,
                icon: 'warning',
                showCancelButton: true,
                buttonsStyling: false,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                customClass: { confirmButton: 'btn fw-bold btn-danger', cancelButton: 'btn fw-bold btn-active-light-light' }
            }).then(function(res){
                if (res.value) {
                    $.ajax({ url: form.attr('action'), type: 'POST', data: form.serialize() })
                      .done(function(resp){ toastr.success(resp.message || 'Berhasil dihapus'); $('#table-on-page').DataTable().ajax.reload(null,false); loadCounts(); })
                      .fail(function(xhr){ toastr.error(xhr.responseJSON?.message || 'Gagal menghapus'); });
                }
            });
        });

        // Sync qty <-> koli on modal (output + components)
        let _syncing = false;
        function getNumber(txt){ const n = parseFloat((txt||'').toString().replace(/[^0-9.\-]/g,'')); return isNaN(n)?0:n; }
        function getOutputRatio(){
            const pq = getNumber($('#plan_output_qty').text());
            const pk = getNumber($('#plan_output_koli').text());
            const ratio = pk > 0 ? (pq / pk) : 1;
            return ratio > 0 ? ratio : 1;
        }
        $('#approveForm').on('input', '#approve_qty', function(){
            if(_syncing) return; _syncing = true;
            const ratio = getOutputRatio();
            const qty = parseFloat($(this).val());
            const koli = !isNaN(qty) ? (qty / ratio) : 0;
            $('#approve_koli').val(isNaN(koli) ? '' : koli.toFixed(2));
            _syncing = false;
        });
        $('#approveForm').on('input', '#approve_koli', function(){
            if(_syncing) return; _syncing = true;
            const ratio = getOutputRatio();
            const koli = parseFloat($(this).val());
            const qty = !isNaN(koli) ? (koli * ratio) : 0;
            $('#approve_qty').val(isNaN(qty) ? '' : qty.toFixed(2));
            _syncing = false;
        });

        function getRowRatio($tr){
            const planQty = getNumber($tr.find('td:nth-child(3)').text());
            const planKoli = getNumber($tr.find('td:nth-child(4)').text());
            const ratio = planKoli > 0 ? (planQty / planKoli) : 1;
            return ratio > 0 ? ratio : 1;
        }
        $('#approve_components_body').on('input', 'input[name^="components"][name$="[quantity_real]"]', function(){
            if(_syncing) return; _syncing = true;
            const $tr = $(this).closest('tr');
            const ratio = getRowRatio($tr);
            const qty = parseFloat($(this).val());
            const koli = !isNaN(qty) ? (qty / ratio) : 0;
            $tr.find('input[name^="components"][name$="[koli_real]"]').val(isNaN(koli)?'':koli.toFixed(2));
            _syncing = false;
        });
        $('#approve_components_body').on('input', 'input[name^="components"][name$="[koli_real]"]', function(){
            if(_syncing) return; _syncing = true;
            const $tr = $(this).closest('tr');
            const ratio = getRowRatio($tr);
            const koli = parseFloat($(this).val());
            const qty = !isNaN(koli) ? (koli * ratio) : 0;
            $tr.find('input[name^="components"][name$="[quantity_real]"]').val(isNaN(qty)?'':qty.toFixed(2));
            _syncing = false;
        });
    });
</script>
 
 <!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
  <div class="modal-dialog mw-800px">
     <div class="modal-content">
       <div class="modal-header">
         <h5 class="modal-title">Approval Perakitan <span id="approve_code"></span></h5>
         <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
       </div>
       <form id="approveForm" method="POST" action="#">
         @csrf
       <div class="modal-body">
          <div class="mb-5">
            <div class="fw-bold">Produk Jadi</div>
            <div id="finished_info" class="mb-3 text-gray-800">-</div>
            <div class="fw-bold">Rencana Output</div>
            <div class="text-muted small">Qty Plan: <span id="plan_output_qty">-</span> | Koli Plan: <span id="plan_output_koli">-</span></div>
          </div>
          <div class="mb-5">
            <label class="form-label required">Tanggal Selesai</label>
            <input type="text" name="completed_date" id="approve_completed_date" class="form-control form-control-solid" placeholder="YYYY-MM-DD" required>
          </div>
          <div class="mb-5">
            <label class="form-label required">Output Quantity Real</label>
            <input type="number" step="0.01" name="output_quantity_real" id="approve_qty" class="form-control form-control-solid" required>
          </div>
           <div class="mb-5">
            <label class="form-label">Output Koli Real</label>
            <input type="number" step="0.01" name="output_koli_real" id="approve_koli" class="form-control form-control-solid">
          </div>
           <div class="mb-5">
             <div class="fw-bold mb-2">Komponen (Resep)</div>
             <div class="table-responsive" style="max-height: 320px; overflow:auto;">
               <table class="table table-sm align-middle">
                 <thead>
                   <tr>
                     <th>Item</th>
                     <th class="text-end">Qty Resep</th>
                     <th class="text-end">Qty Plan</th>
                     <th class="text-end">Koli Plan</th>
                     <th class="text-end">Qty Real</th>
                     <th class="text-end">Koli Real</th>
                   </tr>
                 </thead>
                 <tbody id="approve_components_body"></tbody>
               </table>
             </div>
           </div>
           <div class="text-muted">Komponen akan dikurangkan dan produk jadi akan ditambahkan ke stok saat disetujui.</div>
         </div>
         <div class="modal-footer">
           <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
           <button type="submit" class="btn btn-success">Approve & Selesaikan</button>
         </div>
       </form>
     </div>
   </div>
 </div>
    @endpush
