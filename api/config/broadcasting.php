<?php

return [

    // Only the 'apn' connection is used — this app doesn't use Laravel's websocket broadcasting,
    // but that's the config namespace laravel-notification-channels/apn's service provider reads
    // its Pushok client options from.
    'connections' => [

        'apn' => [
            'key_id' => env('APN_KEY_ID'),
            'team_id' => env('APN_TEAM_ID'),
            'app_bundle_id' => env('APN_APP_BUNDLE_ID', 'com.keirara.makanapa'),
            // .p8 auth key downloaded from the Apple Developer portal (Keys), stored outside
            // version control under storage/app/apn/.
            'private_key_path' => storage_path('app/apn/'.env('APN_KEY_FILENAME', 'AuthKey.p8')),
            'private_key_secret' => null,
            'production' => env('APN_ENVIRONMENT', config('app.env') === 'production' ? 'production' : 'sandbox') === 'production',
        ],

    ],

];
