<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriberNotificationController extends Controller
{
    public function index(string $subscriberId): AnonymousResourceCollection
    {
        $notifications = Notification::query()
            ->where('recipient_id', $subscriberId)
            ->latest()
            ->paginate(50);

        return NotificationResource::collection($notifications);
    }
}
