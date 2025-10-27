@extends('layouts.app')

@push('styles')
<style>
  @media print { body *{visibility:hidden} #kt_content, #kt_content *{visibility:visible} #kt_content{position:absolute;left:0;top:0;width:100%} .no-print{display:none!important} .card{box-shadow:none!important}}
  .doc-title{font-size:26px;font-weight:800;letter-spacing:.6px;text-transform:uppercase}
  .meta-label{color:#6B7280;font-weight:600;font-size:.85rem}
  .meta-value{font-weight:700;color:#111827}
  .table-modern thead th{background:#F9FAFB;border-bottom:1px solid #E5E7EB;text-transform:uppercase;letter-spacing:.4px;font-size:.75rem}
  .table-modern tbody tr{border-bottom:1px dashed #E5E7EB}
  .signature{height:90px;border-top:1px dashed #cbd5e1;margin-top:40px;text-align:center;padding-top:8px}
</style>
@endpush

@push('toolbar')
  @include('layouts.partials._toolbar', [
    'title' => 'Surat Jalan Retur Out',
    'breadcrumbs' => ['Admin','Stok Keluar','Retur Out','Surat Jalan'],
  ])
@endpush

@section('content')
<div class="content flex-row-fluid" id="kt_content">
  <div class="card">
    <div class="card-body p-10">
      <div class="d-flex justify-content-between align-items-start mb-6">
        <div>
          <div class="doc-title">Surat Jalan Retur</div>
          <div class="text-muted">Kode: {{ $ri->code }}</div>
        </div>
        <div class="text-end">
          <div class="meta-label">Tanggal</div>
          <div class="meta-value">{{ optional($ri->return_date)->format('d M Y') }}</div>
        </div>
      </div>

      <div class="row mb-8">
        <div class="col-md-6">
          <div class="meta-label">Dari (Gudang Asal)</div>
          <div class="meta-value">{{ $ri->warehouse->name ?? '-' }}</div>
        </div>
        <div class="col-md-6">
          <div class="meta-label">Kepada (Gudang Tujuan)</div>
          <div class="meta-value">{{ $ri->destinationWarehouse->name ?? '-' }}</div>
        </div>
      </div>

      <div class="table-responsive mb-8">
        <table class="table align-middle table-modern">
          <thead>
            <tr>
              <th class="w-40px">No</th>
              <th>SKU</th>
              <th>Nama Barang</th>
              <th class="text-end w-120px">Qty</th>
              <th class="text-end w-120px">Koli</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            @php $tq=0;$tk=0; @endphp
            @foreach($ri->details as $i=>$d)
              @php $tq+=(float)($d->quantity??0); $tk+=(float)($d->koli??0); @endphp
              <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $d->item->sku ?? '-' }}</td>
                <td>{{ $d->item->nama_barang ?? $d->item->name ?? '-' }}</td>
                <td class="text-end">{{ number_format((float)($d->quantity??0),0,',','.') }}</td>
                <td class="text-end">{{ number_format((float)($d->koli??0),2,',','.') }}</td>
                <td>{{ $d->notes ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end fw-bold">Total</td>
              <td class="text-end fw-bold">{{ number_format($tq,0,',','.') }}</td>
              <td class="text-end fw-bold">{{ number_format($tk,2,',','.') }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="row mt-10">
        <div class="col-md-4 text-center">
          <div class="meta-label mb-2">Pengirim</div>
          <div class="signature">( {{ $ri->sender->name ?? '________________' }} )</div>
        </div>
        <div class="col-md-4 text-center">
          <div class="meta-label mb-2">Penerima</div>
          <div class="signature">( ____________________ )</div>
        </div>
        <div class="col-md-4 text-center">
          <div class="meta-label mb-2">Mengetahui</div>
          <div class="signature">( ____________________ )</div>
        </div>
      </div>

      <div class="mt-8 no-print d-flex justify-content-end">
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i> Cetak</button>
      </div>
    </div>
  </div>
</div>
@endsection

