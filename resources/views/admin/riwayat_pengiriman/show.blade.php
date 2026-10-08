@extends('layouts.app')

@push('styles')
    <style>
        :root { --doc-text:#1f2937; --doc-muted:#6b7280; --doc-border:#d1d5db; }
        .doc-wrapper { background:#fff; border:1px solid var(--doc-border); border-radius:.5rem; padding:16px; }
        .doc-header { display:grid; grid-template-columns: 1fr 1fr 260px; gap:10px; align-items:center; margin-bottom:8px; }
        .doc-title { text-align:left; font-weight:700; color:var(--doc-text); letter-spacing:.04em; font-size:14pt; }
        .doc-meta { border:1px solid #000; border-radius:.375rem; padding:8px; font-size:11pt; }
        .meta-row{ display:flex; justify-content:space-between; margin-bottom:4px; }
        .meta-label{ color:var(--doc-muted); }
        .meta-value{ color:var(--doc-text); font-weight:600; }
        .sj-code{ font-size:11pt; font-weight:800; letter-spacing:.06em; }
        .title-center{ text-align:center; }
        .dest-label, .dest-value { font-size:11pt; }
        .no-sj-inline{ margin-top:4px; display:inline-block; padding:3px 7px; border:1.5px solid #000; border-radius:.25rem; }
        .section-title{ font-weight:700; color:var(--doc-text); margin-bottom:4px; }
        .section-box{ border:1px solid var(--doc-border); border-radius:.375rem; padding:8px; }
        .table-doc{ width:100%; border-collapse:collapse; font-size:11pt; }
        .table-doc th,.table-doc td{ border:1px solid #000; padding:6px 8px; vertical-align:top; word-break:break-word; }
        .table-doc thead th{ background:#f9fafb; color:var(--doc-text); font-weight:700; }
        .table-doc tfoot th,.table-doc tfoot td{ background:#fcfcfd; font-weight:700; }
        .doc-ribbon-top{ width:100%; padding:8px 12px; font-weight:700; letter-spacing:.03em; text-transform:uppercase; color:#111827; border-bottom:1px solid var(--doc-border); border-top-left-radius:.5rem; border-top-right-radius:.5rem; background:#f3f4f6; }
        .no-print{ display:inline-flex; }
        @media print{
            @page { size:A4 portrait; margin:6mm; }
            html, body{ font-size:11pt; }
            body *{ visibility:hidden; }
            #kt_content, #kt_content *{ visibility:visible; }
            #kt_content{ position:absolute; left:0; top:0; width:100%; }
            .no-print, .ribbon-label, .doc-ribbon-top{ display:none !important; }
            .card-body{ padding:0 !important; }
            .doc-wrapper{ border:none; padding:0; }
            .doc-header{ margin-bottom:6px; gap:6px; grid-template-columns: 1fr 1fr 220px; }
            .doc-title{ font-size:14pt; }
            .doc-meta{ padding:5px 6px; font-size:11pt; }
            .sj-code{ font-size:11pt; }
            .meta-row{ margin-bottom:1px; }
            .section-title{ margin:6px 0 3px 0; font-size:11pt; }
            .dest-label, .dest-value { font-size:11pt; }
            .section-box{ padding:6px; }
            .table-doc{ font-size:11pt; }
            .table-doc th,.table-doc td{ padding:3px 5px; }
            .table-doc thead th{ font-size:11pt; }
            .table-doc tfoot th,.table-doc tfoot td{ font-size:11pt; }
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

            $sortedDetails = $shipment->itemDetails
                ->sortBy(function ($detail) {
                    $sku = optional($detail->item)->sku;
                    return $sku === null ? '~' : $sku;
                }, SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
            $totQty = (float) ($sortedDetails->sum('quantity_shipped') ?? 0);
            $totKoli = (float) ($sortedDetails->sum('koli_shipped') ?? 0);
            $cbmSummary = \App\Support\Cbm::summarize($sortedDetails, fn ($d) => [$d->item, $d->koli_shipped, $d->quantity_shipped]);
        @endphp
        <div class="card">
            <div class="doc-ribbon-top d-print-none">
                Status: <span class="badge badge-light-{{ $ribbonBg }}">{{ $status ? ucwords($status) : '-' }}</span>
            </div>
            <div class="card-body p-lg-12">
                <div class="doc-wrapper">
                    <div class="doc-header">
                        <div>
                            <div class="dest-label" style="margin-bottom:2px;">Kepada</div>
                            <div class="meta-value dest-value" style="font-weight:700">{{ $toWarehouse ?? '-' }}</div>
                        </div>
                        <div class="title-center">
                            <div class="doc-title fs-2">SURAT JALAN</div>
                            <div class="no-sj-inline sj-code" style="margin-top:2px;">NO SJ: {{ $shipment->code ?? '-' }}</div>
                        </div>
                        <div class="doc-meta">
                            <div class="meta-row"><span class="meta-label">Dari</span><span class="meta-value" style="font-weight:700">{{ $fromWarehouse ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Tanggal</span><span class="meta-value">{{ $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') : '-' }}</span></div>
                            @isset($requesterName)
                                <div class="meta-row"><span class="meta-label">Requester</span><span class="meta-value">{{ $requesterName ?: '-' }}</span></div>
                            @endisset
                            <div class="meta-row"><span class="meta-label">Kendaraan</span><span class="meta-value">{{ $shipment->vehicle_type ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">No. Polisi</span><span class="meta-value">{{ $shipment->license_plate ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Driver</span><span class="meta-value">{{ $shipment->driver_name ?? '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Total CBM</span><span class="meta-value">{{ \App\Support\Cbm::format($cbmSummary['total']) }} m³</span></div>
                        </div>
                    </div>

                    <div class="section-title">Daftar Barang</div>
                    <div class="table-responsive">
                        <table class="table-doc">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:32px; white-space:nowrap;">NO</th>
                                    <th rowspan="2" style="width:100px">SKU</th>
                                    <th rowspan="2">NAMA BARANG</th>
                                    <th colspan="2" style="text-align:center; width:200px">JUMLAH</th>
                                    <th rowspan="2" style="width:90px; text-align:right; vertical-align:middle;">CBM (m³)</th>
                                    <th rowspan="2" style="width:120px">KET</th>
                                </tr>
                                <tr>
                                    <th style="width:90px; text-align:right;">PCS(SET)</th>
                                    <th style="width:90px; text-align:right;">KOLI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($sortedDetails as $i => $d)
                                    <tr>
                                        <td style="text-align:center;">{{ $i + 1 }}</td>
                                        <td style="white-space:nowrap;">{{ optional($d->item)->sku ?? '-' }}</td>
                                        <td>{{ optional($d->item)->nama_barang ?? ($d->item->name ?? '-') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->quantity_shipped ?? 0, 0, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ number_format($d->koli_shipped ?? 0, 2, ',', '.') }}</td>
                                        <td style="text-align:right;">{{ \App\Support\Cbm::format(\App\Support\Cbm::forLine($d->item, $d->koli_shipped, $d->quantity_shipped)) }}</td>
                                        <td>{{ $d->description ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center" style="color:var(--doc-muted)">Tidak ada detail</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" style="text-align:right;">TOTAL</th>
                                    <th style="text-align:right;">{{ number_format($totQty, 0, ',', '.') }}</th>
                                    <th style="text-align:right;">{{ number_format($totKoli, 2, ',', '.') }}</th>
                                    <th style="text-align:right;">{{ \App\Support\Cbm::format($cbmSummary['total']) }}</th>
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
                        <div class="d-flex gap-2">
                             <a href="{{ route('admin.riwayat-pengiriman.export-excel', $shipment) }}" class="btn btn-primary">
                                    <span class="svg-icon svg-icon-2 me-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.3" x="12.75" y="4.25" width="12" height="2" rx="1" transform="rotate(90 12.75 4.25)" fill="black"></rect>
                                            <path d="M12.0573 6.11875L13.5203 7.87435C13.9121 8.34457 14.6232 8.37683 15.056 7.94401C15.4457 7.5543 15.4641 6.92836 15.0979 6.51643L12.4974 3.59084C12.0996 3.14332 11.4004 3.14332 11.0026 3.59084L8.40206 6.51643C8.0359 6.92836 8.0543 7.5543 8.44401 7.94401C8.87683 8.37683 9.58785 8.34458 9.9797 7.87435L11.4427 6.11875C11.6026 5.92684 11.8974 5.92684 12.0573 6.11875Z" fill="black"></path>
                                            <path d="M18.75 8.25H17.75C17.1977 8.25 16.75 8.69772 16.75 9.25C16.75 9.80228 17.1977 10.25 17.75 10.25C18.3023 10.25 18.75 10.6977 18.75 11.25V18.25C18.75 18.8023 18.3023 19.25 17.75 19.25H5.75C5.19772 19.25 4.75 18.8023 4.75 18.25V11.25C4.75 10.6977 5.19771 10.25 5.75 10.25C6.30229 10.25 6.75 9.80228 6.75 9.25C6.75 8.69772 6.30229 8.25 5.75 8.25H4.75C3.64543 8.25 2.75 9.14543 2.75 10.25V19.25C2.75 20.3546 3.64543 21.25 4.75 21.25H18.75C19.8546 21.25 20.75 20.3546 20.75 19.25V10.25C20.75 9.14543 19.8546 8.25 18.75 8.25Z" fill="#C4C4C4"></path>
                                        </svg>
                                    </span>
                                    Export Excel
                                </a>
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
    </div>
@endsection
