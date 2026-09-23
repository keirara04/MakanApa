<?php

return [
    /*
    | Public certificate directories moderators check by hand (ManualDirectoryProvider). These
    | are deep links only — MakanApa does NOT scrape them. Verify each URL still resolves before
    | relying on it, and check the authority's terms before ever automating a lookup.
    */
    'directories' => [
        'jakim' => env('HALAL_DIRECTORY_JAKIM', 'https://myehalal.halal.gov.my/portal-halal/v1/index.php'),
        'state_islamic_council' => env('HALAL_DIRECTORY_STATE', 'https://myehalal.halal.gov.my/portal-halal/v1/index.php'),
        'muis' => env('HALAL_DIRECTORY_MUIS', 'https://www.muis.gov.sg/halal'),
        'bpjph' => env('HALAL_DIRECTORY_BPJPH', 'https://bpjph.halal.go.id/search/sertifikat'),
        'other' => null,
    ],

    /*
    | Which provider verifies each authority. Only `manual` exists today; automated providers
    | (JakimProvider etc.) slot in here once an approved data source exists.
    */
    'providers' => [
        'jakim' => 'manual',
        'state_islamic_council' => 'manual',
        'muis' => 'manual',
        'bpjph' => 'manual',
        'other' => 'manual',
    ],

    // Lifecycle (halal:lifecycle): notify owner/contributors at these days-before-expiry marks.
    'expiry_notice_days' => [30, 7],

    // Review-queue priority model (HalalReportService::priorityBreakdown). Queue ORDER only —
    // never status. The AI part is advisory and capped at ±ai_cap in total.
    'priority' => [
        'base' => 50,
        'verified_owner' => 20,
        'trusted_contributor' => 10,
        'non_halal_vs_certified' => 25,
        'per_corroborating_reporter' => 5,
        'corroborating_reporters_max' => 15,
        'new_account' => -10,
        'high_rejection_rate' => -15,
        'duplicate_photo' => -20,
        'ai_cap' => 20,
        'ai' => [
            'strong_evidence' => 10,
            'pork_alcohol_evidence' => 10,
            'likely_spam' => -15,
        ],
    ],

    // community:prune-drafts — unfinished drafts older than this become `cancelled`.
    'draft_ttl_days' => 7,
];
