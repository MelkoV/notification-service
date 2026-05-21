<?php

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->enum('channel', array_column(NotificationChannel::cases(), 'value'));
            $table->enum('priority', array_column(NotificationPriority::cases(), 'value'));
            $table->text('message');
            $table->unsignedInteger('recipient_count');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            $table->string('recipient_id');
            $table->enum('channel', array_column(NotificationChannel::cases(), 'value'));
            $table->enum('priority', array_column(NotificationPriority::cases(), 'value'));
            $table->text('message');
            $table->enum('status', array_column(DeliveryStatus::cases(), 'value'))->default(DeliveryStatus::Queued->value);
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'recipient_id']);
            $table->index(['recipient_id', 'created_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_batches');
    }
};
