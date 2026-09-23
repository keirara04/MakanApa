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

];
