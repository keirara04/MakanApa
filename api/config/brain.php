<?php

/*
|--------------------------------------------------------------------------
| Makan Brain
|--------------------------------------------------------------------------
|
| Selera Memory (weeks) · Moment Pulse (session + hours) · Context · Decision
| Trace. Deterministic and auditable — no LLM anywhere on this path. Every
| number the brain uses lives here so tuning never means touching logic.
|
| Kill switches default ON outside production and OFF in production until the
| v1-vs-v2 evaluation page says otherwise. BRAIN_ENABLED=false gives exactly
| the v1 algorithm.
|
*/

$defaultOn = env('APP_ENV', 'production') !== 'production';

return [
    'enabled' => (bool) env('BRAIN_ENABLED', $defaultOn),

    'features' => [
        'selera' => (bool) env('BRAIN_SELERA', true),
        'pulse' => (bool) env('BRAIN_PULSE', true),
        'exploration' => (bool) env('BRAIN_EXPLORATION', true),
        'diversity' => (bool) env('BRAIN_DIVERSITY', true),
        'context' => (bool) env('BRAIN_CONTEXT', true),
        'weather' => (bool) env('BRAIN_WEATHER_ENABLED', true),
        'tune' => (bool) env('BRAIN_TUNE', true),
        'what_if' => (bool) env('BRAIN_WHAT_IF', true),
    ],

    'algorithm_version' => 'v2',
    'reason_catalog_version' => 1,
    'timezone' => 'Asia/Kuala_Lumpur',

    // ── Learning ────────────────────────────────────────────────────────────────
    // Explicit feedback outranks behaviour; passive interactions never teach at all.
    'authority' => [
        'explicit' => 2.0,
        'strong' => 1.0,
        'medium' => 0.5,
        'weak' => 0.2,
    ],
    'half_life_days' => 21,
    'confidence_k' => 4,              // c = n / (n + k)
    'slot_min_evidence' => 4,         // a meal-slot slice only counts once it has this much evidence
    'min_signals' => 3,               // below this the long-term Selera component stays absent
    'recent_size' => 10,              // accepted-category ring buffer used for novelty + traits
    'distance_ewma_alpha' => 0.3,

    'pulse' => [
        'session_gap_minutes' => 20,  // decisions closer together than this share a session
        'half_life_hours' => 12,
        'outside_session_factor' => 0.5,
        'event_window' => 30,         // the only event read on the hot path: last N events
        'min_factor' => 0.02,
    ],

    'fatigue_threshold' => 5,         // rerolls + tunes in one session before "enough choosing" mode

    // ── Scoring ─────────────────────────────────────────────────────────────────
    'weights' => [
        'novelty' => 8,
        'open_certainty' => 6,
    ],
    'pool_size' => 8,
    'diversity' => [
        'max_per_category' => 2,
        'max_per_brand' => 2,
    ],
    'exploration' => [
        'temperature_min' => 1.0,     // in score points — ~greedy
        'temperature_max' => 14.0,
        'wildcard_probability' => 0.2,
    ],

    // ── Context ─────────────────────────────────────────────────────────────────
    'context' => [
        'slots' => [
            // [start, end) in local time, HH:MM. Supper wraps midnight.
            'breakfast' => ['06:00', '10:30'],
            'lunch' => ['11:30', '14:30'],
            'teatime' => ['15:00', '17:30'],
            'dinner' => ['18:00', '21:30'],
            'supper' => ['21:30', '03:00'],
        ],
        'ramadan_slots' => [
            'sahur' => ['03:30', '05:45'],
            'iftar' => ['18:30', '20:30'],
        ],
        // Confirm against the official JAKIM announcement each year.
        'ramadan' => [
            ['2027-02-08', '2027-03-09'],
            ['2028-01-28', '2028-02-26'],
        ],
        'friday_prayer' => ['12:45', '14:15'],
        'month_end_days' => [18, 24],
        // Weight added per active signal, multiplied by that signal's confidence.
        'overlay' => [
            'rain' => ['distance' => 10],
            'supper' => ['lateNightFit' => 8, 'openCertainty' => 4],
            'friday' => ['openCertainty' => 8],
            'iftar' => ['distance' => 6],
            'sahur' => ['openCertainty' => 8],
            'month_end' => ['cheapEatsFit' => 3],
        ],
    ],

    'weather' => [
        'fresh_minutes' => 30,
        'max_age_minutes' => 180,
        'cell_degrees' => 0.05,       // ~5 km
        'rain_mm' => 0.2,
        'timeout_seconds' => 3,
        'url' => 'https://api.open-meteo.com/v1/forecast',
    ],

    // ── Intent ──────────────────────────────────────────────────────────────────
    'lenses' => [
        'cheap_today' => ['weights' => ['cheapEatsFit' => 15]],
        'treat_myself' => ['weights' => ['rating' => 10, 'popularityBonus' => 5], 'disable' => ['cheapEatsFit']],
        'surprise_me' => ['weights' => ['novelty' => 12], 'min_exploration' => 0.7],
        'quick_one' => ['weights' => ['distance' => 15, 'openCertainty' => 10, 'popularityBonus' => 6], 'max_exploration' => 0.1],
        'community_favs' => ['weights' => ['community' => 18, 'popularityBonus' => 6]],
    ],

    'tune' => [
        'max_per_decision' => 2,
        'directions' => [
            'closer' => ['weights' => ['distance' => 25]],
            'cheaper' => ['weights' => ['cheapEatsFit' => 20]],
            'safer' => ['weights' => ['rating' => 15, 'popularityBonus' => 10, 'openCertainty' => 6]],
            'adventurous' => ['weights' => ['novelty' => 20]],
        ],
        'search_wider_factor' => 1.75,
        'search_wider_max_km' => 10,
    ],

    'reasons' => [
        'lift_floor' => 1.0,
    ],

    'community_evidence' => [
        'window_days' => 7,
        'cache_minutes' => 10,
    ],
];
