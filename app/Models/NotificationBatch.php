<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Carbon\CarbonImmutable;
use Database\Factories\NotificationBatchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $idempotency_key
 * @property NotificationChannel $channel
 * @property NotificationPriority $priority
 * @property string $message
 * @property int $recipient_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Notification> $notifications
 */
class NotificationBatch extends Model
{
    /** @use HasFactory<NotificationBatchFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'channel',
        'priority',
        'message',
        'recipient_count',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'priority' => NotificationPriority::class,
            'recipient_count' => 'integer',
        ];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
