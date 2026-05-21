<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\DeliveryStatus;

readonly class ProviderResult
{
    public function __construct(
        public DeliveryStatus $status,
        public ?string $providerMessageId = null,
        public ?string $error = null,
    ) {}

    public static function delivered(string $providerMessageId): self
    {
        return new self(DeliveryStatus::Delivered, $providerMessageId);
    }

    public static function dropped(string $error): self
    {
        return new self(DeliveryStatus::Dropped, null, $error);
    }
}
