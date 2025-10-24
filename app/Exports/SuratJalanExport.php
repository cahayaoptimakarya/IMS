<?php

namespace App\Exports;

use App\Models\Shipment;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SuratJalanExport implements FromView, ShouldAutoSize
{
    public function __construct(
        private readonly Shipment $shipment,
        private readonly ?string $referenceCode,
        private readonly ?string $fromWarehouse,
        private readonly ?string $toWarehouse,
    ) {}

    public function view(): View
    {
        // Ensure item relations are available
        $this->shipment->loadMissing(['itemDetails.item']);

        return view('admin.riwayat_pengiriman.export_excel', [
            'shipment' => $this->shipment,
            'referenceCode' => $this->referenceCode,
            'fromWarehouse' => $this->fromWarehouse,
            'toWarehouse' => $this->toWarehouse,
        ]);
    }
}

