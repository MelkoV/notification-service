<?php

declare(strict_types=1);

namespace App\Services\Notifications\Actions;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Services\Notifications\Exceptions\NotificationProviderException;
use App\Services\Notifications\NotificationProviderFactory;
use Illuminate\Support\Facades\DB;

readonly class SendQueuedNotification
{
    public function __construct(private NotificationProviderFactory $providers) {}

    public function handle(string $notificationId): void
    {
        $notification = Notification::query()->findOrFail($notificationId);

        if (in_array($notification->status, [DeliveryStatus::Delivered, DeliveryStatus::Dropped], true)) {
            return;
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
        } catch (NotificationProviderException $exception) {
            $attempts = $notification->refresh()->attempts;

            $notification->forceFill([
                'status' => $attempts >= $maxRetries ? DeliveryStatus::Dropped : DeliveryStatus::Queued,
                'last_error' => $exception->getMessage(),
                'next_retry_at' => $attempts >= $maxRetries ? null : now()->addSeconds((int) config('notifications.retry_delay_seconds')),
                'dropped_at' => $attempts >= $maxRetries ? now() : null,
            ])->save();

            throw $exception;
        }
    }
}
