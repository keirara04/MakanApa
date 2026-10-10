<?php

use NotificationChannels\WebPush\PushSubscription;

return [

    /**
     * These are the keys for authentication (VAPID).
     * These keys must be safely stored and should not change.
     */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    /**
     * This is model that will be used to for push subscriptions.
     */
    // Push services a browser subscription may point at (a host or its subdomains). Others are
    // refused; add one here (comma-separated in WEBPUSH_SERVICE_HOSTS) if a browser needs it.
    'push_service_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'WEBPUSH_SERVICE_HOSTS',
        'fcm.googleapis.com,push.services.mozilla.com,notify.windows.com,push.apple.com',
    ))))),

    'model' => PushSubscription::class,

    /**
     * This is the name of the table that will be created by the migration and
     * used by the PushSubscription model shipped with this package.
     */
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),

    /**
     * This is the database connection that will be used by the migration and
     * the PushSubscription model shipped with this package.
     */
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /**
     * The HTTP client options used to deliver push notifications.
     */
    // The package default waits 30s per push service; sends run inline in the nudge scheduler.
    'client_options' => ['timeout' => 5, 'connect_timeout' => 3],

    /**
     * The automatic padding in bytes used by Minishlink\WebPush.
     * Set to false to support Firefox Android with v1 endpoint.
     */
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),

];
