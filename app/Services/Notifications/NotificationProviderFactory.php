<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Services\Notifications\Contracts\NotificationProvider;
use App\Services\Notifications\Providers\MockEmailProvider;
use App\Services\Notifications\Providers\MockSmsProvider;
use InvalidArgumentException;

class NotificationProviderFactory
{
    public function make(NotificationChannel $channel): NotificationProvider
    {
        return match ($channel) {
            NotificationChannel::Email => app(MockEmailProvider::class),
            NotificationChannel::Sms => app(MockSmsProvider::class),
            default => throw new InvalidArgumentException('Unsupported notification channel.'),
        };
    }
}
