<?php

namespace App\Support;

/**
 * Centralizes push-notification preference category keys so the same string never has to be
 * retyped (and risk a typo) across requests, the User model, notification classes, and tests.
 */
final class NotificationCategory
{
    public const COMMUNITY_SUBMISSIONS = 'community_submissions';

    public const ACCOUNT_ADMIN = 'account_admin';

    public const RELEASE_ANNOUNCEMENTS = 'release_announcements';

    public const ALL = [
        self::COMMUNITY_SUBMISSIONS,
        self::ACCOUNT_ADMIN,
        self::RELEASE_ANNOUNCEMENTS,
    ];

    // Transactional categories the user expects by default once notifications are enabled.
    public const DEFAULT_TRUE = [
        self::COMMUNITY_SUBMISSIONS,
        self::ACCOUNT_ADMIN,
    ];

    // Marketing/engagement categories require an explicit opt-in (see the onboarding priming screen).
    public const DEFAULT_FALSE = [
        self::RELEASE_ANNOUNCEMENTS,
    ];
}
