<?php

namespace App\Notifications;

use App\Models\TransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferRequestCreated extends Notification
{
    use Queueable;

    public function __construct(
        public readonly TransferRequest $transferRequest
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'transfer_request.created',
            'transfer_request_id' => $this->transferRequest->id,
            'code' => $this->transferRequest->code,
            'from_warehouse_id' => $this->transferRequest->from_warehouse_id,
            'to_warehouse_id' => $this->transferRequest->to_warehouse_id,
            'status' => $this->transferRequest->status,
            'requested_by' => $this->transferRequest->requested_by,
            'message' => sprintf(
                'Permintaan transfer %s dibuat untuk gudang tujuan #%d',
                $this->transferRequest->code,
                $this->transferRequest->to_warehouse_id
            ),
        ];
    }
}
