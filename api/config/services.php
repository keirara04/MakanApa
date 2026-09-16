<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'places' => [
        'provider' => env('PLACES_PROVIDER', 'fixture'),
        'google_api_key' => env('GOOGLE_PLACES_API_KEY'),
        'cache_hours' => env('PLACES_CACHE_HOURS', 24),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        // OpenRouter's free-tier catalog changes frequently — verify this model is still
        // listed as ":free" at https://openrouter.ai/models before relying on it.
        'model' => env('OPENROUTER_MODEL', 'cohere/north-mini-code:free'),
        'daily_limit' => env('OPENROUTER_DAILY_LIMIT', 40),
    ],

];
