<?php

declare(strict_types=1);

namespace App\Services\Notifications\Testing;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationBroker;

class ArrayNotificationBroker implements NotificationBroker
{
    /** @var list<array{id: string, priority: int}> */
    public array $published = [];

    public function publish(Notification $notification): void
    {
        $this->published[] = [
            'id' => $notification->id,
            'priority' => $notification->priority->brokerPriority(),
        ];
    }

    public function consume(callable $handler, int $limit = 0): int
    {
        usort($this->published, fn (array $left, array $right): int => $right['priority'] <=> $left['priority']);

        $processed = 0;

        foreach ($this->published as $message) {
            if ($limit > 0 && $processed >= $limit) {
                break;
            }

            $handler($message['id']);
            $processed++;
        }

        return $processed;
    }
}
