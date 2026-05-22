<?php

declare(strict_types=1);

namespace App\Services\Notifications\Actions;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Services\Notifications\Exceptions\NotificationProviderException;
use App\Services\Notifications\NotificationProviderFactory;
use App\Services\Notifications\SendNotificationResult;
use Illuminate\Support\Facades\DB;

readonly class SendQueuedNotification
{
    public function __construct(private NotificationProviderFactory $providers) {}

    public function handle(string $notificationId): SendNotificationResult
    {
        $notification = Notification::query()->findOrFail($notificationId);

        if (in_array($notification->status, [DeliveryStatus::Delivered, DeliveryStatus::Dropped], true)) {
            return SendNotificationResult::finished($notification);
        }

        $maxRetries = (int) config('notifications.max_retries');

        try {
            DB::transaction(function () use ($notification): void {
                $notification->forceFill([
                    'status' => DeliveryStatus::Sent,
                    'attempts' => $notification->attempts + 1,
                    'sent_at' => $notification->sent_at ?? now(),
                    'last_error' => null,
                ])->save();
            });

            $result = $this->providers->make($notification->channel)->send($notification->refresh());

            $notification->forceFill([
                'status' => $result->status,
                'provider_message_id' => $result->providerMessageId,
                'last_error' => $result->error,
                'delivered_at' => $result->status === DeliveryStatus::Delivered ? now() : null,
                'dropped_at' => $result->status === DeliveryStatus::Dropped ? now() : null,
            ])->save();

            return SendNotificationResult::finished($notification->refresh());
        } catch (NotificationProviderException $exception) {
            $attempts = $notification->refresh()->attempts;
            $retryAt = $attempts >= $maxRetries ? null : now()->addSeconds((int) config('notifications.retry_delay_seconds'));

            $notification->forceFill([
                'status' => $attempts >= $maxRetries ? DeliveryStatus::Dropped : DeliveryStatus::Queued,
                'last_error' => $exception->getMessage(),
                'next_retry_at' => $retryAt,
                'dropped_at' => $attempts >= $maxRetries ? now() : null,
            ])->save();

            $notification = $notification->refresh();

            if ($retryAt === null) {
                return SendNotificationResult::finished($notification);
            }

            return SendNotificationResult::retry($notification, $retryAt);
        }
    }
}
