<?php

namespace App\Notifications;

use App\Models\GoodsReceipt;
use App\Models\Shipment;
use App\Models\TransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TransferRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly GoodsReceipt $goodsReceipt,
        public readonly ?Shipment $shipment = null,
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
            'event' => 'transfer_request.received',
            'goods_receipt_id' => $this->goodsReceipt->id,
            'goods_receipt_code' => $this->goodsReceipt->code ?? null,
            'shipment_id' => $this->shipment?->id,
            'shipment_code' => $this->shipment?->code,
            'transfer_request_id' => $tr?->id,
            'transfer_request_code' => $tr?->code,
            'from_warehouse_id' => $tr?->from_warehouse_id,
            'to_warehouse_id' => $tr?->to_warehouse_id,
            'status' => $this->goodsReceipt->status,
            'receipt_date' => $this->goodsReceipt->receipt_date,
            'message' => sprintf(
                'Penerimaan barang untuk permintaan transfer %s telah diterima',
                $tr?->code ?? '-'
            ),
        ];
    }
}

