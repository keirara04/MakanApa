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
        'cache_hours' => env('PLACES_CACHE_HOURS', 8),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        // OpenRouter's free-tier catalog changes frequently — verify this model is still
        // listed as ":free" at https://openrouter.ai/models before relying on it.
        'model' => env('OPENROUTER_MODEL', 'cohere/north-mini-code:free'),
        'daily_limit' => env('OPENROUTER_DAILY_LIMIT', 40),
    ],

    'apple' => [
        // The `aud` claim to check identity tokens against for the *native* Sign in with Apple
        // flow (ASAuthorizationController on-device) — this is the app's bundle identifier, not
        // a web Services ID (those are two different `aud` values depending on flow type).
        'client_id' => env('APPLE_CLIENT_ID'),
        // Needed only for the authorizationCode → refresh_token exchange (Apple's token
        // endpoint requires a server-to-server client secret, itself a short-lived ES256 JWT
        // signed with a private key generated in the Apple Developer portal under Keys).
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'google' => [
        // Deliberately the Web-application OAuth client ID, not the iOS client ID. The iOS app
        // is configured with both `GIDClientID` (iOS client, drives the native sign-in UI) and
        // `GIDServerClientID` (this value) — the latter is what ends up as the ID token's `aud`,
        // which is what Google_Client::verifyIdToken() checks against here. Using the iOS client
        // ID here would make every token verification fail with an audience mismatch.
        'server_client_id' => env('GOOGLE_SERVER_CLIENT_ID'),
    ],

];
