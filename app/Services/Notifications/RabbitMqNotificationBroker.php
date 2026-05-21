<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Services\Notifications\Contracts\NotificationBroker;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;

class RabbitMqNotificationBroker implements NotificationBroker
{
    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function publish(Notification $notification): void
    {
        $channel = $this->channel();

        $message = new AMQPMessage(
            json_encode(['notification_id' => $notification->id], JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'priority' => $notification->priority->brokerPriority(),
            ],
        );

        $channel->basic_publish($message, $this->exchange(), $this->queue());
    }

    public function consume(callable $handler, int $limit = 0): int
    {
        $channel = $this->channel();
        $processed = 0;

        $channel->basic_qos(0, 1, null);
        $channel->basic_consume($this->queue(), '', false, false, false, false, function (AMQPMessage $message) use ($handler, $limit, &$processed): void {
            try {
                $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);

                if (! is_array($payload) || ! isset($payload['notification_id'])) {
                    throw new RuntimeException('Malformed notification message.');
                }

                $handler((string) $payload['notification_id']);
                $message->ack();
            } catch (\Throwable) {
                $message->nack(requeue: true);
            }

            $processed++;

            if ($limit > 0 && $processed >= $limit) {
                $message->getChannel()->stopConsume();
            }
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        return $processed;
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
        $channel->exchange_declare($this->exchange(), 'direct', false, true, false);
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
