@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        .csv-help .csv-col {
            display:inline-flex; align-items:center; justify-content:center;
            width:22px; height:22px; border-radius:6px; font-weight:700; font-size:12px;
            background:#EEF2FF; color:#3730A3; margin-right:8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Roboto Mono', 'Helvetica Neue', monospace;
        }
        .csv-help code { background:#F3F4F6; padding:2px 6px; border-radius:4px; }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Import Kategori Item',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Import Kategori Item'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <h3 class="fw-bolder mb-0">Import Kategori Item dari CSV</h3>
                </div>
            </div>
            <div class="card-body">
                <form id="import-form" action="{{ route('admin.masterdata.item-categories-import.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-5 align-items-end">
                        <div class="col-lg-8 col-md-9">
                            <div class="fv-row">
                                <label class="form-label required">Pilih CSV</label>
                                <div class="input-group">
                                    <input type="file" name="csv_file" id="csv-input" class="form-control" accept=".csv,text/csv">
                                    <button type="submit" class="btn btn-primary" id="btn-import">
                                        <span class="indicator-label">Import</span>
                                        <span class="indicator-progress">Mohon tunggu...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <div class="form-text">Gunakan file CSV dengan delimiter titik koma (;)</div>
                                <span id="csv-filename" class="ms-2 text-muted small d-none"></span>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="w-100 mt-6">
                    <div class="alert alert-light-primary border csv-help p-4 mb-0">
                        <div class="fw-bold mb-1">Format CSV</div>
                        <div class="text-gray-700 small">Delimiter <code>;</code>. Header tidak wajib.</div>
                        <div class="row g-3 mt-2 small">
                            <div class="col-md-6">
                                <ul class="mb-0 list-unstyled">
                                    <li><span class="csv-col">A</span> SKU</li>
                                    <li class="mt-1"><span class="csv-col">B</span> Nama Kategori</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    Jika kategori (kolom B) belum ada, sistem akan otomatis membuatnya. Item dengan SKU (kolom A)
                                    akan diperbarui agar menggunakan kategori tersebut.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-10" />
                <div class="card-title mb-3">
                    <h3 class="fw-bolder mb-0">Import Tempat Item dari CSV</h3>
                </div>
                <form id="import-tempat-form" action="{{ route('admin.masterdata.item-categories-import.import-tempat') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-5 align-items-end">
                        <div class="col-lg-8 col-md-9">
                            <div class="fv-row">
                                <label class="form-label required">Pilih CSV</label>
                                <div class="input-group">
                                    <input type="file" name="csv_file_tempat" id="csv-input-tempat" class="form-control" accept=".csv,text/csv">
                                    <button type="submit" class="btn btn-primary" id="btn-import-tempat">
                                        <span class="indicator-label">Import</span>
                                        <span class="indicator-progress">Mohon tunggu...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                </div>
                                <div class="form-text">CSV delimiter titik koma (;). Kolom A = SKU, Kolom B = Tempat (lokasi/slot).</div>
                                <span id="csv-filename-tempat" class="ms-2 text-muted small d-none"></span>
                            </div>
                        </div>
                    </div>
                </form>

                @php($summaryTempat = session('import_tempat_summary'))
                @if ($summaryTempat)
                <div class="w-100 mt-6">
                    <div class="alert alert-light-info border p-4">
                        <div class="fw-bold mb-1">Ringkasan Import Tempat</div>
                        <div class="row g-3 small">
                            <div class="col-md-4">Item diperbarui: <span class="fw-bold text-primary">{{ $summaryTempat['updated'] ?? 0 }}</span></div>
                            <div class="col-md-4">Baris dilewati: <span class="fw-bold text-warning">{{ $summaryTempat['skipped'] ?? 0 }}</span></div>
                        </div>
                        @if (!empty($summaryTempat['notes']))
                        <div class="mt-2">
                            <div class="fw-bold">Catatan</div>
                            <ul class="mb-0 small text-gray-700">
                                @foreach ($summaryTempat['notes'] as $note)
                                    <li>{{ $note }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
        @php($summary = session('import_summary'))
        @if ($summary)
        <div class="card mt-6">
            <div class="card-header">
                <div class="card-title">
                    <span class="svg-icon svg-icon-2 me-2"><i class="fas fa-clipboard-check"></i></span>
                    <h3 class="fw-bolder mb-0">Ringkasan Hasil Import</h3>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-5">
                    <div class="col-md-4">
                        <div class="border rounded p-4 h-100 bg-light">
                            <div class="text-muted">Kategori dibuat</div>
                            <div class="fs-2hx fw-bolder text-success">{{ $summary['created_categories'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-4 h-100 bg-light">
                            <div class="text-muted">Item diperbarui</div>
                            <div class="fs-2hx fw-bolder text-primary">{{ $summary['updated_items'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-4 h-100 bg-light">
                            <div class="text-muted">Baris dilewati</div>
                            <div class="fs-2hx fw-bolder text-warning">{{ $summary['skipped'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                @if (!empty($summary['notes']))
                <div class="mt-6">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-bold">Catatan</div>
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#importNotes" aria-expanded="false" aria-controls="importNotes">
                            Tampilkan/Sembunyikan
                        </button>
                    </div>
                    <div class="collapse show" id="importNotes">
                        <ul class="mb-0 small text-gray-700">
                            @foreach ($summary['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                        @if (count($summary['notes']) >= 50)
                            <div class="text-muted small mt-2">Menampilkan 50 catatan pertama.</div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        $(function(){
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-center'
            };

            @if (Session::has('success'))
                toastr.success("{{ session('success') }}");
            @endif
            @if (Session::has('error'))
                toastr.error("{{ session('error') }}");
            @endif

            $('#csv-input').on('change', function(){
                var f = this.files && this.files[0];
                if (f) {
                    $('#csv-filename').text(f.name).removeClass('d-none');
                } else {
                    $('#csv-filename').text('').addClass('d-none');
                }
            });

            const btn = document.getElementById('btn-import');
            $('#import-form').on('submit', function(e){
                e.preventDefault();
                const file = document.getElementById('csv-input').files[0];
                if (!file) { toastr.error('Pilih file CSV terlebih dahulu.'); return; }

                Swal.fire({
                    text: 'Apakah Anda yakin ingin import file ' + file.name + ' ?',
                    icon: 'question',
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: 'Ya, import!',
                    cancelButtonText: 'Batal',
                    customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-primary' }
                }).then(function(result){
                    if (result.isConfirmed) {
                        btn.setAttribute('data-kt-indicator','on');
                        btn.disabled = true;
                        e.currentTarget.submit();
                    }
                });
            });

            // Import tempat form
            $('#csv-input-tempat').on('change', function(){
                var f = this.files && this.files[0];
                if (f) { $('#csv-filename-tempat').text(f.name).removeClass('d-none'); }
                else { $('#csv-filename-tempat').text('').addClass('d-none'); }
            });
            const btnTempat = document.getElementById('btn-import-tempat');
            $('#import-tempat-form').on('submit', function(e){
                e.preventDefault();
                const file = document.getElementById('csv-input-tempat').files[0];
                if (!file) { toastr.error('Pilih file CSV terlebih dahulu.'); return; }
                Swal.fire({
                    text: 'Import tempat dari file ' + file.name + ' ?',
                    icon: 'question', showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: 'Ya, import!', cancelButtonText: 'Batal',
                    customClass: { confirmButton: 'btn fw-bold btn-primary', cancelButton: 'btn fw-bold btn-active-light-primary' }
                }).then(function(result){
                    if (result.isConfirmed) {
                        btnTempat.setAttribute('data-kt-indicator','on');
                        btnTempat.disabled = true;
                        e.currentTarget.submit();
                    }
                });
            });
        });
    </script>
@endpush
