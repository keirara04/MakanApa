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

];
