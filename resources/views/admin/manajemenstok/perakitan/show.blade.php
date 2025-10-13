@extends('layouts.app')

@push('styles')
    <style>
        :root { --doc-text:#1f2937; --doc-muted:#6b7280; --doc-border:#d1d5db; }
        .doc-wrapper { background:#fff; border:1px solid var(--doc-border); border-radius:.5rem; padding:28px; }
        .doc-header { display:grid; grid-template-columns:160px 1fr 260px; gap:16px; align-items:center; margin-bottom:18px; }
        .doc-title { text-align:center; font-weight:700; color:var(--doc-text); letter-spacing:.06em; }
        .doc-meta { border:1px solid var(--doc-border); border-radius:.375rem; padding:12px; font-size:.925rem; }
        .meta-row { display:flex; justify-content:space-between; margin-bottom:6px; }
        .meta-label { color:var(--doc-muted); }
        .meta-value { color:var(--doc-text); font-weight:600; text-align:right; }
        .section-title { font-weight:700; color:var(--doc-text); margin-bottom:8px; }
        .section-box { border:1px solid var(--doc-border); border-radius:.375rem; padding:12px; }
        .table-doc { width:100%; border-collapse:collapse; font-size:.925rem; }
        .table-doc th, .table-doc td { border:1px solid var(--doc-border); padding:10px 12px; vertical-align:top; }
        .table-doc thead th { background:#f9fafb; color:var(--doc-text); font-weight:700; }
        .table-doc tfoot th, .table-doc tfoot td { background:#fcfcfd; font-weight:700; }
        .doc-ribbon-top { width:100%; padding:10px 14px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#111827; border-bottom:1px solid var(--doc-border); border-top-left-radius:.5rem; border-top-right-radius:.5rem; background:#f3f4f6; }
        .no-print { display: inline-flex; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body * { visibility: hidden; }
            #kt_content, #kt_content * { visibility: visible; }
            #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
            .doc-ribbon-top, .no-print { display:none !important; }
            .card-body { padding: 0 !important; }
            .doc-wrapper { border:none; padding:0; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Detail Perakitan',
        'breadcrumbs' => ['Admin', 'Manajemen Stok', 'Perakitan', 'Detail'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $assembly->status;
            $ribbonBg = 'primary';
            if ($status === 'completed') { $ribbonBg = 'success'; }
            elseif ($status === 'draft') { $ribbonBg = 'warning'; }
            elseif ($status === 'dalam proses') { $ribbonBg = 'primary'; }
            $components = $assembly->components ?? collect();
            $sumQtyRecipes = $components->sum('quantity_recipes');
            $sumQtyPlan = $components->sum('quantity_plan');
            $sumKoliPlan = $components->sum('koli_plan');
            $sumQtyReal = $components->sum('quantity_real');
            $sumKoliReal = $components->sum('koli_real');
            $sumRejected = $components->sum('quantity_rejected');
        @endphp
        <div class="card">
            <div class="doc-ribbon-top d-print-none">
                Status: <span class="badge badge-light-{{ $ribbonBg }}">{{ $status ?: '-' }}</span>
            </div>
            <div class="card-body p-lg-12">
                <div class="doc-wrapper">
                    <div class="doc-header">
                        <div></div>
                        <div>
                            <div class="doc-title fs-2">PERAKITAN</div>
                            <div class="text-center" style="color:var(--doc-muted); font-size:.95rem;">Dokumen Perakitan Barang</div>
                        </div>
                        <div class="doc-meta">
                            <div class="meta-row"><span class="meta-label">Kode</span><span class="meta-value">{{ $assembly->code ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Tanggal</span><span class="meta-value">{{ $assembly->date ? \Carbon\Carbon::parse($assembly->date)->format('d M Y') : '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Gudang</span><span class="meta-value">{{ $assembly->warehouse->name ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Status</span><span class="meta-value">{{ $assembly->status ?? '-' }}</span></div>
                            @if($assembly->completed_at)
                                <div class="meta-row"><span class="meta-label">Tanggal Selesai</span><span class="meta-value">{{ \Carbon\Carbon::parse($assembly->completed_at)->format('d M Y') }}</span></div>
                            @endif
                        </div>
                    </div>

                    <div class="row g-6">
                        <div class="col-md-6">
                            <div class="section-title">Informasi Produk Jadi</div>
                            <div class="section-box">
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">SKU</div>
                                    <div class="col-7 meta-value">{{ $assembly->finishedItem->sku ?? '-' }}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">Nama</div>
                                    <div class="col-7 meta-value">{{ $assembly->finishedItem->nama_barang ?? '-' }}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">Output Rencana</div>
                                    <div class="col-7 meta-value">{{ number_format((float)$assembly->output_quantity_plan, 0, ',', '.') }} Qty • {{ number_format((float)$assembly->output_koli_plan, 2, ',', '.') }} Koli</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">Output Real</div>
                                    <div class="col-7 meta-value">{{ number_format((float)$assembly->output_quantity_real, 0, ',', '.') }} Qty • {{ number_format((float)$assembly->output_koli_real, 2, ',', '.') }} Koli</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-title">Informasi Resep</div>
                            <div class="section-box">
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">Kode Resep</div>
                                    <div class="col-7 meta-value">{{ $assembly->recipe->code ?? '-' }}</div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-5 meta-label">Keterangan</div>
                                    <div class="col-7 meta-value">{{ $assembly->recipe->description ?? '-' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <div class="section-title">Komponen Perakitan</div>
                        <div class="section-box p-0">
                            <table class="table-doc">
                                <thead>
                                    <tr>
                                        <th style="width:36px;">No</th>
                                        <th>Item</th>
                                        <th class="text-end" style="width:120px;">Qty Resep</th>
                                        <th class="text-end" style="width:120px;">Qty Rencana</th>
                                        <th class="text-end" style="width:120px;">Koli Rencana</th>
                                        <th class="text-end" style="width:120px;">Qty Real</th>
                                        <th class="text-end" style="width:120px;">Koli Real</th>
                                        <th class="text-end" style="width:120px;">Reject</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($assembly->components as $i => $c)
                                        <tr>
                                            <td>{{ $i+1 }}</td>
                                            <td>
                                                <div class="fw-bold text-primary">{{ $c->item->sku ?? '-' }}</div>
                                                <div class="text-muted small">{{ $c->item->nama_barang ?? '' }}</div>
                                            </td>
                                            <td class="text-end">{{ number_format((float)$c->quantity_recipes, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format((float)$c->quantity_plan, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format((float)$c->koli_plan, 2, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format((float)$c->quantity_real, 0, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format((float)$c->koli_real, 2, ',', '.') }}</td>
                                            <td class="text-end">{{ number_format((float)$c->quantity_rejected, 0, ',', '.') }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted">Tidak ada komponen</td></tr>
                                    @endforelse
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-end">Total</th>
                                        <th class="text-end">{{ number_format((float)$sumQtyRecipes, 0, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format((float)$sumQtyPlan, 0, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format((float)$sumKoliPlan, 2, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format((float)$sumQtyReal, 0, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format((float)$sumKoliReal, 2, ',', '.') }}</th>
                                        <th class="text-end">{{ number_format((float)$sumRejected, 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="row g-6 mt-8">
                        <div class="col-12">
                            <div class="section-title">Catatan</div>
                            <div class="section-box">{{ $assembly->notes ?? '-' }}</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-8 no-print">
                        <a href="{{ route('admin.manajemenstok.perakitan.index') }}" class="btn btn-light">Kembali</a>
                        <button type="button" class="btn btn-success" id="print_button">Print</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function(){
        var btn = document.getElementById('print_button');
        if (btn) { btn.addEventListener('click', function(){ window.print(); }); }
    });
</script>
@endpush
