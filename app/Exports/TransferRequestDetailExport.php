<?php

namespace App\Exports;

use App\Models\TransferRequest;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TransferRequestDetailExport implements FromArray, ShouldAutoSize
{
    public function __construct(private readonly TransferRequest $transferRequest)
    {
    }

    public function array(): array
    {
        $tr = $this->transferRequest;

        $rows = [];
        $rows[] = ['Kode', $tr->code ?? '-'];
        $rows[] = ['Tanggal', $tr->date ? optional($tr->date)->format('Y-m-d') : '-'];
        $rows[] = ['Diminta Oleh', optional($tr->requester)->name ?? '-'];
        $rows[] = ['Gudang Asal', optional($tr->fromWarehouse)->name ?? '-'];
        $rows[] = ['Gudang Tujuan', optional($tr->toWarehouse)->name ?? '-'];
        $rows[] = ['Status', $tr->status ?? '-'];
        $rows[] = [];

        $rows[] = ['No', 'SKU', 'Nama Item', 'Kuantitas', 'Koli', 'Deskripsi Item'];

        $totalQty = 0;
        $totalKoli = 0;
        foreach ($tr->items as $index => $item) {
            $qty = (float) ($item->quantity ?? 0);
            $koli = (float) ($item->koli ?? 0);
            $totalQty += $qty;
            $totalKoli += $koli;

            $rows[] = [
                $index + 1,
                optional($item->item)->sku ?? '-',
                optional($item->item)->nama_barang ?? '-',
                $qty,
                $koli,
                $item->description ?? '',
            ];
        }

        $rows[] = ['', '', 'Total', $totalQty, $totalKoli, ''];

        return $rows;
    }
}
