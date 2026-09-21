<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing site contact & domain config
    |--------------------------------------------------------------------------
    |
    | Public contact addresses and the marketing subdomain are deployment
    | config, not source — no fallback to a real address/domain here so a
    | fresh environment can't silently leak one that isn't meant for it.
    |
    */

    'support_email' => env('SUPPORT_EMAIL'),

    'privacy_email' => env('PRIVACY_EMAIL', env('SUPPORT_EMAIL')),

    'domain' => env('MARKETING_DOMAIN', 'localhost'),

];
