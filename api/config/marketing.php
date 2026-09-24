<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing site contact & domain config
    |--------------------------------------------------------------------------
    |
    | Public contact addresses and the marketing subdomain are deployment
    | config, not source. No fallback to a real address/domain here, so a
    | fresh environment can't silently leak one that isn't meant for it.
    |
    */

    'support_email' => env('SUPPORT_EMAIL'),

    'privacy_email' => env('PRIVACY_EMAIL', env('SUPPORT_EMAIL')),

    'domain' => env('MARKETING_DOMAIN', 'localhost'),

    // Where the "Get the app" buttons point. TestFlight public beta until the App Store
    // listing is live — swap via env, no deploy of source needed.
    'app_download_url' => env('APP_DOWNLOAD_URL', 'https://testflight.apple.com/join/m1hGrFmM'),

    'app_download_label' => env('APP_DOWNLOAD_LABEL', 'Join the beta on TestFlight'),

    // Shared place links (/p/{id}-{slug}). Kept out of search engines until public launch.
    'share_indexable' => (bool) env('SHARE_PAGES_INDEXABLE', false),

    // "<TeamID>.<bundle id>" for apple-app-site-association (universal links into the app).
    'apple_app_id' => env('APPLE_APP_ID', '46798S8ZQT.com.keirara.makanapa'),

    // The landing page never asks for the visitor's location, so its "try it" demo and "most
    // picked near …" list are anchored on a real campus with places already in the database.
    // `university` is universities.short_name, used for the most-picked list.
    'demo' => [
        'label' => env('MARKETING_DEMO_LABEL', 'UKM Bangi'),
        'university' => env('MARKETING_DEMO_UNIVERSITY', 'UKM'),
        'latitude' => (float) env('MARKETING_DEMO_LATITUDE', 2.9290),
        'longitude' => (float) env('MARKETING_DEMO_LONGITUDE', 101.7775),
    ],

    // Live numbers on the landing page, recomputed at most this often. A stat below its floor is
    // left out rather than shown looking small; with none left, the whole strip is hidden.
    'stats' => [
        'cache_seconds' => 3600,
        'min' => [
            'places' => (int) env('MARKETING_STATS_MIN_PLACES', 50),
            'picks' => (int) env('MARKETING_STATS_MIN_PICKS', 50),
            'community' => (int) env('MARKETING_STATS_MIN_COMMUNITY', 5),
        ],
    ],

];
