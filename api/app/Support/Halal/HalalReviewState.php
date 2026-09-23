<?php

namespace App\Support\Halal;

/**
 * Internal operations state, kept separate from HalalStatus so moderation never contaminates the
 * public status. Publicly it only ever surfaces as a hint ("Verification under review",
 * "Cert expired · Help re-verify") — see HalalPresenter. Resolved by HalalReviewStateResolver.
 */
enum HalalReviewState: string
{
    case Clear = 'clear';
    case PendingReview = 'pending_review';
    case ConflictingEvidence = 'conflicting_evidence';
    case Expiring = 'expiring';
    case ReverifyRequired = 'reverify_required';

    public function label(): string
    {
        return match ($this) {
            self::Clear => 'Clear',
            self::PendingReview => 'Pending review',
            self::ConflictingEvidence => 'Conflicting evidence',
            self::Expiring => 'Expiring',
            self::ReverifyRequired => 'Re-verify required',
        };
    }
}
