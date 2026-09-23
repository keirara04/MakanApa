<?php

namespace App\Services\Halal;

use App\Events\HalalCertificateLapsed;
use App\Events\HalalVerificationRecorded;
use App\Models\AiJudgment;
use App\Models\Restaurant;
use App\Models\RestaurantHalalCertificate;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantOwner;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Services\AdminAuditLogger;
use App\Support\Halal\CertificateStatus;
use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalEvidenceSource;
use App\Support\Halal\HalalHeuristicResult;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only code that writes the halal verification ledger. Every path: lock the restaurant
 * row, consult HalalDecisionPolicy, append a verification, supersede the old one, audit, then
 * rebuild the snapshot — all in one transaction, so the ledger and restaurants.halal_* can't
 * disagree. Events fire after commit.
 */
class HalalVerificationService
{
    public function __construct(
        private readonly HalalDecisionPolicy $policy,
        private readonly HalalSnapshotService $snapshots,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    /**
     * Heuristic can only ever assert non_halal. When a previously auto-flagged place no longer
     * matches (renamed, retyped on Google), it's retracted back to unknown. Never touches a
     * human-reviewed decision.
     */
    public function recordHeuristic(Restaurant $restaurant, HalalHeuristicResult $result): ?RestaurantHalalVerification
    {
        return DB::transaction(function () use ($restaurant, $result) {
            $locked = $this->lock($restaurant);
            $current = $this->active($locked);

            if (! $this->policy->canSupersede($current, HalalDecisionMethod::Automatic)) {
                return null;
            }

            $status = $result->likelyNonHalal ? HalalStatus::NonHalal : HalalStatus::Unknown;

            // Nothing to assert and nothing to retract, or the same automatic verdict again.
            if (($current === null && ! $result->likelyNonHalal) || $current?->status === $status) {
                return null;
            }

            return $this->append($locked, $current, [
                'status' => $status,
                'evidence_source' => HalalEvidenceSource::Heuristic,
                'decision_method' => HalalDecisionMethod::Automatic,
                'heuristic_matches' => $result->matches,
                'evidence_summary' => $result->likelyNonHalal ? 'Listing indicates pork or alcohol is served.' : null,
            ]);
        });
    }

    /** A moderator approving a halal_report submission (possibly resolving it to a different status). */
    public function recordModeratorDecision(
        RestaurantSubmission $submission,
        HalalStatus $resolved,
        User $moderator,
        ?CertificateData $certificate = null,
        ?string $evidenceSummary = null,
    ): RestaurantHalalVerification {
        $this->assertCertificateRequirement($resolved, $certificate);

        return DB::transaction(function () use ($submission, $resolved, $moderator, $certificate, $evidenceSummary) {
            $restaurant = $this->lock(Restaurant::findOrFail($submission->restaurant_id)->canonicalRestaurant());
            $current = $this->active($restaurant);

            $isOwner = $submission->user_id !== null
                && RestaurantOwner::isVerifiedOwner($submission->user_id, $restaurant->id);

            $verification = $this->append($restaurant, $current, [
                'submission_id' => $submission->id,
                'moderator_id' => $moderator->id,
                'status' => $resolved,
                'evidence_source' => $isOwner ? HalalEvidenceSource::RestaurantOwner : HalalEvidenceSource::Community,
                'decision_method' => HalalDecisionMethod::ModeratorReview,
                'evidence_summary' => $evidenceSummary,
            ], $certificate);

            $this->auditLogger->log($moderator, 'halal.moderator_decision', $restaurant, null, [
                'verification_id' => $verification->id,
                'submission_id' => $submission->id,
                'claim' => $submission->halal_claim?->value,
                'resolved' => $resolved->value,
            ]);

            return $verification;
        });
    }

    public function recordAdminOverride(
        Restaurant $restaurant,
        HalalStatus $status,
        User $admin,
        string $reason,
        ?CertificateData $certificate = null,
    ): RestaurantHalalVerification {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['override_reason' => 'An override needs a reason.']);
        }
        $this->assertCertificateRequirement($status, $certificate);

