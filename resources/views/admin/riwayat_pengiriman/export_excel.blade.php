@php
    $sortedDetails = $shipment->itemDetails
        ->sortBy(function ($detail) {
            $sku = optional($detail->item)->sku;
            return $sku === null ? '~' : $sku;
        }, SORT_NATURAL | SORT_FLAG_CASE)
        ->values();
    $totQty = (float) ($sortedDetails->sum('quantity_shipped') ?? 0);
    $totKoli = (float) ($sortedDetails->sum('koli_shipped') ?? 0);
@endphp
<table>
    <tr>
        <td colspan="6" style="font-weight:bold; font-size:14pt;">SURAT JALAN</td>
    </tr>
    <tr>
        <td colspan="6" style="font-weight:bold;">NO SJ: {{ $shipment->code ?? '-' }}</td>
    </tr>
    <tr><td colspan="6"></td></tr>
    <tr>
        <td style="width:120px;">Kepada</td>
        <td colspan="2" style="font-weight:bold;">{{ $toWarehouse ?? '-' }}</td>
        <td></td>
        <td style="width:120px;">Dari</td>
        <td style="font-weight:bold;">{{ $fromWarehouse ?? '-' }}</td>
    </tr>
    <tr>
        <td>Tanggal</td>
        <td colspan="2">{{ $shipment->shipping_date ? \Carbon\Carbon::parse($shipment->shipping_date)->format('d M Y') : '-' }}</td>
        <td></td>
        <td>Kendaraan</td>
        <td>{{ $shipment->vehicle_type ?? '-' }}</td>
    </tr>
    <tr>
        <td>No. Polisi</td>
        <td colspan="2">{{ $shipment->license_plate ?? '-' }}</td>
        <td></td>
        <td>Driver</td>
        <td>{{ $shipment->driver_name ?? '-' }}</td>
    </tr>
    @if(!empty($referenceCode))
    <tr>
        <td>Referensi</td>
        <td colspan="5">{{ $referenceCode }}</td>
    </tr>
    @endif
</table>

<table>
    <tr><td colspan="6"></td></tr>
</table>

<table border="1" cellspacing="0" cellpadding="4">
    <thead>
    <tr>
        <th style="width:32px; font-weight:bold;">NO</th>
        <th style="width:100px; font-weight:bold;">SKU</th>
        <th style="font-weight:bold;">NAMA BARANG</th>
        <th colspan="2" style="font-weight:bold; text-align:center; width:200px;">JUMLAH</th>
        <th style="width:120px; font-weight:bold;">KET</th>
    </tr>
    <tr>
        <th></th>
        <th></th>
        <th></th>
        <th style="width:90px; text-align:right; font-weight:bold;">PCS(SET)</th>
        <th style="width:90px; text-align:right; font-weight:bold;">KOLI</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @forelse($sortedDetails as $i => $d)
        <tr>
            <td style="text-align:center;">{{ $i + 1 }}</td>
            <td>{{ optional($d->item)->sku ?? '-' }}</td>
            <td>{{ optional($d->item)->nama_barang ?? ($d->item->name ?? '-') }}</td>
            <td style="text-align:right;">{{ number_format($d->quantity_shipped ?? 0, 0, ',', '.') }}</td>
            <td style="text-align:right;">{{ number_format($d->koli_shipped ?? 0, 2, ',', '.') }}</td>
            <td>{{ $d->description ?? '' }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="6" style="text-align:center;">Tidak ada detail</td>
        </tr>
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

<table>
    <tr><td colspan="5"></td></tr>
    <tr>
        <td style="text-align:center;">Dibuat Oleh</td>
        <td style="text-align:center;">Mengetahui</td>
        <td style="text-align:center;">Driver</td>
        <td style="text-align:center;">Security</td>
        <td style="text-align:center;">Penerima</td>
    </tr>
    <tr>
        <td style="height:60px;"></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
    </tr>
    <tr>
        <td style="border-top:1px solid #000;"></td>
        <td style="border-top:1px solid #000;"></td>
        <td style="border-top:1px solid #000;"></td>
        <td style="border-top:1px solid #000;"></td>
        <td style="border-top:1px solid #000;"></td>
    </tr>
</table>
