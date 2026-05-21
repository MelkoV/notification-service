<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $batch_id
 * @property string $recipient_id
 * @property NotificationChannel $channel
 * @property NotificationPriority $priority
 * @property string $message
 * @property DeliveryStatus $status
 * @property int $attempts
 * @property string|null $provider_message_id
 * @property string|null $last_error
 * @property CarbonImmutable|null $next_retry_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $dropped_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read NotificationBatch $batch
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient_id',
        'channel',
        'priority',
        'message',
        'status',
        'attempts',
        'provider_message_id',
        'last_error',
        'next_retry_at',
        'sent_at',
        'delivered_at',
        'dropped_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'priority' => NotificationPriority::class,
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }
}
