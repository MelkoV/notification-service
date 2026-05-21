<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Notifications\Contracts\NotificationBroker;
use App\Services\Notifications\RabbitMqNotificationBroker;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationBroker::class, RabbitMqNotificationBroker::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
