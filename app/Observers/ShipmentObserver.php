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

        // Notify destination warehouse users that shipment is on the way
        $recipients = User::where('warehouse_id', $shipment->to_warehouse_id)->get();
        foreach ($recipients as $user) {
            // TransferRequest context is optional now; pass null
            $user->notify(new TransferRequestShipped($shipment, null));
        }
    }
}
