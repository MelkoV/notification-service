<?php

declare(strict_types=1);

namespace App\Services\Notifications\Contracts;

use App\Models\Notification;

interface NotificationBroker
{
    public function publish(Notification $notification): void;

    public function consume(callable $handler, int $limit = 0): int;
}
