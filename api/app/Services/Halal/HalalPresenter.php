<?php

namespace App\Services\Halal;

use App\Models\Restaurant;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalEvidenceSource;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;

/**
 * The ONLY producer of public halal payloads and labels. Clients draw `display` verbatim —
 * they never derive wording from status themselves, so iOS/admin/web can't drift apart.
 *
 * Never emitted: certificate_number, submission notes/review_note, override_reason,
 * contributor stats.
 */
class HalalPresenter
{
    public const REPORTS_SHOWN = 3;

    /** Compact shape for map markers and list cards, from a Restaurant::toRecommendationArray() row. */
    public function summaryFromArray(array $restaurant): array
    {
        $status = HalalStatus::tryFrom($restaurant['halal_status'] ?? '') ?? HalalStatus::Unknown;

        return [
            'status' => $status->value,
            'display' => $this->labels(
                $status,
                CertificationAuthority::tryFrom($restaurant['halal_authority'] ?? ''),
                (bool) ($restaurant['halal_reverify'] ?? false),
            ),
        ];
    }

    /** Full shape for the detail sheet / recommendation winner. */
    /**
     * @param  User|null  $viewer  when given, adds `myReport` — the viewer's own latest vouch on this
     *                             place (so the app can show "waiting for review" instead of offering a
     *                             second vouch the API would reject)
     */
    public function present(Restaurant $restaurant, ?User $viewer = null): array
    {
        $restaurant->loadMissing('activeHalalVerification', 'activeHalalCertificate');
        $verification = $restaurant->activeHalalVerification;
        $certificate = $restaurant->activeHalalCertificate;

        return [
            'status' => $restaurant->effectiveHalalStatus()->value,
            'reviewState' => $this->publicReviewState($restaurant),
            'display' => $this->display($restaurant),
            'verification' => $verification ? [
                'method' => $verification->decision_method->value,
                'evidenceSource' => $verification->evidence_source->value,
                'authority' => $certificate?->authority->value,
                'verifiedAt' => $verification->effective_from?->toIso8601String(),
                'expiresAt' => $certificate?->expires_at?->toDateString(),
                'registryCheckedAt' => $certificate?->registry_checked_at?->toIso8601String(),
            ] : null,
            'reports' => $this->reports($restaurant, $verification),
            'historyCount' => $restaurant->halalVerifications()->count(),
            'myReport' => $viewer ? $this->viewerReport($restaurant, $viewer) : null,
        ];
    }

    /** Open vouches, plus the last decided one for 30 days so the user sees its outcome. */
    private function viewerReport(Restaurant $restaurant, User $viewer): ?array
    {
        $report = RestaurantSubmission::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('user_id', $viewer->id)
            ->where('submission_type', 'halal_report')
            ->where(fn ($q) => $q->whereIn('status', ['draft', 'pending', 'changes_requested'])
                ->orWhere(fn ($q) => $q->whereIn('status', ['approved', 'rejected'])->where('reviewed_at', '>=', now()->subDays(30))))
            ->latest('id')
            ->first();

        return $report ? [
            'id' => $report->id,
            'status' => $report->status,
            'claim' => $report->halal_claim?->value,
            'reviewNote' => in_array($report->status, ['changes_requested', 'rejected'], true) ? $report->review_note : null,
        ] : null;
    }

    /** Public, paginated ledger view for GET restaurants/{id}/halal/history. */
    public function historyEntry(RestaurantHalalVerification $verification): array
    {
        return [
            'id' => $verification->id,
            'status' => $verification->status->value,
            'state' => $verification->state->value,
            'method' => $verification->decision_method->value,
            'evidenceSource' => $verification->evidence_source->value,
            'authority' => $verification->certificate?->authority->value,
            'summary' => $verification->evidence_summary,
            'effectiveFrom' => $verification->effective_from?->toIso8601String(),
            'effectiveUntil' => $verification->effective_until?->toIso8601String(),
        ];
    }

    /**
     * @return array{shortLabel: string, longLabel: string, tone: string, action: ?string, verificationLabel: ?string}
     */
    public function display(Restaurant $restaurant): array
    {
        return [
            ...$this->labels(
                $restaurant->effectiveHalalStatus(),
                $restaurant->activeHalalCertificate?->authority,
                $restaurant->needsHalalReverification(),
            ),
            'verificationLabel' => $this->verificationLabel($restaurant),
        ];
    }

