<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use Carbon\CarbonInterface;

readonly class SendNotificationResult
{
    private function __construct(
        public Notification $notification,
        public ?CarbonInterface $retryAt = null,
    ) {}

    public static function finished(Notification $notification): self
    {
        return new self($notification);
    }

    public static function retry(Notification $notification, CarbonInterface $retryAt): self
    {
        return new self($notification, $retryAt);
    }

    public function shouldRetry(): bool
    {
        return $this->retryAt instanceof CarbonInterface;
    }
}
