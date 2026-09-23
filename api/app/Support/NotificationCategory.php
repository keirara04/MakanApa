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

    public const COMMUNITY_REPLIES = 'community_replies';

    public const COMMUNITY_REACTIONS = 'community_reactions';

    public const ALL = [
        self::COMMUNITY_SUBMISSIONS,
        self::ACCOUNT_ADMIN,
        self::RELEASE_ANNOUNCEMENTS,
        self::COMMUNITY_REPLIES,
        self::COMMUNITY_REACTIONS,
    ];

    // Transactional categories the user expects by default once notifications are enabled.
    public const DEFAULT_TRUE = [
        self::COMMUNITY_SUBMISSIONS,
        self::ACCOUNT_ADMIN,
        self::COMMUNITY_REPLIES,
    ];

    // Marketing/engagement categories require an explicit opt-in (see the onboarding priming screen).
    // Reactions are high-volume and low-signal — opt-in, unlike replies.
    public const DEFAULT_FALSE = [
        self::RELEASE_ANNOUNCEMENTS,
        self::COMMUNITY_REACTIONS,
    ];
}
