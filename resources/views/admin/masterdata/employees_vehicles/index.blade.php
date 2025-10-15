@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Karyawan & Kendaraan',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Karyawan & Kendaraan'],
    ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
    <div class="card">
        <div class="card-header border-0 pt-6">
            <ul class="nav nav-tabs nav-line-tabs nav-stretch fs-6 border-0">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-employees">Karyawan</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-vehicles">Kendaraan</a></li>
            </ul>
            <div class="card-toolbar">
                <button id="btn-add" class="btn btn-primary">Tambah</button>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="tab-content" id="tabs">
                <div class="tab-pane fade show active" id="tab-employees" role="tabpanel">
                    <div class="d-flex align-items-center position-relative my-3">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6"></span>
                        <input type="text" id="search_emp" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Karyawan">
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="emp-table">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Nama</th><th>Telepon</th><th>Posisi</th><th>SIM</th><th>Status</th><th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-vehicles" role="tabpanel">
                    <div class="d-flex align-items-center position-relative my-3">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6"></span>
                        <input type="text" id="search_veh" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Kendaraan">
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="veh-table">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>No. Polisi</th><th>Tipe</th><th>Merek</th><th>Model</th><th>Warna</th><th>Status</th><th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div class="modal fade" tabindex="-1" id="ev_modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="ev_title">Tambah</h5><button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Tutup</button></div>
                <form id="ev_form">
                    @csrf
                    <input type="hidden" id="ev_type" name="type" value="employees">
                    <input type="hidden" id="ev_id">
                    <div class="modal-body" id="ev_body">
                        <!-- dynamic fields injected by JS -->
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
    const empTable = $('#emp-table').DataTable({
        processing:true, serverSide:true,
        ajax:{ url:`{{ route('admin.masterdata.employees-vehicles.data') }}`, data: d=>{ d.type='employees'; d.search.value=$('#search_emp').val(); } },
        columns:[
            {data:'name'},{data:'phone'},{data:'position'},{data:'license_number'},
            {data:'active', render:(d)=> d ? '<span class="badge badge-light-success">Aktif</span>' : '<span class="badge badge-light-secondary">Nonaktif</span>'},
            {data:'id', orderable:false, searchable:false, render:(id,_,row)=> actionBtns('employees',id,row)}
        ],
        order:[[0,'asc']],
        drawCallback: function(settings){ if (window.KTMenu && typeof window.KTMenu.createInstances === 'function') { window.KTMenu.createInstances(); } }
    });
    const vehTable = $('#veh-table').DataTable({
        processing:true, serverSide:true,
        ajax:{ url:`{{ route('admin.masterdata.employees-vehicles.data') }}`, data: d=>{ d.type='vehicles'; d.search.value=$('#search_veh').val(); } },
        columns:[
            {data:'license_plate'},{data:'type'},{data:'brand'},{data:'model'},{data:'color'},
            {data:'active', render:(d)=> d ? '<span class="badge badge-light-success">Aktif</span>' : '<span class="badge badge-light-secondary">Nonaktif</span>'},
            {data:'id', orderable:false, searchable:false, render:(id,_,row)=> actionBtns('vehicles',id,row)}
        ],
        order:[[0,'asc']],
        drawCallback: function(settings){ if (window.KTMenu && typeof window.KTMenu.createInstances === 'function') { window.KTMenu.createInstances(); } }
    });
    const debounce=(fn,ms=400)=>{let t;return (...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)};};
    $('#search_emp').on('keyup', debounce(()=>empTable.draw()));
    $('#search_veh').on('keyup', debounce(()=>vehTable.draw()));

    function actionBtns(type,id,row){
        const payload = encodeURIComponent(JSON.stringify(row||{}));
        return `
        <div class="text-center">
            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions</a>
            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-150px py-4" data-kt-menu="true">
                <div class="menu-item px-3"><button class="menu-link bg-transparent border-0 px-3 ev-edit" data-type="${type}" data-id="${id}" data-payload="${payload}">Edit</button></div>
                <div class="menu-item px-3"><button class="menu-link bg-transparent border-0 px-3 ev-del" data-type="${type}" data-id="${id}" data-code="${(row && (row.name||row.license_plate))||''}">Delete</button></div>
            </div>
        </div>`;
    }

    function buildForm(type, data={}){
        $('#ev_type').val(type);
        if(type==='employees'){
            $('#ev_body').html(`
                <div class="mb-3"><label class="form-label required">Nama</label><input class="form-control form-control-solid" name="name" value="${data.name||''}" required></div>
                <div class="mb-3"><label class="form-label">Telepon</label><input class="form-control form-control-solid" name="phone" value="${data.phone||''}"></div>
                <div class="mb-3"><label class="form-label">Posisi</label><input class="form-control form-control-solid" name="position" value="${data.position||''}"></div>
                <div class="mb-3"><label class="form-label">No Identitas (SIM,KTP,Paspor)</label><input class="form-control form-control-solid" name="license_number" value="${data.license_number||''}"></div>
                <input type="hidden" name="active" value="0">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" ${data.active? 'checked':''}> <label class="form-check-label">Aktif</label></div>
                <div class="mb-3"><label class="form-label">Catatan</label><textarea class="form-control form-control-solid" name="notes">${data.notes||''}</textarea></div>
            `);
        } else {
            $('#ev_body').html(`
                <div class="mb-3"><label class="form-label required">No. Polisi</label><input class="form-control form-control-solid" name="license_plate" value="${data.license_plate||''}" required></div>
                <div class="mb-3"><label class="form-label">Tipe</label><input class="form-control form-control-solid" name="type" value="${data.type||''}"></div>
                <div class="mb-3"><label class="form-label">Merek</label><input class="form-control form-control-solid" name="brand" value="${data.brand||''}"></div>
                <div class="mb-3"><label class="form-label">Model</label><input class="form-control form-control-solid" name="model" value="${data.model||''}"></div>
                <div class="mb-3"><label class="form-label">Warna</label><input class="form-control form-control-solid" name="color" value="${data.color||''}"></div>
                <input type="hidden" name="active" value="0">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" ${data.active? 'checked':''}> <label class="form-check-label">Aktif</label></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea class="form-control form-control-solid" name="description">${data.description||''}</textarea></div>
            `);
        }
    }

    $('#btn-add').on('click', function(){
        const activeTab = $('.nav-tabs .nav-link.active').attr('href');
        const type = (activeTab==='#tab-employees') ? 'employees' : 'vehicles';
        $('#ev_id').val('');
        $('#ev_title').text('Tambah ' + (type==='employees'?'Karyawan':'Kendaraan'));
        buildForm(type, {});
        const m = new bootstrap.Modal(document.getElementById('ev_modal')); m.show();
    });

    // Edit
    $(document).on('click', '.ev-edit', function(){
        const id = $(this).data('id'); const type=$(this).data('type');
        let data = {};
        const payload = $(this).data('payload');
        if (payload) { try { data = JSON.parse(decodeURIComponent(payload)); } catch(e){} }
        $('#ev_id').val(id);
        $('#ev_title').text('Edit ' + (type==='employees'?'Karyawan':'Kendaraan'));
        buildForm(type, data);
        const m = new bootstrap.Modal(document.getElementById('ev_modal')); m.show();
    });

    // Delete
    $(document).on('click', '.ev-del', function(){
        const id=$(this).data('id'); const type=$(this).data('type'); const code=$(this).data('code')||'';
        Swal.fire({text: `Hapus data ${code}?`, icon:'warning', showCancelButton:true, buttonsStyling:false, confirmButtonText:'Ya, Hapus', cancelButtonText:'Batal', customClass:{confirmButton:'btn btn-danger', cancelButton:'btn btn-light'}})
        .then(function(res){ if(res.value){ $.ajax({ url:`{{ route('admin.masterdata.employees-vehicles.destroy', ['type'=>'__type__','id'=>'__id__']) }}`.replace('__type__',type).replace('__id__',id), type:'POST', data:{ _method:'DELETE', _token:`{{ csrf_token() }}` } })
            .done(()=>{ (type==='employees'? empTable : vehTable).ajax.reload(null,false); toastr.success('Berhasil dihapus'); })
            .fail(xhr=>{ toastr.error(xhr.responseJSON?.message||'Gagal menghapus'); }); }});
    });

    // Submit form
    $('#ev_form').on('submit', function(e){ e.preventDefault();
        const id=$('#ev_id').val(); const type=$('#ev_type').val();
        const url = id ? `{{ route('admin.masterdata.employees-vehicles.update', ['type' => '__type__', 'id' => '__id__']) }}`.replace('__type__', type).replace('__id__', id)
                        : `{{ route('admin.masterdata.employees-vehicles.store') }}`;
        const method = id ? 'POST' : 'POST';
        const payload = $(this).serialize();
        $.ajax({ url, type: method, data: id ? payload + '&_method=PUT' : payload })
            .done(()=>{ $('#ev_modal').modal('hide'); (type==='employees'? empTable : vehTable).ajax.reload(null,false); toastr.success('Data tersimpan'); })
            .fail(xhr=>{
                const errs = xhr.responseJSON?.errors || {}; $('.is-invalid').removeClass('is-invalid'); $('.invalid-feedback').remove();
                Object.keys(errs).forEach(k=>{ const f = $(`[name="${k}"]`); f.addClass('is-invalid').after(`<div class="invalid-feedback">${errs[k][0]}</div>`); });
                toastr.error(xhr.responseJSON?.message||'Validasi gagal');
            });
    });
});
</script>
@endpush
