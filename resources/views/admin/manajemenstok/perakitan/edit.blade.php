@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Edit Perakitan',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Perakitan', 'Edit'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-6">
                <form id="assembly-form" action="{{ route('admin.manajemenstok.perakitan.update', $assembly) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="mb-5">
                                <label class="form-label">Kode</label>
                                <input type="text" class="form-control form-control-solid" value="{{ $assembly->code }}" readonly>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Tanggal</label>
                                <input type="text" id="date" name="date" class="form-control form-control-solid" value="{{ old('date', optional($assembly->date)->format('Y-m-d')) }}" required>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Gudang</label>
                                <select name="warehouse_id" class="form-select form-select-solid" data-control="select2" required>
                                    <option value="">Pilih Gudang</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ old('warehouse_id', $assembly->warehouse_id) == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Resep (Opsional)</label>
                                <select name="assembly_recipe_id" id="recipe_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Resep">
                                    <option value="">Pilih Resep</option>
                                    @foreach($recipes as $r)
                                        <option value="{{ $r->id }}" data-finished="{{ $r->finished_item_id }}"
                                            {{ old('assembly_recipe_id', $assembly->assembly_recipe_id) == $r->id ? 'selected' : '' }}>
                                            ({{ $r->finishedItem->sku ?? '' }}) - {{ $r->finishedItem->nama_barang ?? '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="mb-5">
                                <label class="form-label required">Produk Jadi</label>
                                <select name="finished_item_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                    <option value="">Pilih Item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" {{ old('finished_item_id', $assembly->finished_item_id) == $item->id ? 'selected' : '' }}>({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                    @endforeach
                                </select>
                                @error('finished_item_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Output Plan</label>
                                <input type="number" step="0.01" name="output_quantity_plan" class="form-control form-control-solid" value="{{ old('output_quantity_plan', $assembly->output_quantity_plan) }}" required>
                                @error('output_quantity_plan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Output Koli Plan</label>
                                <input type="number" step="0.01" name="output_koli_plan" class="form-control form-control-solid" value="{{ old('output_koli_plan', $assembly->output_koli_plan) }}">
                                @error('output_koli_plan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Catatan</label>
                                <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Keterangan">{{ old('notes', $assembly->notes) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="form-label required">Komponen (Plan)</label>
                        <div id="components">
                            @foreach($assembly->components as $comp)
                                <div class="d-flex gap-3 mb-3 component-row">
                                    <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>
                                        <option value="">Pilih Item</option>
                                        @foreach($items as $item)
                                            <option value="{{ $item->id }}" {{ $comp->item_id == $item->id ? 'selected' : '' }}>{{ $item->sku }} - {{ $item->nama_barang }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid" placeholder="Qty Resep" value="{{ $comp->quantity_recipes }}" required>
                                    <input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid" placeholder="Qty Plan" value="{{ $comp->quantity_plan }}" required>
                                    <input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid" placeholder="Koli Plan" value="{{ $comp->koli_plan }}">
                                    <button type="button" class="btn btn-light-danger remove-row">Hapus</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-light-primary" id="add-component">Tambah Komponen</button>
                    </div>

                    <div class="text-end mt-8">
                        <a href="{{ route('admin.manajemenstok.perakitan.index') }}" class="btn btn-light me-3">Batal</a>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function(){
        $('[data-control="select2"]').select2({ width: '100%' });
        $('#add-component').on('click', function(){
            const row = $(
                '<div class="d-flex gap-3 mb-3 component-row">\
                    <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>\
                        <option value="">Pilih Item</option>\
                        @foreach($items as $item)\
                            <option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>\
                        @endforeach\
                    </select>\
                    <input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid" placeholder="Qty Resep" required>\
                    <input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid" placeholder="Qty Plan" required>\
                    <input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid" placeholder="Koli Plan">\
                    <button type="button" class="btn btn-light-danger remove-row">Hapus</button>\
                </div>'
            );
            $('#components').append(row);
            row.find('[data-control="select2"]').select2({width: '100%'});
        });
        $('#components').on('click', '.remove-row', function(){ $(this).closest('.component-row').remove(); });

        // when choose recipe => prefill finished item, output plan, and components via AJAX
        // when choose recipe => prefill finished item and components via AJAX
        $('#recipe_id').on('change', function(){
            const id = $(this).val();
            if (!id) return;
            const url = '{{ url('/admin/manajemen-stok/perakitan/recipes') }}' + '/' + id;
            $.get(url)
              .done(function(resp){
                  if (resp.finished_item_id) {
                      $('select[name="finished_item_id"]').val(String(resp.finished_item_id)).trigger('change');
                  }
                  if (resp.items && Array.isArray(resp.items)) {
                      const $wrap = $('#components');
                      $wrap.empty();
                      resp.items.forEach(function(it){
                          const row = $('<div class="d-flex gap-3 mb-3 component-row"></div>');
                          const select = $('<select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required></select>');
                          select.append('<option value="">Pilih Item</option>');
                          @foreach($items as $item)
                            select.append('<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>');
                          @endforeach
                          select.val(String(it.item_id));
                          const qtyRec = $('<input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid" placeholder="Qty Resep" required />');
                          qtyRec.val(it.quantity);
                          const qtyPlan = $('<input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid" placeholder="Qty Plan" required />');
                          qtyPlan.val(it.quantity);
                          const btn = $('<button type="button" class="btn btn-light-danger remove-row">Hapus</button>');
                          row.append(select).append(qtyRec).append(qtyPlan).append(btn);
                          $wrap.append(row);
                          select.select2({ width: '100%' });
                      });
                  }
              })
              .fail(function(){ toastr.error('Gagal mengambil detail resep'); });
        });

        // client-side validate: finished item cannot be a component
        function showFinishedComponentConflict() {
            Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Produk jadi tidak boleh sama dengan salah satu komponen.' });
        }
        function showComponentDuplicate() {
            Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Terdapat item komponen yang sama. Setiap item harus unik.' });
        }
        function clearAndHighlight($sel){
            $sel.addClass('is-invalid');
            $sel.val('').trigger('change');
            setTimeout(function(){ $sel.removeClass('is-invalid'); }, 2500);
        }
        $('select[name="finished_item_id"]').on('change', function(){
            const finished = $(this).val();
            if (!finished) return;
            let found = false;
            $('#components .component-row').each(function(){
                const $comp = $(this).find('select[name="components[item_id][]"]');
                if (String($comp.val()) === String(finished)) { found = true; clearAndHighlight($comp); }
            });
            if (found) showFinishedComponentConflict();
        });
        $('#components').on('change', 'select[name="components[item_id][]"]', function(){
            const finished = $('select[name="finished_item_id"]').val();
            const currentVal = $(this).val();
            if (finished && String(currentVal) === String(finished)) { clearAndHighlight($(this)); showFinishedComponentConflict(); return; }
            // prevent duplicate component items
            if (currentVal) {
                let count = 0;
                $('#components .component-row select[name="components[item_id][]"]').each(function(){
                    if (String($(this).val()) === String(currentVal)) { count++; }
                });
                if (count > 1) { clearAndHighlight($(this)); showComponentDuplicate(); }
            }
        });

        // no auto-scale: user can set plan manually

        // submit confirm & basic checks
        $('#assembly-form').on('submit', function(e){
            e.preventDefault();
            const form = this;
            const date = $('input[name="date"]').val();
            const wh = $('select[name="warehouse_id"]').val();
            const finished = $('select[name="finished_item_id"]').val();
            const recipeOpt = $('#recipe_id').find('option:selected');
            const recipeFinished = recipeOpt.data('finished');
            const output = parseFloat($('input[name="output_quantity_plan"]').val());
            if (!date || !wh || !finished || !output || output <= 0) {
                Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Lengkapi data wajib dan pastikan qty > 0.' });
                return;
            }
            if ($('#recipe_id').val() && recipeFinished && String(finished) !== String(recipeFinished)) {
                Swal.fire({ icon:'error', title:'Validasi Gagal', text:'Produk jadi harus mengikuti produk pada resep yang dipilih.' });
                return;
            }
            let validComponents = true; let duplicate = false; let duplicateItems = false;
            $('#components .component-row').each(function(){
                const item = $(this).find('select[name="components[item_id][]"]').val();
                const qty = parseFloat($(this).find('input[name="components[quantity_plan][]"]').val());
                if (!item || !qty || qty <= 0) validComponents = false;
                if (item && finished && String(item) === String(finished)) duplicate = true;
            });
            const seen = {};
            $('#components .component-row select[name="components[item_id][]"]').each(function(){
                const v = $(this).val();
                if (!v) return;
                if (seen[v]) { duplicateItems = true; }
                seen[v] = true;
            });
            if (!validComponents) { Swal.fire({icon:'error', title:'Validasi Gagal', text:'Isi komponen dengan benar.'}); return; }
            if (duplicate) { showFinishedComponentConflict(); return; }
            if (duplicateItems) { showComponentDuplicate(); return; }

            Swal.fire({ title:'Apakah Anda yakin?', text:'Perubahan akan disimpan.', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, simpan', cancelButtonText:'Batal', reverseButtons:true })
                .then(function(res){ if (res.isConfirmed) form.submit(); });
        });

        toastr.options = { positionClass: 'toast-top-center', progressBar: true };
        @if ($errors->any())
            JSON.parse(@json($errors->all())).forEach(function(m){ toastr.error(m); });
        @endif
        @if (Session::has('error')) toastr.error("{{ session('error') }}"); @endif
    });
</script>
@endpush
