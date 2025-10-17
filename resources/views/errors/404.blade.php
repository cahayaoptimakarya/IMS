@extends('layouts.app')

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => '404 - Halaman Tidak Ditemukan',
        'breadcrumbs' => ['Error', '404'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        <div class="d-flex flex-column flex-center flex-column-fluid py-10">
            <div class="card shadow-sm w-100 w-lg-700px">
                <div class="card-body p-10 p-lg-15 text-center">
                    <div class="mb-6">
                        <span class="svg-icon svg-icon-5tx svg-icon-primary">
                            <i class="fas fa-search-minus"></i>
                        </span>
                    </div>
                    <h1 class="fw-bolder text-gray-900 mb-3">Halaman Tidak Ditemukan</h1>
                    <p class="fs-5 fw-semibold text-gray-600 mb-10">
                        Maaf, halaman yang Anda cari tidak tersedia atau telah dipindahkan.
                    </p>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