        return DB::transaction(function () use ($restaurant, $status, $admin, $reason, $certificate) {
            $locked = $this->lock($restaurant->canonicalRestaurant());
            $current = $this->active($locked);

            $verification = $this->append($locked, $current, [
                'moderator_id' => $admin->id,
                'status' => $status,
                'evidence_source' => HalalEvidenceSource::AdminObservation,
                'decision_method' => HalalDecisionMethod::AdministratorOverride,
                'override_reason' => $reason,
            ], $certificate);

            $this->auditLogger->log($admin, 'halal.admin_override', $locked, $reason, [
                'verification_id' => $verification->id,
                'status' => $status->value,
                'previous_status' => $current?->status->value,
            ]);

            return $verification;
        });
    }

    /**
     * A moderator confirmed a certificate against the authority's registry/directory. Refuses to
     * undo an explicit administrator override (see HalalDecisionPolicy).
     */
    public function recordRegistryResult(Restaurant $restaurant, CertificateData $certificate, User $admin): RestaurantHalalVerification
    {
        return DB::transaction(function () use ($restaurant, $certificate, $admin) {
            $locked = $this->lock($restaurant->canonicalRestaurant());
            $current = $this->active($locked);

            if (! $this->policy->canSupersede($current, HalalDecisionMethod::RegistryVerified)) {
                throw new DomainException('An administrator override is active — record a new override instead of a registry result.');
            }

            $verification = $this->append($locked, $current, [
                'moderator_id' => $admin->id,
                'status' => HalalStatus::Certified,
                'evidence_source' => HalalEvidenceSource::OfficialRegistry,
                'decision_method' => HalalDecisionMethod::RegistryVerified,
                'evidence_summary' => 'Checked against the '.$certificate->authority->label().' registry.',
            ], $certificate, registryChecked: true);

            $this->auditLogger->log($admin, 'halal.registry_verified', $locked, null, [
                'verification_id' => $verification->id,
                'authority' => $certificate->authority->value,
            ]);

            return $verification;
        });
    }

    /** Authority withdrew the certificate. Any verification resting on it is revoked; status falls back to unknown. */
    public function revokeCertificate(RestaurantHalalCertificate $certificate, User $admin, string $reason): void
    {
        DB::transaction(function () use ($certificate, $admin, $reason) {
            $restaurant = $this->lock($certificate->restaurant);
            $this->lapse($certificate, CertificateStatus::Revoked, HalalVerificationState::Revoked);
            $this->auditLogger->log($admin, 'halal.certificate_revoked', $restaurant, $reason, ['certificate_id' => $certificate->id]);
            $this->snapshots->rebuild($restaurant);
            DB::afterCommit(fn () => HalalCertificateLapsed::dispatch($certificate->fresh(), 'revoked'));
        });
    }

    /** Called by halal:lifecycle once a certificate's expiry date has passed. */
    public function expireCertificate(RestaurantHalalCertificate $certificate): void
    {
        DB::transaction(function () use ($certificate) {
            $restaurant = $this->lock($certificate->restaurant);
            $this->lapse($certificate, CertificateStatus::Expired, HalalVerificationState::Expired);
            $this->snapshots->rebuild($restaurant);
            DB::afterCommit(fn () => HalalCertificateLapsed::dispatch($certificate->fresh(), 'expired'));
        });
    }

    private function lapse(RestaurantHalalCertificate $certificate, CertificateStatus $certStatus, HalalVerificationState $state): void
    {
        $certificate->update(['status' => $certStatus]);

        RestaurantHalalVerification::where('certificate_id', $certificate->id)
            ->where('state', HalalVerificationState::Active)
            ->update(['state' => $state, 'effective_until' => now()]);
    }

    private function assertCertificateRequirement(HalalStatus $status, ?CertificateData $certificate): void
    {
        if ($status === HalalStatus::Certified && $certificate === null) {
            throw ValidationException::withMessages([
                'certificate' => 'A certified status needs verified certificate details (authority, number, expiry, verification method).',
            ]);
        }
    }

    private function lock(Restaurant $restaurant): Restaurant
    {
        return Restaurant::whereKey($restaurant->id)->lockForUpdate()->firstOrFail();
    }

    private function active(Restaurant $restaurant): ?RestaurantHalalVerification
    {
        return RestaurantHalalVerification::where('restaurant_id', $restaurant->id)
            ->where('state', HalalVerificationState::Active)
            ->latest('effective_from')->latest('id')
            ->first();
    }

    private function append(
        Restaurant $restaurant,
        ?RestaurantHalalVerification $current,
        array $attributes,
        ?CertificateData $certificate = null,
        bool $registryChecked = false,
    ): RestaurantHalalVerification {
        $certificateRow = $certificate ? $this->upsertCertificate($restaurant, $certificate, $registryChecked) : null;

        $verification = RestaurantHalalVerification::create([
            ...$attributes,
            'restaurant_id' => $restaurant->id,
            'certificate_id' => $certificateRow?->id,
            'state' => HalalVerificationState::Active,
            'effective_from' => now(),
        ]);

        // Every other still-active row is superseded, not just $current — defends against any
        // historical double-active state rather than trusting there's only ever one.
        RestaurantHalalVerification::where('restaurant_id', $restaurant->id)
            ->where('state', HalalVerificationState::Active)
            ->whereKeyNot($verification->id)
            ->update([
                'state' => HalalVerificationState::Superseded,
                'effective_until' => now(),
                'superseded_by_id' => $verification->id,
            ]);

        $this->snapshots->rebuild($restaurant);

        // Calibration label for any AI second opinion on this place — human decisions only.
        if ($verification->decision_method->isHumanReviewed() && $restaurant->halal_ai_hint !== null) {
            AiJudgment::recordOutcome('non_halal_second_opinion', $restaurant, ['status' => $verification->status->value]);
        }

        DB::afterCommit(fn () => HalalVerificationRecorded::dispatch($verification, $current?->fresh()));

        return $verification;
    }

    /** Renewal with the same number updates the row; a new number adds one — old certs are never overwritten. */
    private function upsertCertificate(Restaurant $restaurant, CertificateData $data, bool $registryChecked): RestaurantHalalCertificate
    {
        return RestaurantHalalCertificate::updateOrCreate(
            [
                'restaurant_id' => $restaurant->id,
                'authority' => $data->authority,
                'certificate_number' => $data->certificateNumber,
            ],
            array_filter([
                'expires_at' => $data->expiresAt,
                'issued_at' => $data->issuedAt,
                'holder_name' => $data->holderName,
                'premise_name' => $data->premiseName,
                'registry_url' => $data->registryUrl,
                'certificate_photo_id' => $data->certificatePhotoId,
                'verification_method' => $data->verificationMethod,
                'registry_checked_at' => $registryChecked ? now() : null,
                'status' => CertificateStatus::Valid,
            ], fn ($value) => $value !== null),
        );
    }
}
