<?php

/*
|--------------------------------------------------------------------------
| Mealtime nudges
|--------------------------------------------------------------------------
|
| Opt-in "lunch dah?" pushes. Every number that shapes how often and how
| confidently MakanApa nudges lives here, so tuning never means touching logic.
|
*/

return [
    'enabled' => (bool) env('MEAL_NUDGES_ENABLED', true),

    // Local-time defaults when the user has too little history to personalize (HH:MM).
    'default_times' => [
        'lunch' => '12:00',
        'dinner' => '19:00',
    ],
    // Personalized time = median of the user's own decisions in that slot, minus this lead.
    'lead_minutes' => 15,
    'history_days' => 60,
    'min_history_samples' => 3,
    // After Jumaat.
    'friday_lunch_time' => '14:15',
    // Ramadan (halal-only users): dinner nudge this long before the iftar slot opens; no lunch.
    'ramadan_iftar_lead_minutes' => 45,
    // Never nudge outside [start, end) local.
    'quiet_hours' => ['22:00', '08:00'],

    // Skip when the user is clearly already deciding / in the app.
    'recent_decision_hours' => 3,
    'recent_app_open_minutes' => 60,

    // Back-off: this many unopened in a row pauses for pause_days; max_pauses pauses stops it.
    'unopened_limit' => 3,
    'pause_days' => 7,
    'max_pauses' => 3,

    // Naming a specific place needs a fresh location, a close, open, genuinely good option.
    'named_location_max_age_days' => 3,
    'radius_km' => 2.0,
    'rain_radius_km' => 1.0,
    'min_score' => 55,
    // Weather copy only from a reading at most this old.
    'weather_fresh_minutes' => 60,
];
