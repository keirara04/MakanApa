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

    // Empty counts as unset: `MARKETING_DOMAIN=` in a copied .env.example must not build "https://".
    'domain' => env('MARKETING_DOMAIN') ?: 'localhost',

    // Where the "Get the app" buttons point: the App Store listing. No storefront in the path,
    // so Apple sends each visitor to their own country's store.
    'app_download_url' => env('APP_DOWNLOAD_URL', 'https://apps.apple.com/app/makanapa-what-to-eat/id6812670941'),

    // MakanApa on Threads: footer link and the structured data's sameAs.
    'threads_url' => env('THREADS_URL', 'https://www.threads.com/@keiraaraar'),

    'app_download_label' => env('APP_DOWNLOAD_LABEL', 'Download on the App Store'),

    // The App Store listing's numeric id, for Safari's Smart App Banner. Null turns the banner off.
    'app_store_id' => env('APP_STORE_ID', '6812670941'),

    // App Store Connect provider token for campaign links (pt=). With the store id above it builds
    // per-campus install links, so App Analytics can credit each campus's ambassadors.
    'app_store_provider_token' => env('APP_STORE_PROVIDER_TOKEN', '129344335'),

    // Campuses with live ambassadors, keyed by the slug in /ambassadors?campus=… and
    // /go/app-store?campus=…. A slug's App Store campaign is "amb_<slug>"; anything not listed
    // here falls back to the plain listing, so a link can't carry a made-up campaign.
    'ambassador_campuses' => [
        'uitmjasin' => 'UiTM Jasin',
        'unimap' => 'UniMAP',
        'pennstate' => 'Penn State',
    ],

    // Shared place links (/p/{id}-{slug}) may be indexed and listed in the sitemap. Only places the
    // community added ever are: Google-sourced ones stay noindex (Places terms), see SharePlaceController.
    'share_indexable' => (bool) env('SHARE_PAGES_INDEXABLE', true),

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
            'app_ratings' => (int) env('MARKETING_STATS_MIN_APP_RATINGS', 10),
            // Places within 3 km a campus needs before the coverage list names it.
            'app_reviews' => (int) env('MARKETING_STATS_MIN_APP_REVIEWS', 3),
            'coverage_places' => (int) env('MARKETING_STATS_MIN_COVERAGE_PLACES', 30),
        ],
    ],

];
