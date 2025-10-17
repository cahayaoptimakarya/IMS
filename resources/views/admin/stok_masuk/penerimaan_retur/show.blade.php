@extends('layouts.app')

@push('styles')
  <style>
    :root{
      --ink:#111827; --muted:#6B7280; --line:#E5E7EB; --chip:#F3F4F6; --chip-ink:#374151; --chip-success:#ECFDF5; --chip-success-ink:#047857;
    }
    @media print {
      body * { visibility: hidden; }
      #kt_content, #kt_content * { visibility: visible; }
      #kt_content { position: absolute; left: 0; top: 0; width: 100%; }
      .no-print { display: none !important; }
      .card { box-shadow: none !important; }
    }
    .doc-header { border-bottom: 2px solid var(--line); padding-bottom: 16px; margin-bottom: 22px; }
    .brand { display:flex; align-items:center; gap:12px; }
    .brand .logo { width:40px; height:40px; }
    .brand .name { font-weight:800; color:var(--ink); font-size:1.05rem; letter-spacing:.4px; }
    .doc-title { font-size: 26px; font-weight: 800; letter-spacing:.6px; text-transform: uppercase; color:var(--ink); }
    .doc-sub { color: var(--muted); }
    .chip { display:inline-block; padding:.35rem .65rem; border-radius: .5rem; font-weight:700; font-size:.825rem; }
    .chip-default{ background:var(--chip); color:var(--chip-ink); }
    .chip-success{ background:var(--chip-success); color:var(--chip-success-ink); }
    .meta-label { color: var(--muted); font-weight: 600; font-size: .85rem; }
    .meta-value { font-weight: 700; color: var(--ink); }
    .info-box { border: 1px solid #EFF2F5; border-radius: .475rem; padding: 1rem; background:#fff; }
    .section-title { font-weight:700; color:var(--ink); margin-bottom:.5rem; }
    .table-modern thead th{ background:#F9FAFB; border-bottom:1px solid var(--line); text-transform:uppercase; letter-spacing:.4px; font-size:.75rem; }
    .table-modern tbody tr{ border-bottom:1px dashed var(--line); }
    .table-modern tfoot td{ border-top:2px solid var(--line); font-weight:800; }
  </style>
@endpush

@push('toolbar')
  @include('layouts.partials._toolbar', [
    'title' => 'Detail Penerimaan Retur',
    'breadcrumbs' => ['Admin', 'Stok Masuk', 'Penerimaan Retur', 'Detail'],
  ])
@endpush

@section('content')
  <div class="content flex-row-fluid" id="kt_content">
    <div class="card">
      <div class="card-body p-lg-10">
        <div class="doc-header d-flex justify-content-between align-items-center">
          <div class="brand">
            <img class="logo" src="{{ asset('metronic/assets/media/svg/brand-logos/code-lab.svg') }}" alt="Logo">
            <div>
              <div class="doc-title">Penerimaan Retur</div>
              <div class="doc-sub">Dokumen Return Receipt</div>
            </div>
          </div>
          <div class="text-end">
            <div class="meta-label">Kode Dokumen</div>
            <div class="meta-value">{{ $rr->code }}</div>
            <div class="meta-label mt-2">Tanggal</div>
            <div class="meta-value">{{ optional($rr->return_date)->format('d M Y') }}</div>
            @php
              $st = $rr->status;
              $chipClass = 'chip-default';
              if ($st === \App\Models\ReturnReceipt::STATUS_COMPLETED) $chipClass = 'chip-success';
            @endphp
            <div class="mt-3"><span class="chip {{ $chipClass }}">Status: {{ ucwords(str_replace('_',' ',$st)) }}</span></div>
          </div>
        </div>

        <div class="row mb-8">
          <div class="col-md-6">
            <div class="info-box h-100">
              <div class="section-title">Informasi Gudang & Penerima</div>
              <div class="row gy-2">
                <div class="col-5 meta-label">Gudang</div>
                <div class="col-7 meta-value">{{ $rr->warehouse->name ?? '-' }}</div>
                <div class="col-5 meta-label">Diterima Oleh</div>
                <div class="col-7 meta-value">{{ $rr->receiver->name ?? '-' }}</div>
                <div class="col-5 meta-label">Diverifikasi</div>
                <div class="col-7 meta-value">{{ $rr->verifier->name ?? '-' }}</div>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="info-box h-100">
              <div class="section-title">Ringkasan</div>
              @php
                $totQty = $rr->details->sum(function($it){ return (float)($it->quantity ?? 0); });
                $totKoli = $rr->details->sum(function($it){ return (float)($it->koli ?? 0); });
              @endphp
              <div class="row gy-2">
                <div class="col-5 meta-label">Total Item Baris</div>
                <div class="col-7 meta-value">{{ $rr->details->count() }}</div>
                <div class="col-5 meta-label">Total Qty</div>
                <div class="col-7 meta-value">{{ number_format($totQty, 0, ',', '.') }}</div>
                <div class="col-5 meta-label">Total Koli</div>
                <div class="col-7 meta-value">{{ number_format($totKoli, 2, ',', '.') }}</div>
              </div>
            </div>
          </div>
        </div>

        <div class="mb-8">
          <div class="section-title">Daftar Barang</div>
          <div class="table-responsive">
            <table class="table table-row-dashed align-middle table-modern">
              <thead>
                <tr class="text-start text-gray-700 fw-bolder fs-7 text-uppercase gs-0">
                  <th class="w-40px">No</th>
                  <th>Item</th>
                  <th class="w-120px text-end">Qty</th>
                  <th class="w-120px text-end">Koli</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody class="fw-bold text-gray-800">
                @php $sumQty=0; $sumKoli=0; @endphp
                @foreach ($rr->details as $i => $d)
                  @php
                    $sumQty += (float) ($d->quantity ?? 0);
                    $sumKoli += (float) ($d->koli ?? 0);
                  @endphp
                  <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                      <div class="d-flex flex-column">
                        <span class="meta-label">{{ $d->item->sku ?? '-' }}</span>
                        <span class="meta-value">{{ $d->item->nama_barang ?? $d->item->name ?? '-' }}</span>
                      </div>
                    </td>
                    <td class="text-end">{{ number_format((float) ($d->quantity ?? 0), 0, ',', '.') }}</td>
                    <td class="text-end">{{ number_format((float) ($d->koli ?? 0), 2, ',', '.') }}</td>
                    <td>{{ $d->notes ?? '-' }}</td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="fw-bolder">
                  <td colspan="2" class="text-end">Total</td>
                  <td class="text-end">{{ number_format($sumQty, 0, ',', '.') }}</td>
                  <td class="text-end">{{ number_format($sumKoli, 2, ',', '.') }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        @if(!empty($rr->description))
          <div class="mb-8">
            <div class="fw-bold mb-2">Catatan</div>
            <div class="info-box">{{ $rr->description }}</div>
          </div>
        @endif

        <div class="row mt-12 no-print">
          <div class="col-12 d-flex justify-content-end gap-2">
            @if($rr->status === \App\Models\ReturnReceipt::STATUS_COMPLETED)
              <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Bukti</button>
            @endif
            <a href="{{ route('admin.stok-masuk.penerimaan-retur.index') }}" class="btn btn-light">Kembali</a>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
