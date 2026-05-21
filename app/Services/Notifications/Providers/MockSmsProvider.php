<?php

declare(strict_types=1);

namespace App\Services\Notifications\Providers;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationProvider;
use App\Services\Notifications\Exceptions\NotificationProviderException;
use App\Services\Notifications\ProviderResult;

class MockSmsProvider implements NotificationProvider
{
    public function send(Notification $notification): ProviderResult
    {
        if (str_contains($notification->message, '[temporary-fail]')) {
            throw new NotificationProviderException('SMS gateway is temporarily unavailable.');
        }

        if (! preg_match('/^\+?[1-9][0-9]{7,14}$/', $notification->recipient_id)) {
            return ProviderResult::dropped('Invalid phone number.');
        }

        return ProviderResult::delivered('sms-'.$notification->id);
    }
}
