<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

use App\Models\Notification;
use App\Services\Notifications\ProviderResult;

interface NotificationProvider
{
    public function send(Notification $notification): ProviderResult;
}
