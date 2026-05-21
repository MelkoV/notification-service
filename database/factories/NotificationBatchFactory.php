<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationBatch>
 */
class NotificationBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'channel' => NotificationChannel::Email,
            'priority' => NotificationPriority::Marketing,
            'message' => fake()->sentence(),
            'recipient_count' => 1,
        ];
    }
}