    /**
     * The whole wording table, in one place. Only Certified ever says "Halal"; only JAKIM is
     * named in the badge.
     *
     * @return array{shortLabel: string, longLabel: string, tone: string, action: ?string}
     */
    public function labels(HalalStatus $status, ?CertificationAuthority $authority, bool $expired): array
    {
        [$short, $long, $tone, $action] = match (true) {
            $status === HalalStatus::Certified => $this->certifiedLabels($authority),
            $status === HalalStatus::MuslimFriendly => ['Muslim-friendly', 'Muslim-friendly (not halal certified)', 'friendly', null],
            $status === HalalStatus::NonHalal => ['Non-halal', 'Non-halal', 'non_halal', null],
            $expired => ['Cert expired · Help re-verify', 'Halal certificate expired — help us re-verify', 'warning', 'help_reverify'],
            default => ['Not verified · Help verify', 'Halal status not verified yet — help us verify', 'neutral', 'help_verify'],
        };

        return ['shortLabel' => $short, 'longLabel' => $long, 'tone' => $tone, 'action' => $action];
    }

    /** @return array{0: string, 1: string, 2: string, 3: null} */
    private function certifiedLabels(?CertificationAuthority $authority): array
    {
        if ($authority?->badgeName() !== null) {
            return ["Halal ({$authority->badgeName()})", "{$authority->badgeName()} halal certified", 'certified', null];
        }

        $long = $authority && $authority !== CertificationAuthority::Other
            ? "Halal certified by {$authority->label()}"
            : 'Halal certified';

        return ['Halal certified', $long, 'certified', null];
    }

    private function verificationLabel(Restaurant $restaurant): ?string
    {
        $verification = $restaurant->activeHalalVerification;
        $underReview = in_array($restaurant->halal_review_state, [HalalReviewState::PendingReview, HalalReviewState::ConflictingEvidence], true);

        $label = null;
        if ($verification !== null && $restaurant->effectiveHalalStatus() !== HalalStatus::Unknown) {
            $date = $verification->effective_from?->timezone(config('app.display_timezone', 'Asia/Kuala_Lumpur'))->format('j M Y');
            $label = match (true) {
                $verification->decision_method === HalalDecisionMethod::Automatic => 'Flagged automatically from its listing',
                $verification->decision_method === HalalDecisionMethod::RegistryVerified => "Checked against the official registry · {$date}",
                $verification->decision_method === HalalDecisionMethod::AdministratorOverride => "Set by the MakanApa team · {$date}",
                $verification->evidence_source === HalalEvidenceSource::RestaurantOwner => "Verified from owner evidence · Reviewed {$date}",
                default => "Verified from community evidence · Reviewed {$date}",
            };
        }

        if ($underReview) {
            $label = $label ? "{$label} · Verification under review" : 'Verification under review';
        }

        return $label;
    }

    /** Only a hint is public — "clear" or "under_review"; the finer internal states stay internal. */
    private function publicReviewState(Restaurant $restaurant): string
    {
        return in_array($restaurant->halal_review_state, [HalalReviewState::PendingReview, HalalReviewState::ConflictingEvidence], true)
            ? 'under_review'
            : 'clear';
    }

    private function reports(Restaurant $restaurant, ?RestaurantHalalVerification $active): array
    {
        return RestaurantSubmission::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('submission_type', 'halal_report')
            ->where('status', 'approved')
            ->with(['user:id,name', 'photos' => fn ($q) => $q->where('is_active', true)->whereNotNull('restaurant_id')])
            ->latest('reviewed_at')
            ->limit(self::REPORTS_SHOWN)
            ->get()
            ->map(fn (RestaurantSubmission $report) => [
                'id' => $report->id,
                'claim' => $report->halal_claim?->value,
                'resolvedStatus' => ($report->halal_resolved_status ?? $report->halal_claim)?->value,
                'isCurrent' => $active?->submission_id === $report->id,
                'comment' => $report->halal_comment,
                'userName' => $report->user?->name ?? 'MakanApa user',
                'approvedAt' => $report->reviewed_at?->toIso8601String(),
                'photos' => $report->photos->map(fn (RestaurantPhoto $photo) => [
                    'id' => $photo->id,
                    'url' => $photo->publicUrl(),
                    'photoType' => $photo->photo_type,
                ])->filter(fn (array $p) => $p['url'] !== null)->values()->all(),
            ])
            ->all();
    }
}
