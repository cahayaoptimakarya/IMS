@extends('layouts.app')

@push('styles')
    <style>
        :root { --doc-text:#1f2937; --doc-muted:#6b7280; --doc-border:#d1d5db; }
        .doc-wrapper { background:#fff; border:1px solid var(--doc-border); border-radius:.5rem; padding:22px; }
        .doc-header { display:grid; grid-template-columns: 1fr 220px; gap:12px; align-items:center; margin-bottom:12px; }
        .doc-title { text-align:left; font-weight:700; color:var(--doc-text); letter-spacing:.04em; }
        .doc-meta { border:1px solid var(--doc-border); border-radius:.375rem; padding:10px; font-size:.9rem; }
        .meta-row{ display:flex; justify-content:space-between; margin-bottom:4px; }
        .meta-label{ color:var(--doc-muted); }
        .meta-value{ color:var(--doc-text); font-weight:600; }
        .section-title{ font-weight:700; color:var(--doc-text); margin-bottom:6px; }
        .section-box{ border:1px solid var(--doc-border); border-radius:.375rem; padding:10px; }
        .table-doc{ width:100%; border-collapse:collapse; font-size:.9rem; }
        .table-doc th,.table-doc td{ border:1px solid var(--doc-border); padding:8px 10px; vertical-align:top; word-break:break-word; }
        .table-doc thead th{ background:#f9fafb; color:var(--doc-text); font-weight:700; }
        .table-doc tfoot th,.table-doc tfoot td{ background:#fcfcfd; font-weight:700; }
        .doc-ribbon-top{ width:100%; padding:8px 12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#111827; border-bottom:1px solid var(--doc-border); border-top-left-radius:.5rem; border-top-right-radius:.5rem; background:#f3f4f6; }
        .no-print{ display:inline-flex; }
        @media print{
            @page { size:A4 portrait; margin:8mm; }
            html, body{ font-size:11px; }
            body *{ visibility:hidden; }
            #kt_content, #kt_content *{ visibility:visible; }
            #kt_content{ position:absolute; left:0; top:0; width:100%; }
            .no-print, .ribbon-label, .doc-ribbon-top{ display:none !important; }
            .card-body{ padding:0 !important; }
            .doc-wrapper{ border:none; padding:0; }
            .doc-header{ margin-bottom:8px; gap:8px; }
            .doc-title{ font-size:1.1rem; }
            .doc-meta{ padding:6px 8px; font-size:.8rem; }
            .meta-row{ margin-bottom:2px; }
            .section-title{ margin:8px 0 4px 0; font-size:1rem; }
            .section-box{ padding:8px; }
            .table-doc{ font-size:.8rem; }
            .table-doc th,.table-doc td{ padding:4px 6px; }
            .table-doc thead th{ font-size:.85rem; }
            .table-doc tfoot th,.table-doc tfoot td{ font-size:.85rem; }
            .section-box, .doc-meta{ page-break-inside: avoid; }
            table{ page-break-inside:auto; }
            tr{ page-break-inside:avoid; page-break-after:auto; }
        }
    </style>
@endpush

@push('toolbar')
    @include('layouts.partials._toolbar', [
        'title' => 'Surat Jalan',
        'breadcrumbs' => ['Admin', 'Riwayat Pengiriman', 'Surat Jalan'],
    ])
@endpush

@section('content')
    <div class="content flex-row-fluid" id="kt_content">
        @php
            $status = $shipment->status;
            $ribbonBg = 'primary';
            if (strtolower((string)$status) === 'selesai' || strtolower((string)$status) === 'completed') { $ribbonBg = 'success'; }
            elseif (strtolower((string)$status) === 'dalam perjalanan' || strtolower((string)$status) === 'on_progress') { $ribbonBg = 'warning'; }
            elseif (strtolower((string)$status) === 'packing') { $ribbonBg = 'info'; }
        @endphp
        <div class="card">
            <div class="doc-ribbon-top d-print-none">
                Status: <span class="badge badge-light-{{ $ribbonBg }}">{{ $status ? ucwords($status) : '-' }}</span>
            </div>
            <div class="card-body p-lg-12">
                <div class="doc-wrapper">
                    <div class="doc-header">
                        <div>
                            <div class="doc-title fs-2">PEMINDAHAN BARANG</div>
                            <div class="section-box" style="margin-top:8px;">
                                <div class="meta-row"><span class="meta-label">Kepada :</span><span class="meta-value"></span></div>
                                <div class="meta-row"><span class="meta-label"></span><span class="meta-value" style="font-weight:700">{{ $toWarehouse ?? '-' }}</span></div>
                            </div>
                        </div>
                        <div class="doc-meta">
                            <div class="meta-row"><span class="meta-label">Dari</span><span class="meta-value" style="font-weight:700">{{ $fromWarehouse ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Nomor SJ</span><span class="meta-value">{{ $shipment->code ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Tanggal</span><span class="meta-value">{{ $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') : '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Pengirim</span><span class="meta-value">{{ $shipment->vehicle_type ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">No. Polisi</span><span class="meta-value">{{ $shipment->license_plate ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Driver</span><span class="meta-value">{{ $shipment->driver_name ?? '-' }}</span></div>
                        </div>
                    </div>

                    @php
                        $totQty = (float) ($shipment->itemDetails->sum('quantity_shipped') ?? 0);
                        $totKoli = (float) ($shipment->itemDetails->sum('koli_shipped') ?? 0);
                    @endphp
                    <div class="section-title">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table-doc">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:32px">NO</th>
                                    <th rowspan="2" style="width:100px">SKU</th>
                                    <th rowspan="2">NAMA BARANG</th>
                                    <th colspan="2" style="text-align:center; width:200px">JUMLAH</th>
                                    <th rowspan="2" style="width:120px">KET</th>
                                </tr>
                                <tr>
                                    <th style="width:90px; text-align:right;">PCS(SET)</th>
                                    <th style="width:90px; text-align:right;">KOLI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shipment->itemDetails as $i => $d)
                                    <tr>
                                        <td style="text-align:center;">{{ $i + 1 }}</td>
                                        <td style="white-space:nowrap;">{{ optional($d->item)->sku ?? '-' }}</td>
                                        <td>{{ optional($d->item)->nama_barang ?? ($d->item->name ?? '-') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->quantity_shipped ?? 0, 0, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->koli_shipped ?? 0, 2, ',', '.') }}</td>
                                        <td>{{ $d->description ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center" style="color:var(--doc-muted)">Tidak ada detail</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align:right;">TOTAL</th>
                                    <th style="text-align:right;">{{ number_format($totQty, 0, ',', '.') }}</th>
                                    <th style="text-align:right;">{{ number_format($totKoli, 2, ',', '.') }}</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="row g-6 mt-6">
                        <div class="col-12">
                            <table class="table-doc" style="border-top:none;">
                                <tbody>
                                <tr>
                                    <td style="border:none; text-align:center; height:60px;">Dibuat Oleh</td>
                                    <td style="border:none; text-align:center; height:60px;">Mengetahui</td>
                                    <td style="border:none; text-align:center; height:60px;">Driver</td>
                                    <td style="border:none; text-align:center; height:60px;">Security</td>
                                    <td style="border:none; text-align:center; height:60px;">Penerima</td>
                                </tr>
                                <tr>
                                    <td style="border:none; padding-top:36px;"><div style="border-top:1px solid var(--doc-border); width:90%; margin:0 auto;"></div></td>
                                    <td style="border:none; padding-top:36px;"><div style="border-top:1px solid var(--doc-border); width:90%; margin:0 auto;"></div></td>
                                    <td style="border:none; padding-top:36px;"><div style="border-top:1px solid var(--doc-border); width:90%; margin:0 auto;"></div></td>
                                    <td style="border:none; padding-top:36px;"><div style="border-top:1px solid var(--doc-border); width:90%; margin:0 auto;"></div></td>
                                    <td style="border:none; padding-top:36px;"><div style="border-top:1px solid var(--doc-border); width:90%; margin:0 auto;"></div></td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @php $isCompleted = in_array(strtolower((string)$shipment->status), ['selesai','completed']); @endphp
                    <div class="d-flex justify-content-between mt-4 no-print">
                        <a href="{{ route('admin.riwayat-pengiriman.index') }}" class="btn btn-light">Kembali</a>
                        @if(!$isCompleted)
                            <button type="button" class="btn btn-success" onclick="window.print()">Cetak</button>
                        @else
                            <button type="button" class="btn btn-secondary" disabled title="Dokumen completed; tidak bisa cetak surat jalan">Cetak</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
