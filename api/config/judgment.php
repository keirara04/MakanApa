<?php

/*
| MakanApa Judgment System — typed, logged, probabilistic judgments (our own "System One").
| Core rule: AI judgment informs the system; it never becomes the system. Nothing configured
| here can change a halal status, hide content, or delete anything.
*/

return [
    'provider' => 'openrouter',
    'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    // Reuses the existing OpenRouter key (services.openrouter.api_key). Empty key = NullJudgmentEngine.
    'model' => env('JUDGMENT_MODEL', 'anthropic/claude-haiku-4.5'),
    // json_schema (strict structured outputs — Haiku) | tools (forced tool call — models without schema support)
    'mode' => env('JUDGMENT_MODE', 'json_schema'),
    'timeout' => (int) env('JUDGMENT_TIMEOUT', 20),
    'connect_timeout' => 3,
    'max_output_tokens' => 800,
    // One retry on 429/5xx/timeout; tests set this to 0.
    'retry_backoff_ms' => (int) env('JUDGMENT_RETRY_BACKOFF_MS', 750),

    // Deterministic state trimming defaults; a definition may override per purpose.
    'limits' => [
        'max_state_chars' => 6000,
        'max_menu_items' => 25,
        'max_comment_chars' => 1000,
        'max_list_items' => 20,
    ],

    // Per-purpose switches and budgets — turn one feature off in production without touching
    // the others; a spike in one can't drain another's budget. daily_limit null = the caller
    // owns budgeting (craving: CravingResolver's existing DailyAiBudget).
    'purposes' => [
        'halal_triage' => [
            'enabled' => (bool) env('JUDGMENT_HALAL_TRIAGE_ENABLED', true),
            'daily_limit' => (int) env('JUDGMENT_HALAL_TRIAGE_DAILY_LIMIT', 600),
        ],
        'non_halal_second_opinion' => [
            'enabled' => (bool) env('JUDGMENT_NON_HALAL_ENABLED', true),
            'daily_limit' => (int) env('JUDGMENT_NON_HALAL_DAILY_LIMIT', 600),
        ],
        'craving' => [
            'enabled' => (bool) env('JUDGMENT_CRAVING_ENABLED', true),
            'daily_limit' => null,
            'timeout' => 8,
            'model' => env('JUDGMENT_CRAVING_MODEL'),
        ],
        'community_screen' => [
            'enabled' => (bool) env('JUDGMENT_COMMUNITY_ENABLED', false),
            'daily_limit' => (int) env('JUDGMENT_COMMUNITY_DAILY_LIMIT', 600),
        ],
    ],

    // judgments:prune — state snapshots are cleared after this; rows/hashes/outcomes are kept.
    'state_retention_days' => 90,
];
