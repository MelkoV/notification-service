<?php

declare(strict_types=1);

namespace App\Services\Notifications\Actions;

use App\Data\CreateNotificationBatchData;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Services\Notifications\Contracts\NotificationBroker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

readonly class CreateNotificationBatch
{
    public function __construct(private NotificationBroker $broker) {}

    public function handle(CreateNotificationBatchData $data): NotificationBatch
    {
        $recipientIds = array_values(array_unique($data->recipientIds));
        $idempotencyKey = $data->idempotencyKey ?? $this->fingerprint($data->channel, $data->priority, $data->message, $recipientIds);

        if ($batchId = Cache::get($this->cacheKey($idempotencyKey))) {
            $batch = NotificationBatch::query()->with('notifications')->find($batchId);

            if ($batch instanceof NotificationBatch) {
                return $batch;
            }
        }

        $created = false;

        $batch = DB::transaction(function () use ($data, $recipientIds, $idempotencyKey, &$created): NotificationBatch {
            $existing = NotificationBatch::query()
                ->where('idempotency_key', $idempotencyKey)
                ->with('notifications')
                ->first();

            if ($existing instanceof NotificationBatch) {
                return $existing;
            }

            $batch = NotificationBatch::query()->create([
                'idempotency_key' => $idempotencyKey,
                'channel' => $data->channel,
                'priority' => $data->priority,
                'message' => $data->message,
                'recipient_count' => count($recipientIds),
            ]);
            $created = true;

            foreach ($recipientIds as $recipientId) {
                $batch->notifications()->create([
                    'recipient_id' => $recipientId,
                    'channel' => $data->channel,
                    'priority' => $data->priority,
                    'message' => $data->message,
                    'status' => DeliveryStatus::Queued,
                ]);
            }

            return $batch->load('notifications');
        });

        Cache::put($this->cacheKey($idempotencyKey), $batch->id, now()->addSeconds((int) config('notifications.idempotency_ttl')));

        if ($created) {
            $batch->notifications
                ->filter(fn (Notification $notification): bool => $notification->status === DeliveryStatus::Queued)
                ->each(fn (Notification $notification) => $this->broker->publish($notification));
        }

        return $batch->load('notifications');
    }

    /**
     * @param  list<string>  $recipientIds
     */
    private function fingerprint(
        NotificationChannel $channel,
        NotificationPriority $priority,
        string $message,
        array $recipientIds,
    ): string {
        sort($recipientIds);

        return hash('sha256', json_encode([
            'channel' => $channel->value,
            'priority' => $priority->value,
            'message' => $message,
            'recipient_ids' => $recipientIds,
        ], JSON_THROW_ON_ERROR));
    }

    private function cacheKey(string $idempotencyKey): string
    {
        return 'notification-idempotency:'.$idempotencyKey;
    }
}
