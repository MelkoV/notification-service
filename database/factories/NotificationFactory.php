<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => NotificationBatch::factory(),
            'recipient_id' => fake()->safeEmail(),
            'channel' => NotificationChannel::Email,
            'priority' => NotificationPriority::Marketing,
            'message' => fake()->sentence(),
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'provider_message_id' => null,
            'last_error' => null,
            'next_retry_at' => null,
            'sent_at' => null,
            'delivered_at' => null,
            'dropped_at' => null,
        ];
    }
}
