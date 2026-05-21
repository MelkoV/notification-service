<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationProvider;
use App\Services\Notifications\Exceptions\NotificationProviderException;
use App\Services\Notifications\ProviderResult;

class MockEmailProvider implements NotificationProvider
{
    public function send(Notification $notification): ProviderResult
    {
        if (str_contains($notification->message, '[temporary-fail]')) {
            throw new NotificationProviderException('Email gateway is temporarily unavailable.');
        }

        if (! str_contains($notification->recipient_id, '@') || str_contains($notification->recipient_id, 'invalid')) {
            return ProviderResult::dropped('Invalid email address.');
        }

        return ProviderResult::delivered('email-'.$notification->id);
    }
}
