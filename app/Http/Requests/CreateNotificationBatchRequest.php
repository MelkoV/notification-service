<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\CreateNotificationBatchData;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNotificationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'priority' => ['required', Rule::enum(NotificationPriority::class)],
            'message' => ['required', 'string', 'max:5000'],
            'recipient_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'recipient_ids.*' => ['required', 'string', 'distinct', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDto(): CreateNotificationBatchData
    {
        $validated = $this->validated();

        return new CreateNotificationBatchData(
            channel: NotificationChannel::from($validated['channel']),
            priority: NotificationPriority::from($validated['priority']),
            message: $validated['message'],
            recipientIds: $validated['recipient_ids'],
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('idempotency_key') && $this->header('Idempotency-Key') !== null) {
            $this->merge([
                'idempotency_key' => $this->header('Idempotency-Key'),
            ]);
        }
    }
}
