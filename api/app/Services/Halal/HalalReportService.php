<?php

namespace App\Services\Halal;

use App\Jobs\TriageHalalReport;
use App\Models\Restaurant;
use App\Models\RestaurantOwner;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Services\Judgment\JudgmentResult;
use App\Support\Halal\HalalStatus;
use Illuminate\Support\Facades\DB;

/**
 * Community/owner halal evidence intake. Reports are just another community submission type
 * (draft -> pending -> moderated), reusing the photo pipeline, MySubmissions, the moderation
 * queue and notifications — never a parallel system. Reports never change status on their own.
 */
class HalalReportService
{
    public const OPEN_STATUSES = ['draft', 'pending', 'changes_requested'];

    public const MAX_OPEN_PER_USER = 10;

    public function __construct(
        private readonly HalalSnapshotService $snapshots,
        private readonly ContributorCredibilityService $credibility,
    ) {}

    /**
     * One open report per user per restaurant. The restaurant row lock serializes concurrent
     * creates (two taps, two devices) — a status-dependent "open" can't be a plain unique index.
     * An existing draft/changes_requested report is updated and returned instead of duplicated.
     */
    public function open(User $user, Restaurant $restaurant, array $data): RestaurantSubmission
    {
        return DB::transaction(function () use ($user, $restaurant, $data) {
            $restaurant = Restaurant::whereKey($restaurant->canonicalRestaurant()->id)->lockForUpdate()->firstOrFail();

            $existing = RestaurantSubmission::where('user_id', $user->id)
                ->where('restaurant_id', $restaurant->id)
                ->where('submission_type', 'halal_report')
                ->whereIn('status', self::OPEN_STATUSES)
                ->first();

            abort_if($existing?->status === 'pending', 422, 'You already have a halal report waiting for review on this place.');

            if ($existing === null) {
                $openCount = RestaurantSubmission::where('user_id', $user->id)
                    ->where('submission_type', 'halal_report')
                    ->whereIn('status', self::OPEN_STATUSES)
                    ->count();
                abort_if($openCount >= self::MAX_OPEN_PER_USER, 429, 'You have too many halal reports open. Wait for some to be reviewed first.');
            }

            $attributes = [
                'halal_claim' => HalalStatus::from($data['claim']),
                'halal_comment' => $data['comment'] ?? null,
                'certification_authority' => $data['certificationAuthority'] ?? null,
                'certificate_number' => $data['certificateNumber'] ?? null,
                'certificate_issued_at' => $data['certificateIssuedAt'] ?? null,
                'certificate_expires_at' => $data['certificateExpiresAt'] ?? null,
            ];

            if ($existing) {
                $existing->update($attributes);

                return $existing;
            }

            return RestaurantSubmission::create([
                ...$attributes,
                'user_id' => $user->id,
                'university_id' => $user->universityId(),
                'area_id' => $user->areaId(),
                'restaurant_id' => $restaurant->id,
                'submission_type' => 'halal_report',
                'source_type' => 'manual',
                'name' => $restaurant->name,
                'latitude' => $restaurant->latitude,
                'longitude' => $restaurant->longitude,
                'location_source' => 'current_location',
                'changed_fields' => [],
                'status' => 'draft',
            ]);
        });
    }

    /**
     * draft/changes_requested -> pending. Evidence rules are enforced HERE (the transition), not
     * at draft creation, since photos are uploaded between the two.
     */
    public function submit(RestaurantSubmission $submission): RestaurantSubmission
    {
        $photos = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->get(['id', 'photo_type', 'content_hash']);

        abort_if(
            $submission->halal_claim === HalalStatus::Certified && ! $photos->contains('photo_type', 'halal_cert'),
            422,
            'A "certified" report needs a photo of the halal certificate.'
        );
        abort_if(
            blank($submission->halal_comment) && $photos->isEmpty(),
            422,
            'Add a comment or at least one photo as evidence.'
        );

        return DB::transaction(function () use ($submission) {
            $breakdown = $this->priorityBreakdown($submission);
            $submission->update([
                'status' => 'pending',
                'review_priority' => $breakdown['final'],
                'review_priority_breakdown' => $breakdown,
            ]);

            $this->snapshots->rebuild(Restaurant::findOrFail($submission->restaurant_id));

            // Advisory AI triage runs in the background after commit — the user never waits,
            // and a queue/AI outage only means "no AI badges", never a failed submission.
            TriageHalalReport::dispatch($submission->id)->afterCommit();

            return $submission;
        });
    }

    /** Called after any moderation decision so the open-report count / review state converge. */
    public function afterDecision(RestaurantSubmission $submission, string $outcome): void
    {
        if ($submission->restaurant_id) {
            $this->snapshots->rebuild(Restaurant::findOrFail($submission->restaurant_id));
        }
        if ($submission->user && in_array($outcome, ['approved', 'rejected'], true)) {
            $this->credibility->record($submission->user, $outcome);
        }
    }

