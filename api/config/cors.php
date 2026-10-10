<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // The web app (app.trymakanapa.com) is the only browser client. The iOS app sends no
    // Origin header, so this never affects it. Comma-separated, e.g. for staging and Vite dev.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS', 'https://app.trymakanapa.com,http://localhost:5173'
    ))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // The web app reads Retry-After on 429s, like the iOS app does.
    'exposed_headers' => ['Retry-After'],

    // Cache preflights for a day: every Bearer request is non-simple and would preflight again.
    'max_age' => 86400,

    'supports_credentials' => false,

];
