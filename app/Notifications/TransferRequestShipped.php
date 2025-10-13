<?php

namespace App\Notifications;

use App\Models\Shipment;
use App\Models\TransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TransferRequestShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly ?TransferRequest $transferRequest = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $tr = $this->transferRequest;
        return [
            'event' => 'transfer_request.shipped',
            'shipment_id' => $this->shipment->id,
            'shipment_code' => $this->shipment->code,
            'transfer_request_id' => $tr?->id,
            'transfer_request_code' => $tr?->code,
            'from_warehouse_id' => $tr?->from_warehouse_id,
            'to_warehouse_id' => $tr?->to_warehouse_id,
            'status' => $this->shipment->status,
            'shipping_date' => $this->shipment->shipping_date,
            'message' => sprintf(
                'Pengiriman untuk permintaan transfer %s telah dikirim (DO: %s)',
                $tr?->code ?? '-',
                $this->shipment->code
            ),
        ];
    }
}

