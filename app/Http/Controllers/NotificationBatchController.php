<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateNotificationBatchRequest;
use App\Http\Resources\NotificationBatchResource;
use App\Services\Notifications\Actions\CreateNotificationBatch;
use Illuminate\Http\JsonResponse;

class NotificationBatchController extends Controller
{
    public function store(CreateNotificationBatchRequest $request, CreateNotificationBatch $action): JsonResponse
    {
        $batch = $action->handle($request->toDto());

        return new NotificationBatchResource($batch)
            ->response()
            ->setStatusCode(202);
    }
}
