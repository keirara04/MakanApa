<?php

return [
    // Adds a `debug` key to the solo recommendation response (craving resolution details,
    // candidate counts, the winning pick's score breakdown). Dev/staging only — never enable
    // in production, since it echoes internal scoring/query details back to the client.
    'debug' => env('RECOMMENDATION_DEBUG', false),

    // Extra Google Text Search lanes fired for DiscoveryMode::LowKey/Cafe and each Vibe.
    // Deliberately minimal wording ("cafe", not "hidden cafe") — MakanApa's own scoring is
    // what should create the low-key feel, not Google's interpretation of a suggestive query.
    'discovery_queries' => [
        'low_key' => ['cafe', 'specialty coffee'],
        'cafe' => ['cafe', 'specialty coffee'],
    ],
    'vibe_queries' => [
        'study' => ['study cafe'],
        'dessert' => ['dessert'],
        'coffee' => ['specialty coffee'],
        'brunch' => ['brunch cafe'],
        // chill / late_night: no extra text lane, type/rating-based signals only.
    ],

    // Case-insensitive substring match against a restaurant's name — penalizes (never
    // excludes) in Low-key/Cafe mode. Ships at weight 0 (see ScoreWeights::discoveryOverlay())
    // until real usage shows chains actually dominating those modes' results.
    'known_chains' => [
        'starbucks', 'coffee bean', 'zus coffee', 'gigi coffee', 'kenangan',
        "mcdonald's", 'mcdonalds', 'kfc', 'subway', 'pizza hut', 'domino',
    ],

    // Bayesian smoothing for the community-score component — a 0-impression restaurant scores
    // exactly the prior (neutral), not 0. See RecommendationService::communityScoreComponent().
    'community_prior' => [
        'success_rate' => 0.5,
        'weight' => 10,
    ],

    // personalFitComponent only activates once an installation has at least this many prior
    // accepted decisions — below that there's no real personal signal yet.
    'personal_fit_min_accepts' => 3,

    // UI-copy confidence threshold before showing a community-derived vibe badge
    // (e.g. "Popular for studying") — see PresentsRecommendation's vibe-label helper.
    'vibe_badge' => [
        'min_votes' => 5,
        'min_share' => 0.4,
    ],

    // Community tab's trending feed — how far back to look, how many distinct pickers before
    // a restaurant counts as "trending" (avoids single-user noise), how many to return, and
    // the Public-branch (no university) search radius. See CommunityController.
    'community_feed' => [
        'window_days' => 30,
        'min_pickers' => 2,
        'limit' => 20,
        'public_radius_km' => 10,
    ],
];
