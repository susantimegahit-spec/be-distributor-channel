<?php

use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Health\ResultStores\InMemoryHealthResultStore;

return [
    /*
     * Result store for saving check results.
     */
    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => 'file',
        ],
        InMemoryHealthResultStore::class,
    ],

    /*
     * Notifications configuration
     */
    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', false),

        'notifications' => [
            CheckFailedNotification::class => ['mail'],
        ],

        'notifiable' => Notifiable::class,

        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',
        'only_on_failure' => false,
    ],

    /*
     * Theme for the local results page ('light' or 'dark')
     */
    'theme' => 'dark',

    'silence_health_queue_job' => true,
    'json_results_failure_status' => 200,
    'secret_token' => env('HEALTH_SECRET_TOKEN', 'susanti_health_secret_123'),
];
