<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationBroker;
use Carbon\CarbonInterface;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;

class RabbitMqNotificationBroker implements NotificationBroker
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function publish(Notification $notification): void
    {
        $this->publishToExchange($notification, $this->exchange());
    }

    public function publishDelayed(Notification $notification, CarbonInterface $retryAt): void
    {
        $delayMs = max(0, ($retryAt->getTimestamp() - now()->getTimestamp()) * 1000);

        $this->publishToExchange(
            $notification,
            $this->exchange(),
            [
                'retry_at' => $retryAt->toISOString(),
            ],
            [
                'application_headers' => new AMQPTable([
                    'x-delay' => $delayMs,
                ]),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $extraPayload
     * @param  array<string, mixed>  $extraProperties
     */
    private function publishToExchange(
        Notification $notification,
        string $exchange,
        array $extraPayload = [],
        array $extraProperties = [],
    ): void {
        $channel = $this->channel();
        $payload = [
            'notification_id' => $notification->id,
            ...$extraPayload,
        ];
        $message = new AMQPMessage(
            json_encode($payload, JSON_THROW_ON_ERROR),
            array_replace([
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => $notification->priority->brokerPriority(),
            ], $extraProperties),
        );

        $channel->basic_publish($message, $exchange, $this->queue());
    }

    public function consume(callable $handler, int $limit = 0): int
    {
        $channel = $this->channel();
        $processed = 0;

        $channel->basic_qos(0, 1, null);

        if ($limit > 0) {
            while ($processed < $limit) {
                $message = $channel->basic_get($this->queue());

                if (! $message instanceof AMQPMessage) {
                    break;
                }

                $this->processMessage($message, $handler);
                $processed++;
            }

            return $processed;
        }

        $channel->basic_consume($this->queue(), '', false, false, false, false, function (AMQPMessage $message) use ($handler, &$processed): void {
            $this->processMessage($message, $handler);
            $processed++;
        });

        while ($channel->is_consuming()) {
            try {
                $channel->wait(timeout: (int) config('notifications.rabbitmq.consume_wait_timeout'));
            } catch (AMQPTimeoutException) {
                break;
            }
        }

        return $processed;
    }

    private function processMessage(AMQPMessage $message, callable $handler): void
    {
        try {
            $notificationId = $this->notificationIdFrom($message);
        } catch (JsonException|RuntimeException) {
            $message->nack(requeue: false);

            return;
        }

        try {
            $result = $handler($notificationId);

            if ($result instanceof SendNotificationResult && $result->shouldRetry()) {
                $this->publishDelayed($result->notification, $result->retryAt);
            }

            $message->ack();
        } catch (\Throwable) {
            $message->nack(requeue: true);
        }
    }

    private function notificationIdFrom(AMQPMessage $message): string
    {
        $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($payload) || ! isset($payload['notification_id'])) {
            throw new RuntimeException('Malformed notification message.');
        }

        return (string) $payload['notification_id'];
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            (string) config('notifications.rabbitmq.host'),
            (int) config('notifications.rabbitmq.port'),
            (string) config('notifications.rabbitmq.user'),
            (string) config('notifications.rabbitmq.password'),
            (string) config('notifications.rabbitmq.vhost'),
        );

        $this->channel = $this->connection->channel();
        $this->declareTopology($this->channel);

        return $this->channel;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $channel->exchange_declare($this->exchange(), 'x-delayed-message', false, true, false, false, false, new AMQPTable([
            'x-delayed-type' => 'direct',
        ]));
        $channel->queue_declare($this->queue(), false, true, false, false, false, new AMQPTable([
            'x-max-priority' => (int) config('notifications.rabbitmq.max_priority'),
        ]));
        $channel->queue_bind($this->queue(), $this->exchange(), $this->queue());
    }

    private function exchange(): string
    {
        return (string) config('notifications.rabbitmq.exchange');
    }

    private function queue(): string
    {
        return (string) config('notifications.rabbitmq.queue');
    }
}
