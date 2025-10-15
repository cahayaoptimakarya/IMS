@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Tambah Perakitan',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Perakitan', 'Tambah'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-body p-6">
                <form id="assembly-form" action="{{ route('admin.manajemenstok.perakitan.store') }}" method="POST">
                    @csrf
                    <div class="row g-5">
                        <div class="col-lg-6">
                            <div class="mb-5">
                                <label class="form-label required">Kode</label>
                                <input type="text" name="code" class="form-control form-control-solid" value="{{ old('code', $nextCode ?? '') }}" readonly>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Tanggal</label>
                                <input type="text" id="date" name="date" class="form-control form-control-solid" value="{{ old('date', now()->format('Y-m-d')) }}" required>
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Gudang</label>
                                <select name="warehouse_id" class="form-select form-select-solid" data-control="select2" required>
                                    <option value="">Pilih Gudang</option>
                                    @foreach($warehouses as $w)
                                        <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                                @error('warehouse_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Resep (Opsional)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <select name="assembly_recipe_id" id="recipe_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Resep" style="flex:1">
                                        <option value="">Pilih Resep</option>
                                        @foreach($recipes as $r)
                                            <option value="{{ $r->id }}" data-finished="{{ $r->finished_item_id }}"
                                                {{ old('assembly_recipe_id') == $r->id ? 'selected' : '' }}>
                                                ({{ $r->finishedItem->sku ?? '' }}) - {{ $r->finishedItem->nama_barang ?? '' }} 
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="mb-5">
                                <label class="form-label required">Produk Jadi</label>
                                <select name="finished_item_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                    <option value="">Pilih Item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}" {{ old('finished_item_id') == $item->id ? 'selected' : '' }}>({{ $item->sku }}) {{ $item->nama_barang }}</option>
                                    @endforeach
                                </select>
                                @error('finished_item_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                                <input type="hidden" id="finished_item_id_hidden" value="{{ old('finished_item_id') }}">
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Output Plan</label>
                                <input type="number" step="0.01" name="output_quantity_plan" class="form-control form-control-solid" value="{{ old('output_quantity_plan', 1) }}" required>
                                @error('output_quantity_plan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label required">Output Koli Plan</label>
                                <input type="number" step="0.01" name="output_koli_plan" class="form-control form-control-solid" value="{{ old('output_koli_plan', 0) }}">
                                @error('output_koli_plan')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="mb-5">
                                <label class="form-label">Catatan</label>
                                <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Keterangan">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Dynamic components section placed below the main row --}}
                    <div class="mt-6">
                        <label class="form-label">Komponen (Plan)</label>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-4" id="components-table">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th style="width:35%" class="required">Item</th>
                                        <th class="text-end required" style="width:15%">Qty Resep</th>
                                        <th class="text-end required" style="width:15%">Qty Plan</th>
                                        <th class="text-end required" style="width:15%">Koli Plan</th>
                                        <th style="width:10%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="components-body">
                                    @php
                                        $oldItemIds = old('components.item_id', []);
                                        $oldQtyRec = old('components.quantity_recipes', []);
                                        $oldQtyPlan = old('components.quantity_plan', []);
                                        $oldKoliPlan = old('components.koli_plan', []);
                                    @endphp
                                    @if(count($oldItemIds))
                                        @foreach($oldItemIds as $idx => $oi)
                                            <tr class="component-row">
                                                <td>
                                                    <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih Item" required>
                                                        <option value="">Pilih Item</option>
                                                        @foreach($items as $item)
                                                            <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}" {{ $oi == $item->id ? 'selected' : '' }}>{{ $item->sku }} - {{ $item->nama_barang }}</option>
                                                        @endforeach
                                                    </select>
                                                    <div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>
                                                </td>
                                                <td><input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" value="{{ $oldQtyRec[$idx] ?? '' }}" required></td>
                                                <td><input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" value="{{ $oldQtyPlan[$idx] ?? '' }}" required></td>
                                                <td><input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan" value="{{ $oldKoliPlan[$idx] ?? '' }}"></td>
                                                <td><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr class="component-row">
                                            <td>
                                                <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>
                                                    <option value="">Pilih Item</option>
                                                    @foreach($items as $item)
                                                        <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>
                                            </td>
                                            <td><input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" required></td>
                                            <td><input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" required></td>
                                            <td><input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan"></td>
                                            <td><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="add-row" class="btn btn-light-primary">Tambah Komponen</button>
                    </div>
                    <div class="mt-8">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('admin.manajemenstok.perakitan.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    <!-- flatpickr js -->
<script>
    $(document).ready(function(){
        // init flatpickr for #date
        if (typeof flatpickr !== 'undefined') {
            flatpickr('#date', {
                dateFormat: 'Y-m-d',
                defaultDate: '{{ old('date', now()->format('Y-m-d')) }}'
            });
        }
        // init recipe select2 with clear (X) and capture initial state for reset
        if ($('#recipe_id').length) {
            $('#recipe_id').select2({ width: '100%', placeholder: 'Pilih Resep', allowClear: true });
        }
        const initialState = {
            finished: $('select[name="finished_item_id"]').val(),
            outputPlan: $('input[name="output_quantity_plan"]').val()
        };

        function buildEmptyRow() {
            return $(
                '<tr class="component-row">\
                    <td>\
                        <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>\
                            <option value="">Pilih Item</option>\
                            @foreach($items as $item)\
                                <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>\
                            @endforeach\
                        </select>\
                        <div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>\
                    </td>\
                    <td><input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" required></td>\
                    <td><input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" required></td>\
                    <td><input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan"></td>\
                    <td><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>\
                </tr>'
            );
        }
        function resetToInitial() {
            const $finished = $('select[name="finished_item_id"]');
            const $hidden = $('#finished_item_id_hidden');
            $finished.prop('disabled', false).val(initialState.finished).trigger('change.select2');
            $hidden.removeAttr('name');
            $('input[name="output_quantity_plan"]').val(initialState.outputPlan);
            const $wrap = $('#components-body');
            // destroy any select2 to avoid ghost containers
            try { $wrap.find('[data-control="select2"]').select2('destroy'); } catch(e) {}
            $wrap.empty();
            const row = buildEmptyRow();
            $wrap.append(row);
            row.find('[data-control="select2"]').select2({ width: '100%' });
            fetchRowStock(row);
        }
        // add/remove rows
        $('#add-row').on('click', function(){
            const row = $(
                '<tr class="component-row">\
                    <td>\
                        <select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required>\
                            <option value="">Pilih Item</option>\
                            @foreach($items as $item)\
                                <option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>\
                            @endforeach\
                        </select>\
                        <div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>\
                    </td>\
                    <td><input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" required></td>\
                    <td><input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" required></td>\
                    <td><input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan"></td>\
                    <td><button type="button" class="btn btn-danger btn-sm remove-row">X</button></td>\
                </tr>'
            );
            $('#components-body').append(row);
            row.find('[data-control="select2"]').select2({width: '100%'});
            fetchRowStock(row);
        });
        $('#components-body').on('click', '.remove-row', function(){ $(this).closest('.component-row').remove(); });

        // when choose recipe => prefill finished item and components via AJAX
        $('#recipe_id').on('change', function(){
            const id = $(this).val();
            const $finished = $('select[name="finished_item_id"]');
            const $hidden = $('#finished_item_id_hidden');
            if (!id) { resetToInitial(); return; }
            const url = '{{ url('/admin/manajemen-stok/perakitan/recipes') }}' + '/' + id;
            $.get(url)
                .done(function(resp){
                    if (resp.finished_item_id) {
                        $finished.val(String(resp.finished_item_id)).trigger('change');
                        $finished.prop('disabled', true).trigger('change.select2');
                        $hidden.attr('name', 'finished_item_id').val(String(resp.finished_item_id));
                    }
                    // no output qty from recipe anymore
                    if (resp.items && Array.isArray(resp.items)) {
                        const $wrap = $('#components-body');
                        $wrap.empty();
                        const outPlanNow = parseFloat($('input[name="output_quantity_plan"]').val());
                        resp.items.forEach(function(it){
                            const row = $('<tr class="component-row"></tr>');
                            const select = $('<select name="components[item_id][]" class="form-select form-select-solid" data-control="select2" required></select>');
                            select.append('<option value="">Pilih Item</option>');
                            @foreach($items as $item)
                                select.append('<option value="{{ $item->id }}" data-koli="{{ $item->koli ?? 1 }}">{{ $item->sku }} - {{ $item->nama_barang }}</option>');
                            @endforeach
                            select.val(String(it.item_id));
                            const qtyRec = $('<input type="number" step="0.01" name="components[quantity_recipes][]" class="form-control form-control-solid text-end" placeholder="Qty Resep" required />');
                            qtyRec.val(it.quantity);
                            const qtyPlan = $('<input type="number" step="0.01" name="components[quantity_plan][]" class="form-control form-control-solid text-end" placeholder="Qty Plan" required />');
                            let planVal = parseFloat(it.quantity);
                            if (!isNaN(outPlanNow)) {
                                planVal = (parseFloat(it.quantity) || 0) * outPlanNow;
                            }
                            qtyPlan.val(planVal.toFixed(2));
                            const koliPlan = $('<input type="number" step="0.01" name="components[koli_plan][]" class="form-control form-control-solid text-end" placeholder="Koli Plan" />');
                            // auto-calc koli plan from qty plan and item ratio
                            const selectedOpt = select.find('option[value="'+String(it.item_id)+'"]');
                            const r = parseFloat(selectedOpt.data('koli'));
                            const ratio = (isNaN(r) || r <= 0) ? 1 : r;
                            const planKoli = planVal / ratio;
                            koliPlan.val(isNaN(planKoli) ? '' : planKoli.toFixed(2));
                            const tds = $('<td></td><td></td><td></td><td></td><td></td>');
                            $(tds[0]).append(select);
                            $(tds[0]).append('<div class="text-muted small mt-1 stock-display">Stok: <span class="stock-qty">-</span> | Koli: <span class="stock-koli">-</span></div>');
                            $(tds[1]).append(qtyRec);
                            $(tds[2]).append(qtyPlan);
                            $(tds[3]).append(koliPlan);
                            $(tds[4]).append('<button type="button" class="btn btn-danger btn-sm remove-row">X</button>');
                            row.append(tds);
                            $wrap.append(row);
                            select.select2({ width: '100%' });
                            fetchRowStock(row);
                        });
                        // ensure recalculation stays consistent if user adjusted output plan
                        recalcComponentsFromOutput();
                    }
                })
                .fail(function(){ toastr.error('Gagal mengambil detail resep'); });
        });

        // auto-calc Qty/Koli Plan for components from Output Plan
        function recalcComponentsFromOutput() {
            const outPlan = parseFloat($('input[name="output_quantity_plan"]').val());
            $('#components-body .component-row').each(function(){
                const $row = $(this);
                const qtyRec = parseFloat($row.find('input[name="components[quantity_recipes][]"]').val());
                if (!isNaN(outPlan) && !isNaN(qtyRec)) {
                    const planQty = outPlan * qtyRec;
                    $row.find('input[name="components[quantity_plan][]"]').val(planQty.toFixed(2));
                    const compOpt = $row.find('select[name="components[item_id][]"]').find('option:selected');
                    const r = parseFloat(compOpt.data('koli'));
                    const ratio = isNaN(r) || r <= 0 ? 1 : r;
                    const planKoli = planQty / ratio;
                    $row.find('input[name="components[koli_plan][]"]').val(planKoli.toFixed(2));
                }
            });
        }
        $('input[name="output_quantity_plan"]').on('input change', recalcComponentsFromOutput);

        // Auto-calc between Output Plan and Output Koli Plan using item's koli ratio (qty per koli)
        function getFinishedKoliRatio() {
            const $opt = $('select[name="finished_item_id"]').find('option:selected');
            const ratio = parseFloat($opt.data('koli'));
            return isNaN(ratio) || ratio <= 0 ? 1 : ratio; // default 1 if missing/invalid
        }
        let syncingOutput = false;
        function qtyToKoli() {
            if (syncingOutput) return; syncingOutput = true;
            const ratio = getFinishedKoliRatio();
            const qty = parseFloat($('input[name="output_quantity_plan"]').val());
            const koli = !isNaN(qty) ? (qty / ratio) : 0;
            $('input[name="output_koli_plan"]').val(isNaN(koli) ? '' : koli.toFixed(2));
            recalcComponentsFromOutput();
            syncingOutput = false;
        }
        function koliToQty() {
            if (syncingOutput) return; syncingOutput = true;
            const ratio = getFinishedKoliRatio();
            const koli = parseFloat($('input[name="output_koli_plan"]').val());
            const qty = !isNaN(koli) ? (koli * ratio) : 0;
            $('input[name="output_quantity_plan"]').val(isNaN(qty) ? '' : qty.toFixed(2));
            recalcComponentsFromOutput();
            syncingOutput = false;
        }
        // Bind events for both directions similar to pengadaan/penerimaan logic
        $('input[name="output_quantity_plan"]').on('input change', qtyToKoli);
        $('input[name="output_koli_plan"]').on('input change', koliToQty);
        // Recompute when finished item changes (ratio changed)
        $('select[name="finished_item_id"]').on('change', function(){
            // Prefer preserving the field the user just edited: if koli has a value, recompute qty; else recompute koli
            const koliVal = $('input[name="output_koli_plan"]').val();
            if (koliVal) {
                koliToQty();
            } else {
                qtyToKoli();
            }
        });
        // Initial sync on page load
        const RESTORING_OLD = Boolean(@json(count(old('components.item_id', [])) > 0 || session()->has('stock_errors')));
        if (!RESTORING_OLD) {
            qtyToKoli();
        }

        // Keep component koli/qty in sync on manual edits and changes
        function getComponentKoliRatio($row) {
            const opt = $row.find('select[name="components[item_id][]"]').find('option:selected');
            const r = parseFloat(opt.data('koli'));
            return isNaN(r) || r <= 0 ? 1 : r;
        }
        $('#components-body').on('input change', 'input[name="components[quantity_plan][]"]', function(){
            const $row = $(this).closest('.component-row');
            const qty = parseFloat($(this).val());
            const r = getComponentKoliRatio($row);
            if (!isNaN(qty)) $row.find('input[name="components[koli_plan][]"]').val((qty / r).toFixed(2));
        });
        $('#components-body').on('input change', 'input[name="components[koli_plan][]"]', function(){
            const $row = $(this).closest('.component-row');
            const k = parseFloat($(this).val());
            const r = getComponentKoliRatio($row);
            if (!isNaN(k)) $row.find('input[name="components[quantity_plan][]"]').val((k * r).toFixed(2));
        });
        $('#components-body').on('change', 'select[name="components[item_id][]"]', function(){
            const $row = $(this).closest('.component-row');
            const qty = parseFloat($row.find('input[name="components[quantity_plan][]"]').val());
            const r = getComponentKoliRatio($row);
            if (!isNaN(qty)) $row.find('input[name="components[koli_plan][]"]').val((qty / r).toFixed(2));
            fetchRowStock($row);
        });
        $('#components-body').on('input change', 'input[name="components[quantity_recipes][]"]', function(){
            recalcComponentsFromOutput();
        });

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
                let count = 0; let $dupTarget = null;
                $('#components-body .component-row select[name="components[item_id][]"]').each(function(){
                    if (String($(this).val()) === String(currentVal)) { count++; if (count > 1) { $dupTarget = $(this); } }
                });
                if (count > 1) { clearAndHighlight($(this)); showComponentDuplicate(); }
            }
        });

        // submit confirm & basic checks
        $('#assembly-form').on('submit', function(e){
            e.preventDefault();
            const form = this;
            const code = $('input[name="code"]').val().trim();
            const date = $('input[name="date"]').val();
            const wh = $('select[name="warehouse_id"]').val();
            const finished = $('select[name="finished_item_id"]').val();
            const recipeOpt = $('#recipe_id').find('option:selected');
            const recipeFinished = recipeOpt.data('finished');
            const output = parseFloat($('input[name="output_quantity_plan"]').val());
            if (!code || !date || !wh || !finished || !output || output <= 0) {
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
                const qtyRec = parseFloat($(this).find('input[name="components[quantity_recipes][]"]').val());
                const qtyPlan = parseFloat($(this).find('input[name="components[quantity_plan][]"]').val());
                if (!item || !qtyPlan || qtyPlan <= 0 || isNaN(qtyRec)) validComponents = false;
                if (item && finished && String(item) === String(finished)) duplicate = true;
            });
            // check duplicate items across components
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

            Swal.fire({ title:'Apakah Anda yakin?', text:'Dokumen akan disimpan.', icon:'warning', showCancelButton:true, confirmButtonText:'Ya, simpan', cancelButtonText:'Batal', reverseButtons:true })
                .then(function(res){ if (res.isConfirmed) form.submit(); });
        });

        // toast
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
