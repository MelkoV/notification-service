<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\Actions\SendQueuedNotification;
use App\Services\Notifications\Contracts\NotificationBroker;
use Illuminate\Console\Command;

class ConsumeNotifications extends Command
{
    protected $signature = 'notifications:consume {--limit=0 : Stop after processing this many messages}';

    protected $description = 'Consume queued notifications from RabbitMQ';

    public function handle(NotificationBroker $broker, SendQueuedNotification $sender): int
    {
        $processed = $broker->consume(
            fn (string $notificationId) => $sender->handle($notificationId),
            (int) $this->option('limit'),
        );

        $this->info("Processed {$processed} notification message(s).");

        return self::SUCCESS;
    }
}
