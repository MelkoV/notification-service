<?php

return [
    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'notifications'),
        'queue' => env('RABBITMQ_QUEUE', 'notifications.priority'),
        'max_priority' => (int) env('RABBITMQ_MAX_PRIORITY', 10),
    ],

    'idempotency_ttl' => (int) env('NOTIFICATION_IDEMPOTENCY_TTL', 86400),
    'max_retries' => (int) env('NOTIFICATION_MAX_RETRIES', 3),
    'retry_delay_seconds' => (int) env('NOTIFICATION_RETRY_DELAY_SECONDS', 5),
];
