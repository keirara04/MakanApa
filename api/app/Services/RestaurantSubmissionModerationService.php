<?php

namespace App\Services;

use App\Models\AiJudgment;
use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantOwner;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Notifications\CommunitySubmissionDecided;
use App\Notifications\HalalReportDecided;
use App\Notifications\OwnerClaimDecided;
use App\Services\Halal\CertificateData;
use App\Services\Halal\HalalReportService;
use App\Services\Halal\HalalVerificationService;
use App\Support\Halal\HalalStatus;
use App\Support\RestaurantField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The ONLY place a `restaurant_submissions` row is ever turned into or applied onto a canonical
 * `restaurants` row. Both the JSON admin API (Api\Admin\RestaurantSubmissionController) and the
 * Filament admin panel call these same methods — there must never be two independent
 * implementations of "approve a submission" that can silently diverge.
 */
class RestaurantSubmissionModerationService
{
    public function __construct(
        private readonly AdminAuditLogger $auditLogger,
        private readonly HalalVerificationService $halalVerifications,
        private readonly HalalReportService $halalReports,
    ) {}

    /**
     * @param  array{resolved_status?: string, certificate?: array, evidence_summary?: string}  $halal
     *                                                                                                  halal_report only: the moderator's resolved status (defaults to the claim) and,
     *                                                                                                  for `certified`, the moderator-verified certificate (see CertificateData).
     */
    public function approve(RestaurantSubmission $submission, User $admin, bool $releaseToGoogle = false, array $halal = []): Restaurant
    {
        return DB::transaction(function () use ($submission, $admin, $releaseToGoogle, $halal) {
            /** @var RestaurantSubmission $locked */
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $restaurant = match ($locked->submission_type) {
                'new_place' => $this->approveNewPlace($locked, $admin->id),
                'edit_place' => $this->approveEdit($locked, $admin->id),
                'closure' => $this->approveClosure($locked, $admin->id),
                'reopen' => $this->approveReopen($locked, $releaseToGoogle, $admin->id),
                'halal_report' => $this->approveHalalReport($locked, $admin, $halal),
                'owner_claim' => $this->approveOwnerClaim($locked, $admin),
                default => abort(422, 'Unknown submission type.'),
            };

            $locked->update([
                'status' => 'approved',
                'restaurant_id' => $restaurant->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogger->log($admin, 'submission.approve', $locked, metadata: [
                'submission_type' => $locked->submission_type,
                'restaurant_id' => $restaurant->id,
            ]);

            $this->notifyDecision($locked, 'approved');

            return $restaurant;
        });
    }

    /** Explicit "this is the same place" action — for the Google-sync-race case and the manual duplicate hint alike. */
    public function link(RestaurantSubmission $submission, int $restaurantId, User $admin): void
    {
        DB::transaction(function () use ($submission, $restaurantId, $admin) {
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $locked->update([
                'status' => 'approved',
                'restaurant_id' => $restaurantId,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogger->log($admin, 'submission.link', $locked, metadata: ['restaurant_id' => $restaurantId]);
        });
    }

    public function reject(RestaurantSubmission $submission, string $reviewNote, User $admin): void
    {
        DB::transaction(function () use ($submission, $reviewNote, $admin) {
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $locked->update([
                'status' => 'rejected',
                'review_note' => $reviewNote,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogger->log($admin, 'submission.reject', $locked, reason: $reviewNote);

            $this->notifyDecision($locked, 'rejected', $reviewNote);
        });
    }

    public function requestChanges(RestaurantSubmission $submission, string $reviewNote, User $admin): void
    {
        DB::transaction(function () use ($submission, $reviewNote, $admin) {
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $locked->update([
                'status' => 'changes_requested',
                'review_note' => $reviewNote,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogger->log($admin, 'submission.request_changes', $locked, reason: $reviewNote);

            // Only the halal/owner types notify here — pre-existing types keep their behaviour.
            if (in_array($locked->submission_type, ['halal_report', 'owner_claim'], true)) {
                $this->notifyDecision($locked, 'changes_requested', $reviewNote);
            }
        });
    }

    /** "Use Google again" — removes the override; the field stops being protected on the next sync. Admin-only, not tied to any submission. */
    public function releaseFieldOverride(Restaurant $restaurant, string $field, User $admin): void
    {
        DB::transaction(function () use ($restaurant, $field, $admin) {
            RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', $field)->delete();

            $this->auditLogger->log($admin, 'restaurant.release_field_override', $restaurant, metadata: ['field' => $field]);
        });
    }

    /**
     * Direct admin takedown of an already-approved place — no submission involved, unlike
     * approveClosure() (that one is the user-facing "this place is closed" report going through
     * the normal moderation queue). Reuses the same is_active=false + field-override mechanism so
     * a `provider = 'google'` restaurant can't get silently reactivated by the next background
     * sync. Not a hard delete: historical decisions/vibe votes/reviews stay untouched, and the
     * place can come back the normal way (a user-submitted "reopen" report) if that's ever wrong.
     */
    public function remove(Restaurant $restaurant, User $admin): void
    {
        DB::transaction(function () use ($restaurant, $admin) {
            $restaurant->update(['is_active' => false]);

            if ($restaurant->provider === 'google') {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                    [
                        'restaurant_submission_id' => null,
                        'value' => false,
                        'authority' => 'admin',
                        'verified_by' => $admin->id,
                        'verified_at' => now(),
                    ]
                );
            }

            $this->auditLogger->log($admin, 'restaurant.remove', $restaurant);
        });
    }

    public function reopen(Restaurant $restaurant, bool $releaseToGoogle, User $admin): void
    {
        DB::transaction(function () use ($restaurant, $releaseToGoogle, $admin) {
            $restaurant->update(['is_active' => true]);

            if ($restaurant->provider === 'google') {
                if ($releaseToGoogle) {
                    RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', 'is_active')->delete();
                } else {
                    RestaurantFieldOverride::updateOrCreate(
                        ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                        [
                            'restaurant_submission_id' => null,
                            'value' => true,
                            'authority' => 'admin',
                            'verified_by' => $admin->id,
                            'verified_at' => now(),
                        ]
                    );
                }
            }

            $this->auditLogger->log($admin, 'restaurant.reopen', $restaurant, metadata: ['release_to_google' => $releaseToGoogle]);
        });
    }

    /**
     * `new_place`/`manual` creates a fresh community restaurant — no baseline exists, no override
     * bookkeeping needed (nothing else will ever sync over it). `new_place`/`google` re-checks
     * for a sync race first — the background Google sync may have already pulled this exact
     * place in between submission and approval — and links to it instead of duplicating; only
     * fields the submitter deliberately edited away from the Google-sourced prefill become
     * overrides on top of that baseline.
     */
    private function approveNewPlace(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        if ($submission->source_type === 'google') {
            $existing = Restaurant::where('provider', 'google')
                ->where('provider_place_id', $submission->google_place_id)
                ->first();

            if ($existing) {
                return $existing->canonicalRestaurant();
            }

            $restaurant = Restaurant::create([
                'provider' => 'google',
                'provider_place_id' => $submission->google_place_id,
                'name' => $submission->name,
                'address' => $submission->address,
                'food_category' => $submission->food_category,
                'price_level' => $submission->price_level,
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'is_active' => true,
                'source_submission_id' => $submission->id,
            ]);

            $this->materializeFields($restaurant, $submission, $adminId);
            $this->materializeMenu($restaurant, $submission);

            return $restaurant;
        }

        $restaurant = Restaurant::create([
            'provider' => 'user_submitted',
            'provider_place_id' => null,
            'name' => $submission->name,
            'address' => $submission->address,
            'food_category' => $submission->food_category,
            'price_level' => $submission->price_level,
            'phone' => $submission->phone,
            'instagram_handle' => $submission->instagram_handle,
            'tiktok_handle' => $submission->tiktok_handle,
            'website_url' => $submission->website_url,
            'latitude' => $submission->latitude,
            'longitude' => $submission->longitude,
            'is_active' => true,
            'source_submission_id' => $submission->id,
        ]);

        $this->materializeMenu($restaurant, $submission);

        return $restaurant;
    }

    /**
     * Halal evidence -> a moderator decision in the verification ledger. The reporter's claim
     * is never mutated; what the moderator actually concluded is stored alongside it.
     */
    private function approveHalalReport(RestaurantSubmission $submission, User $admin, array $halal): Restaurant
    {
        $resolved = isset($halal['resolved_status'])
            ? HalalStatus::from($halal['resolved_status'])
            : $submission->halal_claim;
        abort_if($resolved === null || $resolved === HalalStatus::Unknown, 422, 'Choose the status this evidence supports.');

        $certificate = $resolved === HalalStatus::Certified
            ? CertificateData::fromArray($halal['certificate'] ?? [])
            : null;

        $this->halalVerifications->recordModeratorDecision($submission, $resolved, $admin, $certificate, $halal['evidence_summary'] ?? null);
        $submission->update(['halal_resolved_status' => $resolved]);

        return Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant();
    }

    /** Ownership is a verified link only — it never grants direct edit rights, just evidence provenance. */
    private function approveOwnerClaim(RestaurantSubmission $submission, User $admin): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant();
        abort_if($submission->user_id === null, 422, 'The claimant account no longer exists.');

        RestaurantOwner::updateOrCreate(
            ['restaurant_id' => $restaurant->id, 'user_id' => $submission->user_id],
            [
                'status' => 'verified',
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'claim_submission_id' => $submission->id,
            ]
        );

        return $restaurant;
    }

    private function notifyDecision(RestaurantSubmission $submission, string $decision, ?string $reviewNote = null): void
    {
        match ($submission->submission_type) {
            'halal_report' => $submission->user?->notify(new HalalReportDecided($submission, $decision, $reviewNote)),
            'owner_claim' => $submission->user?->notify(new OwnerClaimDecided($submission, $decision, $reviewNote)),
            default => $submission->user?->notify(new CommunitySubmissionDecided($submission, $decision, $reviewNote)),
        };

        if ($submission->submission_type === 'halal_report') {
            $this->halalReports->afterDecision($submission, $decision);
            // Calibration label for the advisory triage (only outcome/outcome_at are ever updated).
            AiJudgment::recordOutcome('halal_triage', $submission, [
                'decision' => $decision,
                'claim' => $submission->halal_claim?->value,
                'resolved' => $submission->halal_resolved_status?->value,
            ]);
        }
    }

    private function approveEdit(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant();
        $this->materializeFields($restaurant, $submission, $adminId);
        $this->materializeMenu($restaurant, $submission);

        return $restaurant;
    }

    /**
     * Writes an override for the synthetic `is_active` field so a future Google sync can't
     * silently reopen a confirmed closure — the gap flagged as unresolved in the previous plan.
     */
    private function approveClosure(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant();
        $restaurant->update(['is_active' => false]);

        if ($restaurant->provider === 'google') {
            RestaurantFieldOverride::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                [
                    'restaurant_submission_id' => $submission->id,
                    'value' => false,
                    'authority' => 'admin',
                    'verified_by' => $adminId,
                    'verified_at' => now(),
                ]
            );
        }

        return $restaurant;
    }

    /**
     * `releaseToGoogle=false` (default): MakanApa keeps deciding open/closed (override set to true).
     * `releaseToGoogle=true`: the override is removed entirely, handing control back to Google's
     * own open/closed signal on the next sync.
     */
    private function approveReopen(RestaurantSubmission $submission, bool $releaseToGoogle, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant();
        $restaurant->update(['is_active' => true]);

        if ($restaurant->provider === 'google') {
            if ($releaseToGoogle) {
                RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', 'is_active')->delete();
            } else {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                    [
                        'restaurant_submission_id' => $submission->id,
                        'value' => true,
                        'authority' => 'admin',
                        'verified_by' => $adminId,
                        'verified_at' => now(),
                    ]
                );
            }
        }

        return $restaurant;
    }

    /**
     * Only fields listed in `changed_fields` are applied/overridden — the submission's snapshot
     * carries every field for moderation context, but applying all of them would silently freeze
     * fields the submitter never intended to touch, blocking all future Google updates to them.
     */
    private function materializeFields(Restaurant $restaurant, RestaurantSubmission $submission, int $adminId): void
    {
        $changed = array_values(array_intersect($submission->changed_fields ?? [], RestaurantField::OVERRIDABLE));
        if (empty($changed)) {
            return;
        }

        $columnMap = [
            'name' => 'name', 'address' => 'address', 'food_category' => 'food_category',
            'price_level' => 'price_level', 'latitude' => 'latitude', 'longitude' => 'longitude',
            'opening_hours' => 'opening_hours', 'phone' => 'phone', 'instagram_handle' => 'instagram_handle',
            'tiktok_handle' => 'tiktok_handle', 'website_url' => 'website_url',
        ];

        $updates = [];
        foreach ($changed as $field) {
            $value = $submission->{$columnMap[$field]};
            $updates[$field] = $value;

            if ($restaurant->provider === 'google') {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'field' => $field],
                    [
                        'restaurant_submission_id' => $submission->id,
                        'value' => $value,
                        'authority' => 'community_verified',
                        'verified_by' => $adminId,
                        'verified_at' => now(),
                    ]
                );
            }
            // provider='user_submitted': no override bookkeeping — nothing else will ever sync
            // over it, so a direct column update is all that's needed.
        }

        $restaurant->update($updates);
    }

    /** Full replacement, not a per-item diff — matches the submission's full-snapshot design. */
    private function materializeMenu(Restaurant $restaurant, RestaurantSubmission $submission): void
    {
        if (! in_array('menu_items', $submission->changed_fields ?? [], true) || empty($submission->menu_items)) {
            return;
        }

        RestaurantMenuItem::where('restaurant_id', $restaurant->id)->delete();

        foreach (array_values($submission->menu_items) as $index => $item) {
            RestaurantMenuItem::create([
                'restaurant_id' => $restaurant->id,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'price' => $item['price'] ?? null,
                'category' => $item['category'] ?? null,
                'sort_order' => $index,
                'source_submission_id' => $submission->id,
            ]);
        }
    }

    /**
     * Warning only, never an automatic block — any active restaurant within ~100m whose
     * normalized name loosely overlaps the submission's. O(n) over active restaurants is fine
     * at beta scale; not a concern worth optimizing prematurely. Public — reused by the Filament
     * submission page's "possible duplicate" panel as well as the JSON admin API's presenter.
     */
    public function duplicateHint(RestaurantSubmission $submission): ?array
    {
        $normalized = (string) Str::of($submission->name)->lower()->squish();

        foreach (Restaurant::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude']) as $candidate) {
            $distanceKm = RecommendationService::distanceKm(
                (float) $submission->latitude, (float) $submission->longitude,
                (float) $candidate->latitude, (float) $candidate->longitude
            );

            if ($distanceKm > 0.1) {
                continue;
            }

            $candidateNormalized = (string) Str::of($candidate->name)->lower()->squish();
            if (str_contains($candidateNormalized, $normalized) || str_contains($normalized, $candidateNormalized)) {
                return ['id' => $candidate->id, 'name' => $candidate->name, 'distanceMeters' => (int) round($distanceKm * 1000)];
            }
        }

        return null;
    }
}
