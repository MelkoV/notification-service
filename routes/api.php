<?php

use App\Http\Controllers\NotificationBatchController;
use App\Http\Controllers\SubscriberNotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/notifications', [NotificationBatchController::class, 'store']);
    Route::get('/subscribers/{subscriberId}/notifications', [SubscriberNotificationController::class, 'index']);
});
