<?php

/*
|--------------------------------------------------------------------------
| Outbound API cost tracking (admin → System → API costs)
|--------------------------------------------------------------------------
|
| Estimates only — Google and OpenRouter invoices are the source of truth. Prices are USD and
| mirror each provider's public list price when this was written; update them (or override via
| env) when pricing changes. `admin:check-api-budget` alerts superadmins at each threshold of a
| provider's monthly budget, once per threshold per month.
|
*/

return [

    'alert_thresholds' => [0.8, 1.0],

    'google_places' => [
        'label' => 'Google Places',
        'monthly_budget_usd' => (float) env('ADMIN_BUDGET_GOOGLE_PLACES_USD', 100),

        // Keys match GooglePlacesProvider::USAGE_* (one per billed SKU). `free_per_month` is
        // Google's monthly free-usage cap for that SKU, taken off before pricing.
        // FIELD_MASK asks for rating/priceLevel/opening hours, which bills Nearby/Text Search
        // and Place Details at the Enterprise tier; photos + reviews add Atmosphere.
        'endpoints' => [
            'nearby_search' => [
                'label' => 'Nearby Search (Enterprise)',
                'usd_per_1000' => (float) env('ADMIN_PRICE_PLACES_NEARBY_PER_1000', 35),
                'free_per_month' => (int) env('ADMIN_FREE_PLACES_NEARBY', 1000),
            ],
            'text_search' => [
                'label' => 'Text Search (Enterprise)',
                'usd_per_1000' => (float) env('ADMIN_PRICE_PLACES_TEXT_PER_1000', 35),
                'free_per_month' => (int) env('ADMIN_FREE_PLACES_TEXT', 1000),
            ],
            'place_details' => [
                'label' => 'Place Details (Enterprise)',
                'usd_per_1000' => (float) env('ADMIN_PRICE_PLACES_DETAILS_PER_1000', 20),
                'free_per_month' => (int) env('ADMIN_FREE_PLACES_DETAILS', 1000),
            ],
            'place_details_atmosphere' => [
                'label' => 'Place Details (Enterprise + Atmosphere)',
                'usd_per_1000' => (float) env('ADMIN_PRICE_PLACES_DETAILS_ATMOSPHERE_PER_1000', 25),
                'free_per_month' => (int) env('ADMIN_FREE_PLACES_DETAILS_ATMOSPHERE', 1000),
            ],
            'place_photo' => [
                'label' => 'Place Photos',
                'usd_per_1000' => (float) env('ADMIN_PRICE_PLACES_PHOTO_PER_1000', 7),
                'free_per_month' => (int) env('ADMIN_FREE_PLACES_PHOTO', 1000),
            ],
        ],
    ],

    'openrouter' => [
        'label' => 'OpenRouter (AI judgments)',
        'monthly_budget_usd' => (float) env('ADMIN_BUDGET_OPENROUTER_USD', 20),

        // USD per 1M tokens, by model id as recorded in ai_judgments.model. Models ending in
        // ":free" always cost 0. Anything unlisted falls back to `default`.
        'models' => [
            'anthropic/claude-haiku-4.5' => ['input_per_million' => 1.00, 'output_per_million' => 5.00],
        ],
        'default' => [
            'input_per_million' => (float) env('ADMIN_PRICE_OPENROUTER_INPUT_PER_M', 1.00),
            'output_per_million' => (float) env('ADMIN_PRICE_OPENROUTER_OUTPUT_PER_M', 5.00),
        ],
    ],

];
