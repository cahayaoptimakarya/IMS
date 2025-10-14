@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        /* Improve Item/SKU cell appearance */
        .item-sku-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .item-sku-badge {
            background: #e6f0ff;
            color: #1e40af;
            font-weight: 700;
            padding: 6px 8px;
            border-radius: 6px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, 'Roboto Mono', 'Helvetica Neue', monospace;
        }

        .item-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 320px;
        }

        @media (max-width: 576px) {
            .item-name {
                max-width: 140px;
            }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Items',
        'breadcrumbs' => ['Admin', 'Masterdata', 'Items'],
    ])
@endpush

@section('content')
    @php
        $permissionResolver = app(\App\Support\MenuPermissionResolver::class);
        $canCreate = $permissionResolver->userCan('create');
        $canEdit = $permissionResolver->userCan('edit');
        $canDelete = $permissionResolver->userCan('delete');
    @endphp
    <div class="content flex-row-fluid" id="kt_content">
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <!--begin::Svg Icon | path: icons/duotune/general/gen021.svg-->
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
                        <!--end::Svg Icon-->
                        <input type="text" id="search_input" class="form-control form-control-solid w-250px ps-15"
                            placeholder="Cari Items">
                    </div>
                    <div class="d-flex align-items-center position-relative my-1 ms-3">
                        <select id="category_filter" class="form-select form-select-solid" data-control="select2"
                            data-placeholder="Filter by Kategori">
                            <option></option>
                            <option value="semua">Semua Kategori</option>
                            @foreach ($itemcategories as $category)
                                <option value="{{ $category->id }}"
                                    {{ isset($selected_category_id) && $selected_category_id == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    @if ($canCreate)
                        <div class="d-flex flex-wrap gap-2 justify-content-end align-items-center">
                            <div class="me-2">
                                <a href="{{ route('admin.masterdata.items.create') }}" class="btn btn-primary">Tambah
                                    Item</a>
                            </div>
                        </div>
                    @endif
                    <!--end::Toolbar-->
                </div>

            </div>
            <div class="card-body pt-4">
                <div class="my-3">
                    <form id="import-form" action="{{ route('admin.masterdata.items.import') }}" method="POST"
                        enctype="multipart/form-data" class="d-flex align-items-center">
                        @csrf
                        <div class="input-group input-group-sm">
                            <label class="btn btn-sm btn-secondary mb-0" style="position:relative; overflow:hidden">
                                <span id="csv-label-text">Pilih CSV</span>
                                <input id="csv-input" type="file" name="csv_file" accept="text/csv"
                                    class="form-control form-control-sm"
                                    style="position:absolute; left:0; top:0; opacity:0; width:100%; height:100%; cursor:pointer;"
                                    required>
                            </label>
                            <button type="submit" class="btn btn-sm btn-primary ms-2">Import</button>
                        </div>
                        <div id="csv-filename" class="text-muted small ms-3 d-none"
                            style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"></div>
                    </form>

                    <div class="w-100 d-block d-md-inline-block text-muted small mt-2 mt-md-0">CSV columns: A=SKU, B=Nama
                        Barang, C=Koli (default 10), D=Low Stock (default 100), E=Item Category ID (default 1), F=UOM ID
                        (default 1)</div>
                </div>
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive min-h-500px">
                        <table class="table align-middle table-row-dashed fs-6 gy-5 dataTable no-footer w-100"
                            id="table-on-page">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th class="sorting">No</th>
                                    <th class="min-w-250px sorting">Item / SKU</th>
                                    <th class="min-w-125px sorting">Minimal Stok Menipis</th>
                                    <th class="min-w-125px sorting">Koli</th>
                                    <th class="min-w-125px sorting">UOM</th>
                                    <th class="min-w-125px sorting">Deskripsi</th>
                                    <th class="text-center min-w-125px sorting_disabled">Actions</th>
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

            var canEdit = @json((bool) $canEdit);
            var canDelete = @json((bool) $canDelete);

            function escapeHtml(t) {
                return $('<div/>').text(t ?? '').html();
            }

            var table = $('#table-on-page').DataTable({
                processing: true,
                serverSide: true,
                responsive: false,
                searchDelay: 300,
                lengthMenu: [10, 25, 50, 100],
                ajax: {
                    url: '{{ route('admin.masterdata.items.data') }}',
                    data: function(d) {
                        // Mirror PengadaanController pattern: pass explicit search.value
                        d.search = d.search || {};
                        d.search.value = $('#search_input').val();
                        d.category_id = $('#category_filter').val();
                    }
                },
                order: [
                    [1, 'asc']
                ],
                columns: [{
                        data: 'no',
                        name: 'no',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'item_sku',
                        name: 'nama_barang',
                        render: function(data, type, row) {
                            var sku = data && data.sku ? data.sku : '-';
                            var nama = data && data.nama_barang ? data.nama_barang : '';
                            return '<div class="item-sku-cell">' +
                                '<span class="item-sku-badge">' + escapeHtml(sku) + '</span>' +
                                '<span class="item-name" title="' + escapeHtml(nama) + '">' +
                                escapeHtml(nama) + '</span>' +
                                '</div>';
                        }
                    },
                    {
                        data: 'low_stock_threshold',
                        name: 'low_stock_threshold'
                    },
                    {
                        data: 'koli',
                        name: 'koli'
                    },
                    {
                        data: 'uom',
                        name: 'uom'
                    },
                    {
                        data: 'deskripsi',
                        name: 'deskripsi',
                        render: function(d) {
                            return escapeHtml(d);
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: function(id, type, row) {
                            if (!(canEdit || canDelete)) return '';
                            var btn = '' +
                                '<a href="#" class="btn btn-sm btn-light btn-active-light-primary"' +
                                ' data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">' +
                                'Actions' +
                                ' <span class="svg-icon svg-icon-5 m-0">' +
                                '   <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">' +
                                '     <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>' +
                                '   </svg>' +
                                ' </span>' +
                                '</a>';
                            var menu = '' +
                                '<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4" data-kt-menu="true">';
                            var viewUrl = '{{ route('admin.masterdata.items.show', ':id') }}'
                                .replace(':id', id);
                            menu += '<div class="menu-item px-3"><a href="' + viewUrl +
                                '" class="menu-link px-3">View</a></div>';
                            if (canEdit) {
                                menu +=
                                    '<div class="menu-item px-3"><a href="{{ route('admin.masterdata.items.edit', ':id') }}" class="menu-link px-3">Edit</a></div>'
                                    .replace(':id', id);
                            }
                            if (canDelete) {
                                var form = '<div class="menu-item px-3">' +
                                    '<form class="form-delete" action="{{ route('admin.masterdata.items.destroy', ':id') }}" method="POST" style="display:inline-block;">' +
                                    '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                                    '<input type="hidden" name="_method" value="DELETE">' +
                                    '<button type="submit" class="menu-link px-3 border-0 bg-transparent w-100 text-start justify-content-start" data-item-sku="' +
                                    escapeHtml(row.item_sku.sku) + '">Delete</button>' +
                                    '</form>' +
                                    '</div>';
                                menu += form.replace(':id', id);
                            }
                            menu += '</div>';
                            return btn + menu;
                        }
                    }
                ],
                drawCallback: function(settings) {
                    if (typeof KTMenu !== 'undefined' && KTMenu.createInstances) {
                        KTMenu.createInstances();
                    }
                },
                columnDefs: [{
                    targets: [6],
                    orderable: false,
                    className: 'text-center'
                }]
            });

            // --- Debounce helper (match pattern in Pengadaan index) ---
            const debounce = (callback, wait) => {
                let timeoutId = null;
                return (...args) => {
                    window.clearTimeout(timeoutId);
                    timeoutId = window.setTimeout(() => callback.apply(null, args), wait);
                };
            };

            // Redraw table on search with debounce
            $('#search_input').on('keyup', debounce(function() {
                table.draw();
            }, 500));

            if (canDelete) {
                $('#table-on-page').on('submit', '.form-delete', function(e) {
                    e.preventDefault();

                    var form = $(this);
                    var n = form.find('button[data-item-sku]').data('item-sku');
                    var url = form.attr('action');
                    var data = form.serialize();

                    Swal.fire({
                        text: "Apakah yakin ingin menghapus data " + n + "?",
                        icon: "warning",
                        showCancelButton: true,
                        buttonsStyling: false,
                        confirmButtonText: "Ya, hapus!",
                        cancelButtonText: "Tidak, batalkan",
                        customClass: {
                            confirmButton: "btn fw-bold btn-danger",
                            cancelButton: "btn fw-bold btn-active-light-primary"
                        }
                    }).then(function(result) {
                        if (result.value) {
                            $.ajax({
                                url: url,
                                type: 'POST',
                                data: data,
                                success: function(response) {

                                    toastr.success("Data " + n + " berhasil dihapus.");
                                    table.ajax.reload(null, false);
                                },
                                error: function(xhr) {

                                    toastr.error("Gagal menghapus data " + n +
                                        ". Silakan coba lagi.");
                                }
                            });
                        } else if (result.dismiss === 'cancel') {

                            toastr.info("Penghapusan data " + n + " dibatalkan.");
                        }
                    });
                });
            }

            $('#category_filter').on('change', function() {
                table.draw();
            });

            // Show selected CSV filename and update label
            $('#csv-input').on('change', function() {
                var file = this.files && this.files[0];
                if (file) {
                    var name = file.name;
                    $('#csv-filename').text(name).removeClass('d-none');
                    $('#csv-label-text').text('Ubah CSV');
                } else {
                    $('#csv-filename').text('').addClass('d-none');
                    $('#csv-label-text').text('Pilih CSV');
                }
            });
        });
    </script>
@endpush
