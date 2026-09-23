<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Community posts ("What KU is saying")
    |--------------------------------------------------------------------------
    |
    | Posts go live immediately — the word filter and link ban below are the
    | only pre-publication checks. Everything else is post-moderation: enough
    | distinct reports auto-hide a post until an admin reviews it in Filament.
    |
    */

    'community_posts' => [
        'max_length' => (int) env('COMMUNITY_POST_MAX_LENGTH', 280),
        'page_size' => 20,
        'reply_preview_count' => 2,
        'report_hide_threshold' => (int) env('COMMUNITY_POST_REPORT_HIDE_THRESHOLD', 3),
        // Fresh accounts can't post yet — makes throwaway-account spam/abuse slower. Trusted
        // contributors skip this.
        'min_account_age_hours' => (int) env('COMMUNITY_POST_MIN_ACCOUNT_AGE_HOURS', 24),

        // Whole-word, case-insensitive. English + Malay. Deliberately excludes words that are
        // legitimate food/halal vocabulary in this app ("babi", "anjing") — those get handled by
        // reports, not a blind filter. Extend here; no code change needed.
        'blocked_words' => [
            // English
            'fuck', 'fucking', 'fucker', 'motherfucker', 'shit', 'bullshit', 'bitch', 'cunt',
            'asshole', 'bastard', 'dickhead', 'pussy', 'whore', 'slut', 'retard', 'faggot', 'nigger', 'nigga',
            // Malay
            'pukimak', 'puki', 'kimak', 'lancau', 'pantat', 'bangsat', 'sial', 'celaka',
            'haram jadah', 'butoh', 'kote', 'jubur', 'keparat', 'sundal', 'pelacur',
        ],
    ],

];
