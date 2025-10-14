<?php

namespace App\Notifications;

use App\Models\TransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransferRequestApproved extends Notification
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
            'event' => 'transfer_request.approved',
            'transfer_request_id' => $this->transferRequest->id,
            'code' => $this->transferRequest->code,
            'from_warehouse_id' => $this->transferRequest->from_warehouse_id,
            'to_warehouse_id' => $this->transferRequest->to_warehouse_id,
            'status' => $this->transferRequest->status,
            'approved_by' => $this->transferRequest->approved_by,
            'approved_at' => $this->transferRequest->approved_at,
            'message' => sprintf(
                'Permintaan transfer %s telah disetujui.',
                $this->transferRequest->code,
            ),
        ];
    }
}

