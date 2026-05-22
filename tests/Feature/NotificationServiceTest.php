<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Services\Notifications\Actions\SendQueuedNotification;
use App\Services\Notifications\Contracts\NotificationBroker;
use App\Services\Notifications\Testing\ArrayNotificationBroker;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    private ArrayNotificationBroker $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broker = new ArrayNotificationBroker;
        $this->app->instance(NotificationBroker::class, $this->broker);
    }

    public function test_it_accepts_mass_notifications_and_publishes_each_recipient(): void
    {
        $response = $this
            ->withHeader('Idempotency-Key', 'batch-001')
            ->postJson('/api/v1/notifications', [
                'channel' => 'email',
                'priority' => 'marketing',
                'message' => 'Spring campaign',
                'recipient_ids' => ['first@example.com', 'second@example.com'],
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.priority', 'marketing')
            ->assertJsonPath('data.recipient_count', 2)
            ->assertJsonCount(2, 'data.notifications');

        $this->assertSame(2, Notification::query()->count());
        $this->assertCount(2, $this->broker->published);
    }

    public function test_idempotency_key_returns_existing_batch_without_republishing(): void
    {
        $payload = [
            'channel' => 'sms',
            'priority' => 'transactional',
            'message' => 'Your access code is 1234',
            'recipient_ids' => ['+79000000000'],
            'idempotency_key' => 'access-code-1234',
        ];

        $first = $this->postJson('/api/v1/notifications', $payload)->assertAccepted();
        $second = $this->postJson('/api/v1/notifications', $payload)->assertAccepted();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, NotificationBatch::query()->count());
        $this->assertSame(1, Notification::query()->count());
        $this->assertCount(1, $this->broker->published);
    }

    public function test_consumer_sends_notifications_and_exposes_subscriber_history(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Route changed',
            'recipient_ids' => ['driver@example.com'],
            'idempotency_key' => 'route-changed',
        ])->assertAccepted();

        $processed = $this->broker->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
        );

        $this->assertSame(1, $processed);

        $this->getJson('/api/v1/subscribers/driver@example.com/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.status', DeliveryStatus::Delivered->value)
            ->assertJsonPath('data.0.attempts', 1);
    }

    public function test_provider_can_drop_permanently_invalid_recipient(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'priority' => 'marketing',
            'message' => 'Sale',
            'recipient_ids' => ['not-a-phone'],
        ])->assertAccepted();

        $this->broker->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
        );

        $this->assertSame(DeliveryStatus::Dropped, Notification::query()->sole()->status);
    }

    public function test_temporary_provider_error_is_scheduled_for_delayed_retry(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Route changed [temporary-fail]',
            'recipient_ids' => ['driver@example.com'],
        ])->assertAccepted();

        $this->broker->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
        );

        $notification = Notification::query()->sole();

        $this->assertSame(DeliveryStatus::Queued, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->next_retry_at);
        $this->assertCount(1, $this->broker->delayedPublished);
        $this->assertSame($notification->id, $this->broker->delayedPublished[0]['id']);
        $this->assertSame(10, $this->broker->delayedPublished[0]['priority']);
    }

    public function test_temporary_provider_error_drops_after_max_retries_without_delayed_retry(): void
    {
        config()->set('notifications.max_retries', 1);

        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Route changed [temporary-fail]',
            'recipient_ids' => ['driver@example.com'],
        ])->assertAccepted();

        $this->broker->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
        );

        $notification = Notification::query()->sole();

        $this->assertSame(DeliveryStatus::Dropped, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertNull($notification->next_retry_at);
        $this->assertNotNull($notification->dropped_at);
        $this->assertCount(0, $this->broker->delayedPublished);
    }

    public function test_transactional_notifications_are_consumed_before_marketing_notifications(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'marketing',
            'message' => 'Newsletter',
            'recipient_ids' => ['reader@example.com'],
        ])->assertAccepted();

        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Security code',
            'recipient_ids' => ['reader@example.com'],
            'idempotency_key' => 'security-code',
        ])->assertAccepted();

        $this->broker->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
            limit: 1,
        );

        $delivered = Notification::query()
            ->where('status', DeliveryStatus::Delivered)
            ->sole();

        $this->assertSame('transactional', $delivered->priority->value);
        $this->assertSame('Security code', $delivered->message);
    }
}
