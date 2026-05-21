<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'channel' => $this->channel->value,
            'priority' => $this->priority->value,
            'message' => $this->message,
            'recipient_count' => $this->recipient_count,
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
