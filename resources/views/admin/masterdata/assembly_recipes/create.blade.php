@extends('layouts.app')
@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Resep Perakitan',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Resep Perakitan', 'Tambah'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-6">
                <form id="assembly-recipe-form" action="{{ route('admin.masterdata.assemblyrecipes.store') }}" method="POST">
                    @csrf
                    <div class="row g-5">
                        <div class="col-md-6">
                            <div class="mb-5">
                                <label class="form-label">Kode</label>
                                <input type="text" name="code" class="form-control form-control-solid" value="{{ old('code', $nextCode ?? '') }}" readonly>
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Deskripsi</label>
                                <textarea name="description" class="form-control form-control-solid" rows="3" placeholder="Keterangan">{{ old('description') }}</textarea>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Produk Jadi</label>
                                <select name="finished_item_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                    <option value="">Pilih Item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" {{ old('finished_item_id') == $item->id ? 'selected' : '' }}>({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                    @endforeach
                                </select>
                                @error('finished_item_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            
                            <div class="mb-5 form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Aktif</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Komponen</label>
                            <div id="components">
                                @if(old('components.item_id'))
                                    @php
                                        $oldItemIds = old('components.item_id', []);
                                        $oldQtys = old('components.quantity', []);
                                    @endphp
                                    @foreach($oldItemIds as $idx => $oi)
                                        <div class="d-flex gap-3 mb-3 component-row">
                                            <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                                <option value="">Pilih Item</option>
                                                @foreach($items as $item)
                                                    <option value="{{ $item->id }}" {{ $oi == $item->id ? 'selected' : '' }}>({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" value="{{ $oldQtys[$idx] ?? '' }}" required>
                                            <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="d-flex gap-3 mb-3 component-row">
                                        <select name="components[item_id][]" class="form-select form-select-solid" required>
                                            <option value="">Pilih Item</option>
                                            @foreach($items as $item)
                                                <option value="{{ $item->id }}">({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" required>
                                        <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                    </div>
                                @endif
                                @error('components')
                                    <div><span class="text-danger">{{ $message }}</span></div>
                                @enderror
                            </div>
                            <template id="component-row-template">
                                <div class="d-flex gap-3 mb-3 component-row">
                                    <select name="components[item_id][]" class="form-select form-select-solid" data-placeholder="Pilih Item" required>
                                        <option value="">Pilih Item</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}">({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" name="components[quantity][]" class="form-control form-control-solid" placeholder="Qty" required>
                                    <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-light-primary" id="add_row">Tambah Komponen</button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button type="submit" class="btn btn-primary me-2">Simpan</button>
                        <a href="{{ route('admin.masterdata.assemblyrecipes.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            const btnAdd = document.getElementById('add_row');
            const container = document.getElementById('components');
            const tpl = document.getElementById('component-row-template');

            function initSelect2In(el){
                const $sel = $(el).find('select[name="components[item_id][]"]');
                if ($sel.length && !$sel.hasClass('select2-hidden-accessible')) {
                    $sel.select2({ width: '100%', dropdownParent: $('#kt_content') });
                }
            }

            // init existing rows
            container.querySelectorAll('.component-row').forEach(function(row){ initSelect2In(row); });

            btnAdd.addEventListener('click', function(){
                const frag = tpl.content.cloneNode(true);
                container.appendChild(frag);
                // init select2 on the last appended row
                const rows = container.querySelectorAll('.component-row');
                const last = rows[rows.length - 1];
                initSelect2In(last);
            });

            container.addEventListener('click', function(e){
                if(e.target.classList.contains('remove-row')){
                    const rows = container.querySelectorAll('.component-row');
                    if(rows.length > 1){ e.target.closest('.component-row').remove(); }
                }
            });
        });
    </script>
@endsection

@push('scripts')

    <script>
        $(document).ready(function() {
            toastr.options = {
                "closeButton": true,
                "debug": false,
                "newestOnTop": false,
                "progressBar": true,
                "positionClass": "toast-top-center",
                "preventDuplicates": false,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            };

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif

            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif
            @if ($errors->any())
                var laravelErrors = @json($errors->all());
                laravelErrors.forEach(function(msg){ toastr.error(msg); });
            @endif
            // SweetAlert confirm + validation before submit (create)
            $('#assembly-recipe-form').on('submit', function(e){
                e.preventDefault();
                var form = this;
                // clear previous invalid markers
                $(form).find('.is-invalid').removeClass('is-invalid');

                var code = $('input[name="code"]').val().trim();
                var finished = $('select[name="finished_item_id"]').val();
                var validComponents = true;
                var duplicateFound = false;
                var duplicateRows = [];

                $('#components .component-row').each(function(){
                    var $row = $(this);
                    var item = $row.find('select[name="components[item_id][]"]').val();
                    var qty = parseFloat($row.find('input[name="components[quantity][]"]').val());
                    if(!item || !qty || qty <= 0){ validComponents = false; }
                    if(item && finished && String(item) === String(finished)){
                        duplicateFound = true;
                        duplicateRows.push($row);
                    }
                });

                    // Immediate duplicate check: show SweetAlert if finished item equals any component
                    function showFinishedComponentConflict() {
                        Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Produk jadi tidak boleh sama dengan salah satu komponen.' });
                    }

                    function clearAndHighlight($sel){
                        $sel.addClass('is-invalid');
                        // clear value and notify select2 if used
                        $sel.val('').trigger('change');
                        // remove invalid class after short delay so user can reselect
                        setTimeout(function(){ $sel.removeClass('is-invalid'); }, 3000);
                    }

                    // when finished product changes, remove any matching components and alert
                    $('select[name="finished_item_id"]').on('change', function(){
                        var finished = $(this).val();
                        if(!finished) return;
                        var found = false;
                        $('#components .component-row').each(function(){
                            var $row = $(this);
                            var $comp = $row.find('select[name="components[item_id][]"]');
                            if(String($comp.val()) === String(finished)){
                                found = true;
                                clearAndHighlight($comp);
                            }
                        });
                        if(found) showFinishedComponentConflict();
                    });

                    // when a component select changes, check against finished product
                    $('#components').on('change', 'select[name="components[item_id][]"]', function(){
                        var finished = $('select[name="finished_item_id"]').val();
                        var $sel = $(this);
                        if(!finished) return;
                        if(String($sel.val()) === String(finished)){
                            clearAndHighlight($sel);
                            showFinishedComponentConflict();
                        }
                    });

                if(!code || !finished || !validComponents){
                    Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Lengkapi data wajib dan pastikan qty > 0.' });
                    return;
                }

                if(duplicateFound){
                    // mark offending selects
                    duplicateRows.forEach(function($r){
                        $r.find('select[name="components[item_id][]"]').addClass('is-invalid');
                    });
                    Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Produk jadi tidak boleh sama dengan salah satu komponen.' });
                    // scroll to first invalid
                    $('html, body').animate({ scrollTop: $('.is-invalid').first().offset().top - 100 }, 250);
                    return;
                }

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Dokumen akan disubmit. Lanjutkan?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, submit',
                    cancelButtonText: 'Batal',
                    reverseButtons: true
                }).then(function(res){
                    if(res.isConfirmed){ form.submit(); }
                });
            });
        });
    </script>
@endpush
