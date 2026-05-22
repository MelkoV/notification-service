<?php

declare(strict_types=1);

namespace App\Services\Notifications\Testing;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationBroker;
use App\Services\Notifications\SendNotificationResult;
use Carbon\CarbonInterface;

class ArrayNotificationBroker implements NotificationBroker
{
    /** @var list<array{id: string, priority: int}> */
    public array $published = [];

    /** @var list<array{id: string, priority: int, retry_at: string}> */
    public array $delayedPublished = [];

    public function publish(Notification $notification): void
    {
        $this->published[] = [
            'id' => $notification->id,
            'priority' => $notification->priority->brokerPriority(),
        ];
    }

    public function publishDelayed(Notification $notification, CarbonInterface $retryAt): void
    {
        $this->delayedPublished[] = [
            'id' => $notification->id,
            'priority' => $notification->priority->brokerPriority(),
            'retry_at' => $retryAt->toISOString(),
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

            $result = $handler($message['id']);

            if ($result instanceof SendNotificationResult && $result->shouldRetry()) {
                $this->publishDelayed($result->notification, $result->retryAt);
            }

            $processed++;
        }

        return $processed;
    }
}
