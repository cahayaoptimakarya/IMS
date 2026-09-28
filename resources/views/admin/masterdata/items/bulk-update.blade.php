@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Update Massal Item',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items', 'Update Massal (Excel)'],
    ])
@endpush

@push('styles')
    <style>
        .bu-step { display: flex; align-items: center; gap: 12px; }
        .bu-step .bu-num {
            width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;
            font-weight: 700; background: var(--bs-light-primary, #F1FAFF); color: var(--bs-primary, #009EF7); flex-shrink: 0;
        }
        .bu-step.done .bu-num { background: var(--bs-success, #50CD89); color: #fff; }
        .bu-step-line { flex: 1; height: 2px; background: #E4E6EF; min-width: 24px; }

        .bu-field { cursor: pointer; height: 100%; transition: border-color .15s, background-color .15s; }
        .bu-field input { display: none; }
        .bu-field .bu-check {
            width: 20px; height: 20px; border-radius: 6px; border: 2px solid #D8DCE6; display: inline-flex;
            align-items: center; justify-content: center; color: transparent; font-size: 11px; flex-shrink: 0;
        }
        .bu-field.active { border-color: var(--bs-primary, #009EF7) !important; background: #F1FAFF; }
        .bu-field .bu-check i { visibility: hidden; }
        .bu-field.active .bu-check { background: var(--bs-primary, #009EF7); border-color: var(--bs-primary, #009EF7); }
        .bu-field.active .bu-check i { visibility: visible; color: #fff; }
        .bu-field.locked { cursor: not-allowed; background: #F9F9F9; opacity: .85; }
        .bu-icon { width: 36px; height: 36px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: #F5F8FA; flex-shrink: 0; }
        .bu-field.active .bu-icon { background: #fff; }

        .bu-chip { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 6px; font-size: .8rem; font-weight: 600; background: #F5F8FA; color: #3F4254; margin: 0 6px 6px 0; }
        .bu-chip.key { background: #EEF0F8; color: #1E1E2D; }

        .bu-drop {
            border: 2px dashed #D8DCE6; border-radius: 12px; padding: 28px 16px; text-align: center; cursor: pointer;
            transition: border-color .15s, background-color .15s; position: relative;
        }
        .bu-drop:hover, .bu-drop.dragover { border-color: var(--bs-primary, #009EF7); background: #F1FAFF; }
        .bu-drop input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

        .bu-stat { border-radius: 10px; padding: 14px 16px; height: 100%; }
        .bu-filter .btn.active { background: var(--bs-primary, #009EF7) !important; color: #fff !important; }

        .bu-change { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; padding: 2px 0; font-size: .85rem; }
        .bu-change .lbl { color: #7E8299; font-weight: 600; min-width: 140px; }
        .bu-change .old { color: #A1A5B7; text-decoration: line-through; word-break: break-word; }
        .bu-change .new { color: #181C32; font-weight: 700; word-break: break-word; }
        #bu_table td { vertical-align: top; }

        .bu-apply-bar { position: sticky; bottom: 16px; z-index: 5; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
    </style>
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">

        <!--begin::Steps-->
        <div class="card mb-6">
            <div class="card-body py-5">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <div class="bu-step" data-step="1"><span class="bu-num">1</span><div><div class="fw-bolder text-gray-800">Pilih Field</div><div class="text-muted fs-7">Tentukan field yang di-update</div></div></div>
                    <div class="bu-step-line d-none d-md-block"></div>
                    <div class="bu-step" data-step="2"><span class="bu-num">2</span><div><div class="fw-bolder text-gray-800">Download Template</div><div class="text-muted fs-7">Excel sesuai field terpilih</div></div></div>
                    <div class="bu-step-line d-none d-md-block"></div>
                    <div class="bu-step" data-step="3"><span class="bu-num">3</span><div><div class="fw-bolder text-gray-800">Upload & Terapkan</div><div class="text-muted fs-7">Periksa pratinjau sebelum disimpan</div></div></div>
                    <div class="ms-auto">
                        <a href="{{ route('admin.masterdata.items.index') }}" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i> Kembali ke Items</a>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Steps-->

        <!--begin::Step 1-->
        <div class="card mb-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h3 class="fw-bolder mb-1">1. Pilih field yang akan di-update</h3>
                    <div class="text-muted fs-7">Hanya field yang dipilih yang akan diproses saat upload. Field lain tidak tersentuh.</div>
                </div>
                <div class="card-toolbar gap-2">
                    <span class="badge badge-light-primary fs-7 me-2" id="bu_selected_count">0 field dipilih</span>
                    <button type="button" class="btn btn-sm btn-light-primary" id="bu_select_all">Pilih semua</button>
                    <button type="button" class="btn btn-sm btn-light" id="bu_clear">Reset</button>
                </div>
            </div>
            <div class="card-body pt-4">
                @foreach ($groupLabels as $groupKey => $groupLabel)
                    @continue(empty($groups[$groupKey]))
                    <div class="text-gray-600 fw-bolder fs-7 text-uppercase mb-3 {{ $loop->first ? '' : 'mt-6' }}">{{ $groupLabel }}</div>
                    <div class="row g-4">
                        @foreach ($groups[$groupKey] as $key => $field)
                            <div class="col-md-6 col-xl-3">
                                <label class="bu-field border border-gray-300 rounded p-4 d-flex align-items-start gap-3 w-100 mb-0" data-field="{{ $key }}">
                                    <input type="checkbox" name="fields[]" value="{{ $key }}">
                                    <span class="bu-icon"><i class="fas {{ $field['icon'] }} text-primary"></i></span>
                                    <span class="flex-grow-1">
                                        <span class="d-block fw-bolder text-gray-800">{{ $field['label'] }}</span>
                                        <span class="d-block text-muted fs-8 mt-1">{{ $field['hint'] }}</span>
                                    </span>
                                    <span class="bu-check"><i class="fas fa-check"></i></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="text-gray-600 fw-bolder fs-7 text-uppercase mb-3 mt-6">Tidak dapat diubah</div>
                <div class="row g-4">
                    @foreach ($locked as $lockedField)
                        <div class="col-md-6 col-xl-3">
                            <div class="bu-field locked border border-dashed border-gray-300 rounded p-4 d-flex align-items-start gap-3">
                                <span class="bu-icon"><i class="fas fa-lock text-gray-500"></i></span>
                                <span>
                                    <span class="d-block fw-bolder text-gray-600">{{ $lockedField['label'] }}</span>
                                    <span class="d-block text-muted fs-8 mt-1">{{ $lockedField['reason'] }}</span>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <!--end::Step 1-->

        <!--begin::Step 2-->
        <div class="card mb-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h3 class="fw-bolder mb-1">2. Download template Excel</h3>
                    <div class="text-muted fs-7">Template berisi kolom SKU dan field yang dipilih, lengkap dengan dropdown dan petunjuk.</div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="mb-5">
                    <div class="text-gray-700 fw-bold fs-7 mb-2">Kolom pada template:</div>
                    <div id="bu_columns_preview"><span class="text-muted fs-7">Pilih field terlebih dahulu.</span></div>
                </div>
                <div class="row g-5 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label fw-bold">Isi template</label>
                        <select id="bu_prefill" class="form-select form-select-solid">
                            <option value="current">Isi dengan data item saat ini</option>
                            <option value="empty">Kosong (hanya judul kolom)</option>
                        </select>
                    </div>
                    <div class="col-lg-3 bu-prefill-filter">
                        <label class="form-label fw-bold">Kategori</label>
                        <select id="bu_category" class="form-select form-select-solid">
                            <option></option>
                            @foreach ($itemcategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 bu-prefill-filter">
                        <label class="form-label fw-bold">Status</label>
                        <select id="bu_status" class="form-select form-select-solid">
                            <option value="">Semua</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-lg-3 text-lg-end">
                        <button type="button" class="btn btn-success w-100 w-lg-auto" id="bu_download" disabled>
                            <i class="fas fa-file-excel me-1"></i> Download Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Step 2-->

        <!--begin::Step 3-->
        <div class="card mb-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h3 class="fw-bolder mb-1">3. Upload file & periksa pratinjau</h3>
                    <div class="text-muted fs-7">Data belum berubah sampai Anda menekan <strong>Terapkan Perubahan</strong>.</div>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="row g-6">
                    <div class="col-lg-7">
                        <div class="bu-drop" id="bu_drop">
                            <input type="file" id="bu_file" accept=".xlsx,.xls">
                            <i class="fas fa-cloud-upload-alt fs-2x text-primary mb-3"></i>
                            <div class="fw-bolder text-gray-800" id="bu_file_label">Klik atau seret file Excel ke sini</div>
                            <div class="text-muted fs-7 mt-1">Format .xlsx / .xls, maks. 10 MB, maks. {{ number_format($maxRows, 0, ',', '.') }} baris</div>
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-primary" id="bu_preview_btn" disabled>
                                <span class="indicator-label"><i class="fas fa-search me-1"></i> Upload & Pratinjau</span>
                                <span class="indicator-progress">Memproses... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="rounded border border-gray-300 p-5 h-100">
                            <div class="fw-bolder text-gray-800 mb-3"><i class="fas fa-info-circle text-primary me-2"></i>Aturan pengisian</div>
                            <ul class="text-gray-700 fs-7 mb-0 ps-5">
                                <li class="mb-2">Setiap baris dicocokkan berdasarkan <strong>SKU</strong>. SKU tidak ikut diubah.</li>
                                <li class="mb-2">Sel <strong>kosong</strong> berarti field tersebut <strong>tidak diubah</strong>.</li>
                                <li class="mb-2">Ketik <code>{{ $clearKeyword }}</code> untuk mengosongkan Tempat, Deskripsi, atau Dimensi Koli.</li>
                                <li class="mb-2">Kolom <strong>Koli</strong> atau kolom lain di luar field terpilih akan diabaikan.</li>
                                <li>Baris yang error tidak diterapkan; baris lain tetap bisa diterapkan.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="bu_result" class="d-none mt-8">
                    <div class="separator separator-dashed mb-7"></div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div>
                            <h4 class="fw-bolder mb-1">Hasil Pratinjau</h4>
                            <div class="text-muted fs-7" id="bu_result_meta"></div>
                        </div>
                    </div>

                    <div class="row g-4 mb-6">
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light"><div class="text-muted fs-8 fw-bold">Total Baris</div><div class="fs-2 fw-bolder text-gray-800" data-stat="total">0</div></div></div>
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light-primary"><div class="text-primary fs-8 fw-bold">Item Akan Diubah</div><div class="fs-2 fw-bolder text-primary" data-stat="changed">0</div></div></div>
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light-info"><div class="text-info fs-8 fw-bold">Total Perubahan Field</div><div class="fs-2 fw-bolder text-info" data-stat="field_changes">0</div></div></div>
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light-success"><div class="text-success fs-8 fw-bold">Tidak Berubah</div><div class="fs-2 fw-bolder text-success" data-stat="unchanged">0</div></div></div>
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light-danger"><div class="text-danger fs-8 fw-bold">Error</div><div class="fs-2 fw-bolder text-danger" data-stat="error">0</div></div></div>
                        <div class="col-6 col-lg-2"><div class="bu-stat bg-light-warning"><div class="text-warning fs-8 fw-bold">SKU Tidak Ditemukan</div><div class="fs-2 fw-bolder text-warning" data-stat="not_found">0</div></div></div>
                    </div>

                    <div id="bu_warnings" class="alert alert-light-warning border border-warning border-dashed d-none">
                        <div class="fw-bolder mb-2"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Catatan</div>
                        <ul class="mb-0 ps-5 fs-7"></ul>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                        <div class="bu-filter btn-group btn-group-sm flex-wrap" role="group">
                            <button type="button" class="btn btn-light active" data-filter="">Semua</button>
                            <button type="button" class="btn btn-light" data-filter="changed">Akan Diubah</button>
                            <button type="button" class="btn btn-light" data-filter="error">Error</button>
                            <button type="button" class="btn btn-light" data-filter="not_found">Tidak Ditemukan</button>
                            <button type="button" class="btn btn-light" data-filter="unchanged">Tidak Berubah</button>
                        </div>
                        <input type="text" class="form-control form-control-solid form-control-sm w-250px" id="bu_search" placeholder="Cari SKU / nama barang">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-7 gy-3 w-100" id="bu_table">
                            <thead>
                                <tr class="text-start text-gray-500 fw-bolder fs-8 text-uppercase gs-0">
                                    <th class="w-60px">Baris</th>
                                    <th class="min-w-200px">SKU / Item</th>
                                    <th class="w-125px">Status</th>
                                    <th class="min-w-350px">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700"></tbody>
                        </table>
                    </div>

                    <div class="card bu-apply-bar mt-6">
                        <div class="card-body py-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div>
                                <div class="fw-bolder text-gray-800" id="bu_apply_text">-</div>
                                <div class="text-muted fs-8" id="bu_apply_hint"></div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" id="bu_cancel">Batal</button>
                                <button type="button" class="btn btn-primary" id="bu_apply_btn" disabled>
                                    <span class="indicator-label"><i class="fas fa-check me-1"></i> Terapkan Perubahan</span>
                                    <span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Step 3-->
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var fieldColumns = @json(collect($groups)->collapse()->map(fn ($f) => array_values($f['columns'])));
            var urls = {
                template: "{{ route('admin.masterdata.items.update-bulk.template') }}",
                preview: "{{ route('admin.masterdata.items.update-bulk.preview') }}",
                apply: "{{ route('admin.masterdata.items.update-bulk.apply') }}",
                index: "{{ route('admin.masterdata.items.index') }}"
            };
            var statusMeta = {
                changed: { label: 'Akan diubah', cls: 'badge-light-primary' },
                unchanged: { label: 'Tidak berubah', cls: 'badge-light-success' },
                error: { label: 'Error', cls: 'badge-light-danger' },
                not_found: { label: 'Tidak ditemukan', cls: 'badge-light-warning' }
            };
            var preview = null;
            var table = null;
            var statusFilter = '';

            function esc(text) { return $('<div>').text(text == null ? '' : String(text)).html(); }
            function fmt(n) { return Number(n || 0).toLocaleString('id-ID'); }
            function notify(type, message) {
                if (window.toastr) { toastr.options.positionClass = 'toast-top-center'; toastr[type](message); } else { alert(message); }
            }

            // ---------- Step 1: field selection ----------
            function selectedFields() {
                return $('.bu-field input:checked').map(function () { return this.value; }).get();
            }

            function refreshSelection() {
                var fields = selectedFields();
                $('.bu-field[data-field]').each(function () {
                    $(this).toggleClass('active', $(this).find('input').prop('checked'));
                });
                $('#bu_selected_count').text(fields.length + ' field dipilih');
                $('.bu-step[data-step="1"]').toggleClass('done', fields.length > 0);
                $('#bu_download').prop('disabled', fields.length === 0);
                $('#bu_preview_btn').prop('disabled', fields.length === 0 || !$('#bu_file')[0].files.length);

                var chips = '<span class="bu-chip key"><i class="fas fa-key me-2 fs-8"></i>SKU</span>';
                if (fields.indexOf('nama_barang') === -1 && fields.length) {
                    chips += '<span class="bu-chip text-muted">Info: Nama Barang</span>';
                }
                fields.forEach(function (f) {
                    (fieldColumns[f] || []).forEach(function (c) { chips += '<span class="bu-chip">' + esc(c) + '</span>'; });
                });
                $('#bu_columns_preview').html(fields.length ? chips : '<span class="text-muted fs-7">Pilih field terlebih dahulu.</span>');

                // Pratinjau lama tidak berlaku lagi jika pilihan field berubah
                if (preview && preview.fields.slice().sort().join() !== fields.slice().sort().join()) {
                    resetPreview();
                    notify('info', 'Pilihan field berubah. Silakan upload ulang file untuk pratinjau baru.');
                }
            }

            $('.bu-field[data-field] input').on('change', refreshSelection);
            $('#bu_select_all').on('click', function () { $('.bu-field input').prop('checked', true); refreshSelection(); });
            $('#bu_clear').on('click', function () { $('.bu-field input').prop('checked', false); refreshSelection(); });

            // ---------- Step 2: template ----------
            $('#bu_prefill').on('change', function () {
                $('.bu-prefill-filter').toggleClass('d-none', this.value !== 'current');
            });

            $('#bu_download').on('click', function () {
                var params = { fields: selectedFields(), prefill: $('#bu_prefill').val() };
                if (params.prefill === 'current') {
                    if ($('#bu_category').val()) params.category_id = $('#bu_category').val();
                    if ($('#bu_status').val()) params.status = $('#bu_status').val();
                }
                window.location.href = urls.template + '?' + $.param(params);
                $('.bu-step[data-step="2"]').addClass('done');
            });

            // ---------- Step 3: upload & preview ----------
            var $drop = $('#bu_drop');
            $drop.on('dragover dragenter', function () { $drop.addClass('dragover'); })
                .on('dragleave drop', function () { $drop.removeClass('dragover'); });

            $('#bu_file').on('change', function () {
                var file = this.files[0];
                $('#bu_file_label').html(file
                    ? '<i class="fas fa-file-excel text-success me-2"></i>' + esc(file.name) + ' <span class="text-muted fw-normal">(' + (file.size / 1024).toFixed(0) + ' KB)</span>'
                    : 'Klik atau seret file Excel ke sini');
                refreshSelection();
            });

            function setLoading($btn, loading) {
                $btn.prop('disabled', loading).attr('data-kt-indicator', loading ? 'on' : null);
            }

            $('#bu_preview_btn').on('click', function () {
                var file = $('#bu_file')[0].files[0];
                var fields = selectedFields();
                if (!file || !fields.length) return;

                var data = new FormData();
                data.append('file', file);
                fields.forEach(function (f) { data.append('fields[]', f); });

                var $btn = $(this);
                setLoading($btn, true);
                $.ajax({
                    url: urls.preview, method: 'POST', data: data, processData: false, contentType: false,
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' }
                }).done(function (res) {
                    renderPreview(res, file.name);
                }).fail(function (xhr) {
                    resetPreview();
                    notify('error', errorMessage(xhr));
                }).always(function () {
                    setLoading($btn, false);
                    refreshSelection();
                });
            });

            function errorMessage(xhr) {
                var res = xhr.responseJSON || {};
                if (res.errors) {
                    var first = Object.values(res.errors)[0];
                    return Array.isArray(first) ? first[0] : first;
                }
                return res.message || 'Terjadi kesalahan. Silakan coba lagi.';
            }

            function renderDetail(row) {
                if (row.errors && row.errors.length) {
                    return row.errors.map(function (e) {
                        return '<div class="text-danger"><i class="fas fa-times-circle me-1"></i>' + esc(e) + '</div>';
                    }).join('');
                }
                if (!row.changes || !row.changes.length) {
                    return '<span class="text-muted">Nilai di file sama dengan data saat ini.</span>';
                }
                return row.changes.map(function (c) {
                    return '<div class="bu-change"><span class="lbl">' + esc(c.label) + '</span>'
                        + '<span class="old">' + esc(c.old) + '</span>'
                        + '<i class="fas fa-long-arrow-alt-right text-gray-400"></i>'
                        + '<span class="new">' + esc(c['new']) + '</span></div>';
                }).join('');
            }

            function renderPreview(res, fileName) {
                preview = res;
                $.each(res.summary, function (key, value) { $('[data-stat="' + key + '"]').text(fmt(value)); });
                $('#bu_result_meta').text(fileName + ' | Field: ' + res.fields.map(function (f) {
                    return $('.bu-field[data-field="' + f + '"] .fw-bolder').first().text();
                }).join(', '));

                var $warnings = $('#bu_warnings');
                $warnings.toggleClass('d-none', !res.warnings.length)
                    .find('ul').html(res.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join(''));

                if (table) { table.clear().rows.add(res.rows).draw(); }
                else {
                    table = $('#bu_table').DataTable({
                        data: res.rows,
                        dom: 'rt<"row mt-4"<"col-md-5"i><"col-md-7"p>>',
                        pageLength: 25,
                        order: [[0, 'asc']],
                        columns: [
                            { data: 'row' },
                            { data: 'sku', render: function (d, t, row) {
                                if (t !== 'display') return (row.sku || '') + ' ' + (row.nama_barang || '');
                                return '<div class="fw-bolder text-primary">' + esc(row.sku || '-') + '</div>'
                                    + (row.nama_barang ? '<div class="text-muted fs-8">' + esc(row.nama_barang) + '</div>' : '');
                            } },
                            { data: 'status', render: function (d, t) {
                                if (t !== 'display') return d;
                                var m = statusMeta[d] || { label: d, cls: 'badge-light' };
                                return '<span class="badge ' + m.cls + ' fw-bolder">' + m.label + '</span>';
                            } },
                            { data: null, orderable: false, render: function (d, t, row) {
                                return t === 'display' ? renderDetail(row) : (row.errors || []).join(' ');
                            } }
                        ],
                        language: { info: 'Menampilkan _START_ - _END_ dari _TOTAL_ baris', infoEmpty: 'Tidak ada baris', infoFiltered: '(difilter dari _MAX_)', zeroRecords: 'Tidak ada baris yang cocok', paginate: { previous: '&lsaquo;', next: '&rsaquo;' } }
                    });
                    $.fn.dataTable.ext.search.push(function (settings, data, index, rowData) {
                        if (settings.nTable.id !== 'bu_table' || !statusFilter) return true;
                        return (rowData || table.row(index).data()).status === statusFilter;
                    });
                }
                setFilter('');

                var changed = res.summary.changed;
                $('#bu_apply_text').text(changed
                    ? fmt(changed) + ' item (' + fmt(res.summary.field_changes) + ' perubahan field) siap diterapkan'
                    : 'Tidak ada perubahan yang bisa diterapkan');
                $('#bu_apply_hint').text(changed
                    ? (res.summary.error + res.summary.not_found > 0 ? 'Baris error / tidak ditemukan tidak ikut diterapkan. ' : '') + 'Pratinjau berlaku ' + res.expires_in_minutes + ' menit.'
                    : 'Perbaiki file lalu upload ulang.');
                $('#bu_apply_btn').prop('disabled', !changed || !res.token);
                $('.bu-step[data-step="3"]').removeClass('done');
                $('#bu_result').removeClass('d-none');
                $('html, body').animate({ scrollTop: $('#bu_result').offset().top - 90 }, 300);
            }

            function setFilter(value) {
                statusFilter = value;
                $('.bu-filter .btn').removeClass('active').filter('[data-filter="' + value + '"]').addClass('active');
                if (table) table.draw();
            }
            $('.bu-filter .btn').on('click', function () { setFilter($(this).data('filter')); });

            var searchTimer;
            $('#bu_search').on('keyup', function () {
                var v = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { if (table) table.search(v).draw(); }, 250);
            });

            function resetPreview() {
                preview = null;
                $('#bu_result').addClass('d-none');
                $('#bu_apply_btn').prop('disabled', true);
                if (table) table.clear().draw();
            }

            $('#bu_cancel').on('click', function () {
                resetPreview();
                $('#bu_file').val('').trigger('change');
            });

            // ---------- Apply ----------
            $('#bu_apply_btn').on('click', function () {
                if (!preview || !preview.token) return;
                var $btn = $(this);
                Swal.fire({
                    html: 'Terapkan perubahan ke <strong>' + fmt(preview.summary.changed) + ' item</strong>?<br><span class="text-muted fs-7">Perubahan langsung tersimpan ke master item.</span>',
                    icon: 'question',
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: 'Ya, terapkan',
                    cancelButtonText: 'Batal',
                    customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-primary' }
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    setLoading($btn, true);
                    $.ajax({
                        url: urls.apply, method: 'POST', data: { token: preview.token },
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' }
                    }).done(function (res) {
                        $('.bu-step[data-step="3"]').addClass('done');
                        var extra = '';
                        if (res.conflicts.length) extra += '<div class="mt-3 text-start fs-7"><strong>Dilewati (data berubah sejak pratinjau):</strong><br>' + res.conflicts.map(esc).join(', ') + '</div>';
                        if (res.missing.length) extra += '<div class="mt-3 text-start fs-7"><strong>Item tidak ditemukan:</strong><br>' + res.missing.map(esc).join(', ') + '</div>';
                        resetPreview();
                        $('#bu_file').val('').trigger('change');
                        Swal.fire({
                            html: '<div class="fw-bolder">' + esc(res.message) + '</div>' + extra,
                            icon: res.conflicts.length || res.missing.length ? 'warning' : 'success',
                            showCancelButton: true,
                            buttonsStyling: false,
                            confirmButtonText: 'Lihat daftar item',
                            cancelButtonText: 'Update lagi',
                            customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-light' }
                        }).then(function (r) { if (r.isConfirmed) window.location.href = urls.index; });
                    }).fail(function (xhr) {
                        notify('error', errorMessage(xhr));
                        setLoading($btn, false);
                    }).always(function () {
                        $btn.attr('data-kt-indicator', null);
                    });
                });
            });

            if ($.fn.select2) {
                $('#bu_category').select2({ placeholder: 'Semua kategori', allowClear: true });
            }
            refreshSelection();
        });
    </script>
@endpush
