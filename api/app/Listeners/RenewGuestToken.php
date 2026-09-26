<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * Guest tokens slide instead of hard-expiring: a guest has nothing else to sign back in with, so
 * a fixed 90-day expiry would silently wipe an active guest's picks, saves and taste history on
 * day 90. Renewed at most about once a month (only once under 60 days remain), so this isn't an
 * extra write per request. Abandoned guests still expire, and users:prune-guests removes them.
 * Registered users' tokens keep their fixed expiry — they can sign back in.
 */
class RenewGuestToken
{
    private const LIFETIME_DAYS = 90;

    private const RENEW_WHEN_DAYS_LEFT = 60;

    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;
        $user = $token->tokenable;

        if (! $user instanceof User || ! $user->isGuest() || $token->expires_at === null) {
            return;
        }

        if ($token->expires_at->lt(now()->addDays(self::RENEW_WHEN_DAYS_LEFT))) {
            $token->forceFill(['expires_at' => now()->addDays(self::LIFETIME_DAYS)])->save();
        }
    }
}