    /**
     * Moderation queue ordering only — never affects status. Higher = reviewed sooner.
     */
    public function reviewPriority(RestaurantSubmission $submission): int
    {
        return $this->priorityBreakdown($submission)['final'];
    }

    /**
     * Explicit, bounded, inspectable priority model — answers "why is this report #1?":
     * base + deterministic rules + (optional) AI triage contribution capped at ±ai_cap,
     * clamped to 0..100. Weights live in config/halal.php `priority`.
     *
     * @return array{base: int, rules: array<string, int>, ai: array<string, int>, final: int}
     */
    public function priorityBreakdown(RestaurantSubmission $submission, ?JudgmentResult $triage = null): array
    {
        $w = config('halal.priority');
        $restaurant = Restaurant::find($submission->restaurant_id);
        $user = $submission->user;
        $rules = [];

        if ($user && RestaurantOwner::isVerifiedOwner($user->id, $submission->restaurant_id)) {
            $rules['verified_owner'] = $w['verified_owner'];
        }
        if ($user?->trusted_contributor) {
            $rules['trusted_contributor'] = $w['trusted_contributor'];
        }
        // Someone saying a "Halal" place isn't — the most harmful possible error if true.
        if ($submission->halal_claim === HalalStatus::NonHalal && $restaurant?->effectiveHalalStatus() === HalalStatus::Certified) {
            $rules['non_halal_vs_certified'] = $w['non_halal_vs_certified'];
        }

        $otherReporters = RestaurantSubmission::where('restaurant_id', $submission->restaurant_id)
            ->where('submission_type', 'halal_report')
            ->where('status', 'pending')
            ->where('halal_claim', $submission->halal_claim)
            ->where('user_id', '!=', $submission->user_id)
            ->distinct()
            ->count('user_id');
        if ($otherReporters > 0) {
            $rules['corroborating_reporters'] = min($otherReporters * $w['per_corroborating_reporter'], $w['corroborating_reporters_max']);
        }

        if ($user && $user->created_at?->gt(now()->subDays(7))) {
            $rules['new_account'] = $w['new_account'];
        }
        if ($user && $this->credibility->rejectionRate($user) > 0.5) {
            $rules['high_rejection_rate'] = $w['high_rejection_rate'];
        }
        if ($this->hasDuplicatePhoto($submission)) {
            $rules['duplicate_photo'] = $w['duplicate_photo'];
        }

        $ai = $triage ? $this->aiPriorityPoints($submission, $triage) : [];
        $aiTotal = max(-$w['ai_cap'], min($w['ai_cap'], array_sum($ai)));

        return [
            'base' => $w['base'],
            'rules' => $rules,
            'ai' => $ai,
            'aiTotal' => $aiTotal,
            'final' => (int) max(0, min(100, $w['base'] + array_sum($rules) + $aiTotal)),
        ];
    }

    /** @return array<string, int> */
    private function aiPriorityPoints(RestaurantSubmission $submission, JudgmentResult $triage): array
    {
        $w = config('halal.priority.ai');
        $points = [];

        if ($triage->score('supports_claim')->score >= 2 && $triage->binary('mentions_certificate')->probability >= 0.8) {
            $points['ai_strong_evidence'] = $w['strong_evidence'];
        }
        if ($submission->halal_claim === HalalStatus::NonHalal && $triage->binary('mentions_pork_alcohol')->probability >= 0.8) {
            $points['ai_pork_alcohol_evidence'] = $w['pork_alcohol_evidence'];
        }
        if ($triage->binary('is_spam_or_irrelevant')->probability >= 0.7) {
            $points['ai_likely_spam'] = $w['likely_spam'];
        }

        return $points;
    }

    /**
     * Stores the advisory triage and folds its bounded contribution into the queue order.
     * Never touches halal status, the ledger, or the snapshot.
     */
    public function applyTriage(RestaurantSubmission $submission, JudgmentResult $triage): void
    {
        $breakdown = $this->priorityBreakdown($submission, $triage);

        $submission->forceFill([
            'triage' => $triage->summary(),
            'review_priority_breakdown' => $breakdown,
            'review_priority' => $breakdown['final'],
        ])->save();
    }

    /** Same image bytes already attached to a different submission (reused/stock evidence). */
    public function hasDuplicatePhoto(RestaurantSubmission $submission): bool
    {
        $hashes = RestaurantPhoto::where('restaurant_submission_id', $submission->id)
            ->whereNotNull('content_hash')
            ->pluck('content_hash');

        return $hashes->isNotEmpty() && RestaurantPhoto::whereIn('content_hash', $hashes)
            ->where('restaurant_submission_id', '!=', $submission->id)
            ->exists();
    }
}
