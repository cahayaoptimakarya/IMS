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
                                <label class="form-label fs-6 fw-bolder text-dark required">Tanggal</label>
                                <input type="text" id="date" name="date" class="form-control form-control-solid" value="{{ old('date', optional($assembly->date)->format('Y-m-d')) }}" required>
                            </div>
                            <div class="mb-5">
                                <label class="form-label fs-6 fw-bolder text-dark required">Gudang</label>
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
                                <label class="form-label fs-6 fw-bolder text-dark">Resep (Opsional)</label>
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
                                <label class="form-label fs-6 fw-bolder text-dark required">Produk Jadi</label>
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
                                <label class="form-label fs-6 fw-bolder text-dark required">Output Plan</label>
                                <input type="number" step="0.01" name="output_quantity_plan" class="form-control form-control-solid" value="{{ old('output_quantity_plan', $assembly->output_quantity_plan) }}" required>
                                @error('output_quantity_plan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label fs-6 fw-bolder text-dark">Output Koli Plan</label>
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
                        <label class="form-label fs-6 fw-bolder text-dark">Komponen (Plan)</label>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-4" id="components-table">
                                <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th style="width:35%">Item</th>
                                    <th class="text-end" style="width:15%">Qty Resep</th>
                                    <th class="text-end" style="width:15%">Qty Plan</th>
                                    <th class="text-end" style="width:15%">Koli Plan</th>
                                    <th style="width:10%">Aksi</th>
                                </tr>
                                </thead>
                                <tbody id="components-body">
                                @foreach($assembly->components as $comp)
                                    <tr class="component-row">
                                        <td>
                                            <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>
                                                <option value="">Pilih Item</option>
                                                @foreach($items as $item)
                                                    <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}" {{ $comp->item_id == $item->id ? 'selected' : '' }}>{{ $item->sku }} - {{ $item->nama_barang }}</option>
                                                @endforeach
                                            </select>
                                            <div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>
                                        </td>
                                        <td><input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" value="{{ $comp->quantity_recipes }}" required></td>
                                        <td><input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" value="{{ $comp->quantity_plan }}" required></td>
                                        <td><input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan" value="{{ $comp->koli_plan }}"></td>
                                        <td><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
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
        if ($('#recipe_id').length) {
            $('#recipe_id').select2({ width: '100%', placeholder: 'Pilih Resep', allowClear: true });
        }

        function buildRow(itemId, qtyRec, qtyPlan, koliPlan) {
            const $tr = $('<tr class="component-row"></tr>');
            const $item = $('<select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required></select>');
            $item.append('<option value="">Pilih Item</option>');
            @foreach($items as $item)
                $item.append('<option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>');
            @endforeach
            if (itemId) { $item.val(String(itemId)); }
            const $qtyRec = $('<input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" required />');
            if (typeof qtyRec !== 'undefined' && qtyRec !== null) { $qtyRec.val(qtyRec); }
            const $qtyPlan = $('<input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" required />');
            if (typeof qtyPlan !== 'undefined' && qtyPlan !== null) { $qtyPlan.val(qtyPlan); }
            const $koliPlan = $('<input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan" />');
            if (typeof koliPlan !== 'undefined' && koliPlan !== null) { $koliPlan.val(koliPlan); }

            const tds = $('<td></td><td></td><td></td><td></td><td></td>');
            $(tds[0]).append($item);
            $(tds[1]).append($qtyRec);
            $(tds[2]).append($qtyPlan);
            $(tds[3]).append($koliPlan);
            $(tds[4]).append('<button type="button" class="btn btn-danger btn-sm remove-row">X</button>');
            $tr.append(tds);
            $tr.find('[data-control="select2"]').select2({ width: '100%' });
            return $tr;
        }

        // capture initial component rows data for reset
        const initialRows = [];
        $('#components-body .component-row').each(function(){
            initialRows.push({
                item_id: $(this).find('select[name="components[item_id][]"]').val() || null,
                qty_rec: $(this).find('input[name="components[quantity_recipes][]"]').val() || null,
                qty_plan: $(this).find('input[name="components[quantity_plan][]"]').val() || null,
                koli_plan: $(this).find('input[name="components[koli_plan][]"]').val() || null,
            });
        });

        function resetToInitial() {
            const $finished = $('select[name="finished_item_id"]');
            $finished.prop('disabled', false).trigger('change.select2');
            const $body = $('#components-body');
            try { $body.find('[data-control="select2"]').select2('destroy'); } catch(e) {}
            $body.empty();
            if (initialRows.length === 0) {
                const $row = buildRow(null, null, null, null);
                $body.append($row);
                fetchRowStock($row);
            } else {
                initialRows.forEach(function(r){
                    const $row = buildRow(r.item_id, r.qty_rec, r.qty_plan, r.koli_plan);
                    $body.append($row);
                    fetchRowStock($row);
                });
            }
        }
        $('#add-component').on('click', function(){
            const $row = buildRow(null, null, null, null);
            $('#components-body').append($row);
            fetchRowStock($row);
        });
        $('#components-body').on('click', '.remove-row', function(){ $(this).closest('.component-row').remove(); });

        // when choose recipe => prefill finished item and components via AJAX
        $('#recipe_id').on('change', function(){
            const id = $(this).val();
            const $finished = $('select[name="finished_item_id"]');
            if (!id) { resetToInitial(); return; }
            const url = '{{ url('/admin/manajemen-stok/perakitan/recipes') }}' + '/' + id;
            $.get(url)
              .done(function(resp){
                  if (resp.finished_item_id) {
                      $finished.val(String(resp.finished_item_id)).trigger('change.select2');
                      $finished.prop('disabled', true).trigger('change.select2');
                  }
                  if (resp.items && Array.isArray(resp.items)) {
                      const $wrap = $('#components-body');
                      try { $wrap.find('[data-control="select2"]').select2('destroy'); } catch(e) {}
                      $wrap.empty();
                      const outPlanNow = parseFloat($('input[name="output_quantity_plan"]').val());
                      resp.items.forEach(function(it){
                          const qtyRec = parseFloat(it.quantity) || 0;
                          const qtyPlanNum = !isNaN(outPlanNow) ? (qtyRec * outPlanNow) : qtyRec;
                          // compute koli plan using item ratio
                          const opt = $('<select></select>');
                          @foreach($items as $item)
                            opt.append('<option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>');
                          @endforeach
                          opt.val(String(it.item_id));
                          const sel = opt.find('option[value="'+String(it.item_id)+'"]');
                          const r = parseFloat(sel.data('koli'));
                          const ratio = (isNaN(r) || r <= 0) ? 1 : r;
                          const koliPlanNum = qtyPlanNum / ratio;
                          const $row = buildRow(it.item_id, qtyRec, qtyPlanNum.toFixed(2), isNaN(koliPlanNum) ? '' : koliPlanNum.toFixed(2));
                          $wrap.append($row);
                          fetchRowStock($row);
                      });
                  }
              })
              .fail(function(){ toastr.error('Gagal mengambil detail resep'); });
        });

        // auto-calc Qty Plan = Output Plan * Qty Resep
        $('input[name="output_quantity_plan"]').on('input change', function(){
            const outPlan = parseFloat($(this).val());
            $('#components-body .component-row').each(function(){
                const $row = $(this);
                const qtyRec = parseFloat($row.find('input[name="components[quantity_recipes][]"]').val());
                if (!isNaN(outPlan) && !isNaN(qtyRec)) {
                    $row.find('input[name="components[quantity_plan][]"]').val((outPlan * qtyRec).toFixed(2));
                }
            });
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
            $('#components-body .component-row').each(function(){
                const $comp = $(this).find('select[name="components[item_id][]"]');
                if (String($comp.val()) === String(finished)) { found = true; clearAndHighlight($comp); }
            });
            if (found) showFinishedComponentConflict();
        });
        $('#components-body').on('change', 'select[name="components[item_id][]"]', function(){
            const finished = $('select[name="finished_item_id"]').val();
            const currentVal = $(this).val();
            if (finished && String(currentVal) === String(finished)) { clearAndHighlight($(this)); showFinishedComponentConflict(); return; }
            // prevent duplicate component items
            if (currentVal) {
                let count = 0;
                $('#components-body .component-row select[name="components[item_id][]"]').each(function(){
                    if (String($(this).val()) === String(currentVal)) { count++; }
                });
                if (count > 1) { clearAndHighlight($(this)); showComponentDuplicate(); }
            }
            fetchRowStock($(this).closest('.component-row'));
        });

        // no auto-scale: user can set plan manually

        // Stock info helpers
        function updateAllRowsStock() {
            $('#components-body .component-row').each(function(){ fetchRowStock($(this)); });
        }
        function fetchRowStock($row) {
            const wh = $('select[name="warehouse_id"]').val();
            const item = $row.find('select[name="components[item_id][]"]').val();
            const $qty = $row.find('.stock-display .stock-qty');
            const $koli = $row.find('.stock-display .stock-koli');
            if (!wh || !item) { $qty.text('-'); $koli.text('-'); return; }
            const url = '{{ url('/admin/manajemen-stok/perakitan/stock') }}';
            $.get(url, { warehouse_id: wh, item_id: item })
                .done(function(resp){
                    if (resp && resp.success) {
                        $qty.text(Number(resp.quantity ?? 0).toFixed(2));
                        $koli.text(Number(resp.koli ?? 0).toFixed(2));
                    } else { $qty.text('-'); $koli.text('-'); }
                })
                .fail(function(){ $qty.text('-'); $koli.text('-'); });
        }
        $('select[name="warehouse_id"]').on('change', updateAllRowsStock);
        updateAllRowsStock();

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
            $('#components-body .component-row').each(function(){
                const item = $(this).find('select[name="components[item_id][]"]').val();
                const qty = parseFloat($(this).find('input[name="components[quantity_plan][]"]').val());
                if (!item || !qty || qty <= 0) validComponents = false;
                if (item && finished && String(item) === String(finished)) duplicate = true;
            });
            const seen = {};
            $('#components-body .component-row select[name="components[item_id][]"]').each(function(){
                const v = $(this).val();
                if (!v) return;
                if (seen[v]) { duplicateItems = true; }
                seen[v] = true;
            });
            if (!validComponents) { Swal.fire({icon:'error', title:'Validasi Gagal', text:'Isi komponen dengan benar.'}); return; }
            if (duplicate) { showFinishedComponentConflict(); return; }
            if (duplicateItems) { showComponentDuplicate(); return; }

            Swal.fire({ title:'Apakah Anda yakin?', text:'Perubahan akan disimpan.', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, simpan', cancelButtonText:'Batal', reverseButtons:true })
                .then(function(res){ if (res.isConfirmed) { $('select[name="finished_item_id"]').prop('disabled', false); form.submit(); } });
        });

        toastr.options = { positionClass: 'toast-top-center', progressBar: true };
        @if ($errors->any())
            JSON.parse(@json($errors->all())).forEach(function(m){ toastr.error(m); });
        @endif
        @if (Session::has('error')) toastr.error("{{ session('error') }}"); @endif
        @if (Session::has('stock_errors'))
            (function(){
                const list = @json(session('stock_errors')) || [];
                const title = @json(session('stock_error_title') ?? 'Stok tidak mencukupi');
                let html = '<div style="text-align:left">Berikut komponen yang stoknya kurang:<ul style="margin-top:8px">';
                list.forEach(function(it){
                    const sku = it.sku ? '('+it.sku+') ' : '';
                    html += '<li>'+sku+ (it.name || ('Item #'+it.item_id)) + ': butuh ' + (Number(it.required).toFixed(2)) + ', tersedia ' + (Number(it.available).toFixed(2)) + '</li>';
                });
                html += '</ul></div>';
                Swal.fire({ icon:'error', title: title, html: html });
            })();
        @endif
    });
</script>
@endpush
