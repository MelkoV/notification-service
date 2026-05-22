<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Notification;
use App\Services\Notifications\Actions\SendQueuedNotification;
use App\Services\Notifications\Contracts\NotificationBroker;
use App\Services\Notifications\RabbitMqNotificationBroker;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

class RabbitMqNotificationBrokerIntegrationTest extends TestCase
{
    private string $exchange;

    private string $queue;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RABBITMQ_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('RabbitMQ integration tests are disabled. Set RABBITMQ_INTEGRATION_TESTS=true to run them.');
        }

        if (! $this->canConnectToRabbitMq()) {
            $this->markTestSkipped('RabbitMQ is not reachable.');
        }

        $suffix = str_replace('.', '', uniqid('', true));
        $this->exchange = 'notifications.test.'.$suffix;
        $this->queue = 'notifications.test.priority.'.$suffix;

        config()->set('notifications.rabbitmq.exchange', $this->exchange);
        config()->set('notifications.rabbitmq.queue', $this->queue);
        config()->set('notifications.rabbitmq.consume_wait_timeout', 3);
        config()->set('notifications.retry_delay_seconds', 1);

        $this->app->forgetInstance(NotificationBroker::class);
        $this->app->singleton(NotificationBroker::class, RabbitMqNotificationBroker::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->exchange, $this->queue) && $this->canConnectToRabbitMq()) {
            $this->deleteRabbitMqTopology();
        }

        parent::tearDown();
    }

    public function test_real_rabbitmq_consumer_sends_notification_and_updates_database_status(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Route changed',
            'recipient_ids' => [$this->recipientEmail('rabbitmq-driver')],
            'idempotency_key' => $this->idempotencyKey('rabbitmq-route-changed'),
        ])->assertAccepted();

        $notificationId = $response->json('data.notifications.0.id');

        $processed = app(NotificationBroker::class)->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
            limit: 1,
        );

        $notification = Notification::query()->findOrFail($notificationId);

        $this->assertSame(1, $processed);
        $this->assertSame(DeliveryStatus::Delivered, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->provider_message_id);
    }

    public function test_real_rabbitmq_delayed_retry_requeues_notification_after_delay(): void
    {
        config()->set('notifications.max_retries', 2);

        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'priority' => 'transactional',
            'message' => 'Route changed [temporary-fail]',
            'recipient_ids' => [$this->recipientEmail('rabbitmq-retry-driver')],
            'idempotency_key' => $this->idempotencyKey('rabbitmq-temporary-fail'),
        ])->assertAccepted();

        $notificationId = $response->json('data.notifications.0.id');

        app(NotificationBroker::class)->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
            limit: 1,
        );

        $notification = Notification::query()->findOrFail($notificationId);

        $this->assertSame(DeliveryStatus::Queued, $notification->status);
        $this->assertSame(1, $notification->attempts);
        $this->assertNotNull($notification->next_retry_at);

        sleep(2);

        app(NotificationBroker::class)->consume(
            fn (string $notificationId) => app(SendQueuedNotification::class)->handle($notificationId),
            limit: 1,
        );

        $notification->refresh();

        $this->assertSame(DeliveryStatus::Dropped, $notification->status);
        $this->assertSame(2, $notification->attempts);
        $this->assertNull($notification->next_retry_at);
        $this->assertNotNull($notification->dropped_at);
    }

    private function idempotencyKey(string $prefix): string
    {
        return $prefix.'-'.str_replace('.', '', uniqid('', true));
    }

    private function recipientEmail(string $prefix): string
    {
        return $prefix.'+'.str_replace('.', '', uniqid('', true)).'@example.com';
    }

    private function canConnectToRabbitMq(): bool
    {
        $connection = @fsockopen(
            (string) config('notifications.rabbitmq.host'),
            (int) config('notifications.rabbitmq.port'),
            $errorCode,
            $errorMessage,
            1,
        );

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }

    private function deleteRabbitMqTopology(): void
    {
        $connection = new AMQPStreamConnection(
            (string) config('notifications.rabbitmq.host'),
            (int) config('notifications.rabbitmq.port'),
            (string) config('notifications.rabbitmq.user'),
            (string) config('notifications.rabbitmq.password'),
            (string) config('notifications.rabbitmq.vhost'),
        );

        $channel = $connection->channel();
        $channel->queue_delete($this->queue, false, false, true);
        $channel->exchange_delete($this->exchange, false, true);
        $channel->close();
        $connection->close();
    }
}
