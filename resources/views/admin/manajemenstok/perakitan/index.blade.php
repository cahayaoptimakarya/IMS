@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="d-flex align-items-center position-relative my-1">
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path>
                            </svg>
                        </span>
                        <form method="GET" action="{{ route('admin.manajemenstok.perakitan.index') }}" class="d-flex align-items-center gap-2">
                            <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-solid w-250px ps-15" placeholder="Cari Perakitan">
                            <button type="submit" class="btn btn-light">Cari</button>
                        </form>
                    </div>
                </div>
                <div class="card-toolbar">
                    @if ($canCreate)
                        <div class="d-flex justify-content-end">
                            <a href="{{ route('admin.manajemenstok.perakitan.create') }}" class="btn btn-primary">Tambah Perakitan</a>
                        </div>
                    @endif
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="dataTables_wrapper dt-bootstrap4 no-footer">
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5" id="table-on-page">
                            <thead>
                             <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Kode</th>
                                <th>Tanggal</th>
                                <th>Gudang</th>
                                <th>Produk Jadi</th>
                                <th>Status</th>
                                <th>Qty Plan</th>
                                <th>Qty Real</th>
                                <th>Koli Plan</th>
                                <th>Koli Real</th>
                                <th>Catatan</th>
                                <th>Aksi</th>
                            </tr>
                            </thead>
                            <tbody class="fw-bold text-gray-600">
                            @forelse($assemblies as $row)
                                <tr>
                                    <td>{{ ($assemblies->currentPage() - 1) * $assemblies->perPage() + $loop->iteration }}</td>
                                    <td>{{ $row->code }}</td>
                                    <td>{{ optional($row->date)->format('Y-m-d') }}</td>
                                    <td>{{ $row->warehouse->name ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <div class="fw-bold text-primary">{{ $row->finishedItem->sku ?? '-' }}</div>
                                            <div class="text-muted small">{{ $row->finishedItem->nama_barang ?? '-' }}</div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($row->status === 'completed')
                                            <span class="badge badge-light-success">Selesai</span>
                                        @else
                                            <span class="badge badge-light-warning">Dalam Proses</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($row->output_quantity_plan, 2, ',', '.') }}</td>
                                    <td>{{ number_format($row->output_quantity_real, 2, ',', '.') }}</td>
                                    <td>{{ number_format($row->output_koli_plan ?? 0, 2, ',', '.') }}</td>
                                    <td>{{ number_format($row->output_koli_real ?? 0, 2, ',', '.') }}</td>
                                    <td>{{ $row->notes ?? '-' }}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            @if($row->status !== 'completed')
                                                <button type="button" class="btn btn-sm btn-success btn-approve" data-id="{{ $row->id }}" data-code="{{ $row->code }}" data-default-real="{{ $row->output_quantity_plan }}">Approve</button>
                                                <a class="btn btn-sm btn-light-primary" href="{{ route('admin.manajemenstok.perakitan.edit', $row) }}">Edit</a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted">Belum ada data</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        {{ $assemblies->links() }}
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

        // Approve modal handling
        const $modal = $('#approveModal');
        $('.btn-approve').on('click', function(){
            const id = $(this).data('id');
            const code = $(this).data('code');
            const def = $(this).data('default-real') || 0;
            $('#approve_code').text(code);
            $('#approve_qty').val(def);
            const action = '{{ url('/admin/manajemen-stok/perakitan') }}' + '/' + id + '/approve';
            $('#approveForm').attr('action', action);
            $modal.modal('show');
        });
    });
 </script>
 
 <!-- Approve Modal -->
 <div class="modal fade" id="approveModal" tabindex="-1">
   <div class="modal-dialog">
     <div class="modal-content">
       <div class="modal-header">
         <h5 class="modal-title">Approval Perakitan <span id="approve_code"></span></h5>
         <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
       </div>
       <form id="approveForm" method="POST" action="#">
         @csrf
         <div class="modal-body">
           <div class="mb-5">
             <label class="form-label required">Output Quantity Real</label>
             <input type="number" step="0.01" name="output_quantity_real" id="approve_qty" class="form-control form-control-solid" required>
           </div>
           <div class="mb-5">
             <label class="form-label">Output Koli Real</label>
             <input type="number" step="0.01" name="output_koli_real" class="form-control form-control-solid">
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
