<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'recipient_id' => $this->recipient_id,
            'channel' => $this->channel->value,
            'priority' => $this->priority->value,
            'message' => $this->message,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'provider_message_id' => $this->provider_message_id,
            'last_error' => $this->last_error,
            'history' => [
                'queued_at' => $this->created_at?->toISOString(),
                'sent_at' => $this->sent_at?->toISOString(),
                'delivered_at' => $this->delivered_at?->toISOString(),
                'dropped_at' => $this->dropped_at?->toISOString(),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
