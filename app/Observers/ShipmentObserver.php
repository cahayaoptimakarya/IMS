<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\TransferRequest;
use App\Models\User;
use App\Notifications\TransferRequestShipped;

class ShipmentObserver
{
    /**
     * Handle the Shipment "created" event.
     */
    public function created(Shipment $shipment): void
    {
        if ($shipment->reference_type !== Shipment::REFERENCE_TYPE_TRANSFER_REQUEST) {
            return;
        }

        $tr = TransferRequest::find($shipment->reference_id);
        if (!$tr) return;

        // Notify destination warehouse users that shipment is on the way
        $recipients = User::where('warehouse_id', $tr->to_warehouse_id)->get();
        foreach ($recipients as $user) {
            $user->notify(new TransferRequestShipped($shipment, $tr));
        }
    }
}

