<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Spatie\LaravelData\Data;

class CreateNotificationBatchData extends Data
{
    /**
     * @param  list<string>  $recipientIds
     */
    public function __construct(
        public readonly NotificationChannel $channel,
        public readonly NotificationPriority $priority,
        public readonly string $message,
        public readonly array $recipientIds,
        public readonly ?string $idempotencyKey,
    ) {}
}
