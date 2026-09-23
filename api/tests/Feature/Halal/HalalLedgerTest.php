<?php

namespace Tests\Feature\Halal;

use App\Models\AdminAuditLog;
use App\Models\RestaurantHalalCertificate;
use App\Models\RestaurantHalalVerification;
use App\Models\RestaurantOwner;
use App\Services\Halal\CertificateData;
use App\Services\Halal\HalalSnapshotService;
use App\Services\Halal\HalalVerificationService;
use App\Support\Halal\HalalDecisionMethod;
use App\Support\Halal\HalalHeuristic;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class HalalLedgerTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private function service(): HalalVerificationService
    {
        return app(HalalVerificationService::class);
    }

    public function test_full_precedence_history_heuristic_moderator_override_registry(): void
    {
        $restaurant = $this->makeRestaurant(['name' => 'Bak Kut Teh Corner']);
        $admin = $this->makeAdmin();

        // 1. Heuristic flags it.
        $auto = $this->service()->recordHeuristic($restaurant, HalalHeuristic::evaluate(['name' => $restaurant->name]));
        $this->assertSame(HalalStatus::NonHalal, $restaurant->fresh()->halal_status);

        // 2. Moderator approves a community certified report.
        $report = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved');
        $moderated = $this->service()->recordModeratorDecision($report, HalalStatus::Certified, $admin, $this->jakimCert());
        $this->assertSame(HalalStatus::Certified, $restaurant->fresh()->halal_status);

        $auto->refresh();
        $this->assertSame(HalalVerificationState::Superseded, $auto->state);
        $this->assertSame($moderated->id, $auto->superseded_by_id);
        $this->assertNotNull($auto->effective_until);

        // 3. A later heuristic run can't touch a human decision.
        $this->assertNull($this->service()->recordHeuristic($restaurant, HalalHeuristic::evaluate(['name' => $restaurant->name])));
        $this->assertSame(HalalStatus::Certified, $restaurant->fresh()->halal_status);

        // 4. Admin override needs a reason, then wins.
        try {
            $this->service()->recordAdminOverride($restaurant, HalalStatus::MuslimFriendly, $admin, '  ');
            $this->fail('Override without a reason must be rejected.');
        } catch (ValidationException) {
        }
        $override = $this->service()->recordAdminOverride($restaurant, HalalStatus::MuslimFriendly, $admin, 'Cert displayed belongs to sister outlet');
        $this->assertSame(HalalStatus::MuslimFriendly, $restaurant->fresh()->halal_status);
        $this->assertSame(HalalVerificationState::Superseded, $moderated->fresh()->state);

        // 5. Registry result can't undo an explicit override.
        try {
            $this->service()->recordRegistryResult($restaurant, $this->jakimCert('JAKIM-002'), $admin);
            $this->fail('Registry must not supersede an administrator override.');
        } catch (DomainException) {
        }

        // 6. A new moderator decision (newest human wins) replaces the override.
        $report2 = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved');
        $this->service()->recordModeratorDecision($report2, HalalStatus::Certified, $admin, $this->jakimCert('JAKIM-002'));
        $this->assertSame(HalalVerificationState::Superseded, $override->fresh()->state);

        // Ledger integrity: exactly one active row, snapshot points at it.
        $active = RestaurantHalalVerification::where('restaurant_id', $restaurant->id)->where('state', 'active')->get();
        $this->assertCount(1, $active);
        $this->assertSame($active->first()->id, $restaurant->fresh()->halal_active_verification_id);
        $this->assertSame(4, RestaurantHalalVerification::where('restaurant_id', $restaurant->id)->count());
        $this->assertGreaterThanOrEqual(3, AdminAuditLog::where('subject_id', $restaurant->id)->count());
    }

    public function test_certified_requires_certificate_data(): void
    {
        $restaurant = $this->makeRestaurant();
        $report = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified);

        $this->expectException(ValidationException::class);
        $this->service()->recordModeratorDecision($report, HalalStatus::Certified, $this->makeAdmin());
    }

    public function test_certificate_data_rejects_missing_fields_and_past_expiry(): void
    {
        try {
            $this->jakimCert('X', now()->subDay()->toDateString());
            $this->fail('Expired cert accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('expires_at', $e->errors());
        }

        try {
            CertificateData::fromArray(['authority' => 'jakim']);
            $this->fail('Incomplete cert accepted.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(['certificate_number', 'expires_at', 'verification_method'], array_keys($e->errors()));
        }
    }

    public function test_heuristic_retracts_its_own_non_halal_when_listing_no_longer_matches(): void
    {
        $restaurant = $this->makeRestaurant(['name' => 'Pork Noodle House']);
        $this->service()->recordHeuristic($restaurant, HalalHeuristic::evaluate(['name' => 'Pork Noodle House']));
        $this->assertSame(HalalStatus::NonHalal, $restaurant->fresh()->halal_status);

        $this->service()->recordHeuristic($restaurant, HalalHeuristic::evaluate(['name' => 'Noodle House']));
        $this->assertSame(HalalStatus::Unknown, $restaurant->fresh()->halal_status);
    }

    public function test_certificate_renewal_adds_a_row_and_keeps_the_old_one(): void
    {
        $restaurant = $this->makeRestaurant();
        $admin = $this->makeAdmin();

        $this->service()->recordModeratorDecision($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $admin, $this->jakimCert('A-2025'));
        $this->service()->recordModeratorDecision($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $admin, $this->jakimCert('B-2026', now()->addYears(2)->toDateString()));

        $this->assertSame(2, RestaurantHalalCertificate::where('restaurant_id', $restaurant->id)->count());
        $this->assertSame('B-2026', $restaurant->fresh()->activeHalalCertificate->certificate_number);
    }

    public function test_revoked_certificate_revokes_verification_and_falls_back_to_unknown(): void
    {
        $restaurant = $this->makeRestaurant();
        $admin = $this->makeAdmin();
        $verification = $this->service()->recordModeratorDecision($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $admin, $this->jakimCert());

        $this->service()->revokeCertificate($verification->certificate, $admin, 'Withdrawn by JAKIM');

        $this->assertSame(HalalVerificationState::Revoked, $verification->fresh()->state);
        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Unknown, $fresh->halal_status);
        $this->assertSame(HalalReviewState::ReverifyRequired, $fresh->halal_review_state);
    }

    public function test_expired_certificate_is_unknown_at_read_time_even_before_lifecycle_runs(): void
    {
        $restaurant = $this->makeRestaurant();
        $this->service()->recordModeratorDecision($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $this->makeAdmin(), $this->jakimCert());

        $this->travel(400)->days();

        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Certified, $fresh->halal_status); // snapshot not rebuilt yet
        $this->assertSame(HalalStatus::Unknown, $fresh->effectiveHalalStatus());
        $this->assertSame('unknown', $fresh->load('cuisines', 'tags')->toRecommendationArray()['halal_status']);
    }

    public function test_pending_disagreeing_report_marks_conflict_without_changing_status(): void
    {
        $restaurant = $this->makeRestaurant();
        $this->service()->recordModeratorDecision($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $this->makeAdmin(), $this->jakimCert());

        foreach (range(1, 25) as $_) {
            $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::NonHalal);
        }
        app(HalalSnapshotService::class)->rebuild($restaurant);

        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Certified, $fresh->halal_status);
        $this->assertSame(HalalReviewState::ConflictingEvidence, $fresh->halal_review_state);
        $this->assertSame(25, $fresh->halal_open_report_count);
    }

    public function test_moderator_decision_by_verified_owner_is_tagged_restaurant_owner(): void
    {
        $restaurant = $this->makeRestaurant();
        $owner = $this->makeUser();
        RestaurantOwner::create(['restaurant_id' => $restaurant->id, 'user_id' => $owner->id, 'status' => 'verified']);

        $verification = $this->service()->recordModeratorDecision(
            $this->makeHalalReport($restaurant, $owner, HalalStatus::MuslimFriendly, 'approved'), HalalStatus::MuslimFriendly, $this->makeAdmin()
        );

        $this->assertSame('restaurant_owner', $verification->evidence_source->value);
        $this->assertSame(HalalDecisionMethod::ModeratorReview, $verification->decision_method);
    }
}
