<?php

namespace App\Support;

/**
 * Post-accept feedback taxonomy — what a user says a place *is like*, not a search filter.
 * Broader than Vibe on purpose: "hidden gem," "date spot," "student budget" are useful things
 * to know about a restaurant without being a sensible thing to type-filter Nearby by. Only the
 * subset with a real searchable equivalent maps to a Vibe (toDiscoveryVibe()) — the rest are
 * feedback-only until/unless a retrieval signal for them is built.
 */
enum CommunityTag: string
{
    case Chill = 'chill';
    case Study = 'study';
    case StudentBudget = 'student_budget';
    case HiddenGem = 'hidden_gem';
    case Date = 'date';
    case Lepak = 'lepak';
    case Family = 'family';
    case LateNight = 'late_night';

    public function toDiscoveryVibe(): ?Vibe
    {
        return match ($this) {
            self::Chill => Vibe::Chill,
            self::Study => Vibe::Study,
            self::LateNight => Vibe::LateNight,
            default => null,
        };
    }
}
