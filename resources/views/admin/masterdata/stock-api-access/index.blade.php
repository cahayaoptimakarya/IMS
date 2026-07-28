@extends('layouts.admin')

@section('title', 'Akses API Stok')

@section('content')
<div class="card mb-4"><div class="card-body">
    <h4><i class="fas fa-shield-alt me-2"></i>Akses API Stok</h4>
    <p class="mb-0 text-muted">Hanya IP aktif dalam daftar ini yang dapat memakai Bearer token API. Jika daftar kosong, API ditolak untuk semua IP.</p>
</div></div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card mb-4"><div class="card-body">
    <form method="POST" action="{{ route('admin.masterdata.stock-api-access.store') }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-md-4"><label class="form-label">IP Address</label><input name="ip_address" class="form-control" placeholder="203.0.113.10" required></div>
        <div class="col-md-5"><label class="form-label">Keterangan</label><input name="label" class="form-control" placeholder="Server pusat"></div>
        <div class="col-md-3"><button class="btn btn-primary w-100">Tambah IP</button></div>
    </form>
</div></div>

<div class="card"><div class="card-body table-responsive"><table class="table align-middle">
    <thead><tr><th>IP Address</th><th>Keterangan</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($ips as $ip)<tr>
        <td class="fw-bold">{{ $ip->ip_address }}</td><td>{{ $ip->label ?: '-' }}</td>
        <td><span class="badge bg-{{ $ip->is_active ? 'success' : 'secondary' }}">{{ $ip->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td>{{ $ip->created_at?->format('d M Y H:i') }}</td>
        <td class="text-end">
            <form class="d-inline" method="POST" action="{{ route('admin.masterdata.stock-api-access.update', $ip) }}">@csrf @method('PUT')<input type="hidden" name="label" value="{{ $ip->label }}"><input type="hidden" name="is_active" value="{{ $ip->is_active ? 0 : 1 }}"><button class="btn btn-sm btn-outline-secondary">{{ $ip->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            <form class="d-inline" method="POST" action="{{ route('admin.masterdata.stock-api-access.destroy', $ip) }}" onsubmit="return confirm('Hapus IP ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form>
        </td>
    </tr>@empty<tr><td colspan="5" class="text-center text-muted">Belum ada IP aktif; API belum dapat diakses.</td></tr>@endforelse
    </tbody>
</table></div></div>
@endsection
