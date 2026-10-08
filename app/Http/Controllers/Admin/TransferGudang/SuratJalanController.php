<?php

namespace App\Http\Controllers\Admin\TransferGudang;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentItemDetail;
use App\Models\TransferRequest;
use App\Support\Cbm;
use Illuminate\Http\Request;

class SuratJalanController extends Controller
{
    public function show(TransferRequest $transferRequest)
    {
        $transferRequest->load(['fromWarehouse', 'toWarehouse']);
        $transferRequestItemIds = $transferRequest->items()->pluck('id');

        // Ambil shipment terbaru yang benar-benar memiliki detail dari Transfer Request ini.
        $shipment = Shipment::query()
            ->where('reference_type', Shipment::REFERENCE_TYPE_TRANSFER_REQUEST)
            ->whereHas('itemDetails', function ($q) use ($transferRequestItemIds) {
                $q->where('reference_type', ShipmentItemDetail::REFERENCE_TYPE_TRANSFER_REQUEST_ITEM)
                    ->whereIn('reference_id', $transferRequestItemIds);
            })
            ->with(['itemDetails.item'])
            ->latest('shipping_date')
            ->latest('id')
            ->first();

        if (!$shipment) {
            abort(404, 'Surat jalan belum dibuat untuk permintaan ini.');
        }

        $totalQuantity = $shipment->itemDetails->sum('quantity_shipped');
        $totalKoli = $shipment->itemDetails->sum('koli_shipped');
        $cbmSummary = Cbm::summarize($shipment->itemDetails, fn ($d) => [$d->item, $d->koli_shipped, $d->quantity_shipped]);

        return view('admin.transfergudang.surat-jalan.show', compact('transferRequest', 'shipment', 'totalQuantity', 'totalKoli', 'cbmSummary'));
    }
}
