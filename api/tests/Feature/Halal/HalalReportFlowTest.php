<?php

namespace Tests\Feature\Halal;

use App\Models\RestaurantOwner;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Notifications\HalalReportDecided;
use App\Notifications\HalalReportSuperseded;
use App\Notifications\OwnerClaimDecided;
use App\Services\Halal\HalalReportService;
use App\Services\Halal\HalalVerificationService;
use App\Services\RestaurantPhotoPromotionService;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class HalalReportFlowTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('restaurant_photos.pending_disk'));
        Storage::fake(config('restaurant_photos.public_disk'));
        Notification::fake();
    }

    private function openReport(int $restaurantId, array $body)
    {
        return $this->postJson("/api/v1/restaurants/{$restaurantId}/halal-reports", $body);
    }

    private function uploadPhoto(int $submissionId, string $type = 'halal_cert', ?UploadedFile $file = null)
    {
        return $this->postJson("/api/v1/community/submissions/{$submissionId}/photos", [
            'photo' => $file ?? UploadedFile::fake()->image('cert.jpg', 600, 800),
            'photoType' => $type,
        ]);
    }

    private function submit(int $submissionId)
    {
        return $this->postJson("/api/v1/community/submissions/{$submissionId}/submit");
    }

    private function moderation(): RestaurantSubmissionModerationService
    {
        return app(RestaurantSubmissionModerationService::class);
    }

    public function test_full_certified_flow_draft_evidence_submit_approve_publish(): void
    {
        $restaurant = $this->makeRestaurant();
        $reporter = $this->makeUser(['name' => 'Hakeem']);
        Sanctum::actingAs($reporter, ['*']);

        $id = $this->openReport($restaurant->id, ['claim' => 'certified', 'comment' => 'Cert displayed beside cashier.'])
            ->assertCreated()->assertJsonPath('submission.status', 'draft')->json('submission.id');

        // Certified without a cert photo is refused at the submit transition.
        $this->submit($id)->assertStatus(422);

        $this->uploadPhoto($id)->assertCreated();
        $this->submit($id)->assertOk()->assertJsonPath('submission.status', 'pending');

        $fresh = $restaurant->fresh();
        $this->assertSame(1, $fresh->halal_open_report_count);
        $this->assertSame(HalalReviewState::PendingReview, $fresh->halal_review_state);
        $this->assertSame(HalalStatus::Unknown, $fresh->halal_status);

        // Approving certified without the admin's confirmation fails (details are optional).
        $admin = $this->makeAdmin();
        try {
            $this->moderation()->approve(RestaurantSubmission::find($id), $admin);
            $this->fail('Certified approval without confirmation must fail.');
        } catch (ValidationException) {
        }
        $this->assertSame('pending', RestaurantSubmission::find($id)->status);

        $this->moderation()->approve(RestaurantSubmission::find($id), $admin, halal: [
            'certificate' => ['authority' => 'jakim', 'certificate_number' => 'JAKIM-123', 'expires_at' => now()->addYear()->toDateString(), 'verification_method' => 'manual_directory_check'],
        ]);
        app(RestaurantPhotoPromotionService::class)->promote(RestaurantSubmission::find($id), $restaurant);

        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Certified, $fresh->halal_status);
        $this->assertSame(0, $fresh->halal_open_report_count);
        $this->assertSame(HalalReviewState::Clear, $fresh->halal_review_state);
        $this->assertSame(1, $reporter->fresh()->contribution_stats['approved']);
        Notification::assertSentTo($reporter, HalalReportDecided::class);

        $details = $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertOk();
        $details->assertJsonPath('halal.status', 'certified')
            ->assertJsonPath('halal.display.shortLabel', 'Halal (JAKIM)')
            ->assertJsonPath('halal.verification.authority', 'jakim')
            ->assertJsonPath('halal.reports.0.userName', 'Hakeem')
            ->assertJsonPath('halal.reports.0.isCurrent', true)
            ->assertJsonPath('halal.reports.0.comment', 'Cert displayed beside cashier.')
            ->assertJsonPath('halal.reports.0.photos.0.photoType', 'halal_cert');
        $this->assertStringStartsWith('Verified from community evidence', $details->json('halal.display.verificationLabel'));
        // Admin-only data never leaks; cert photos stay out of the general gallery.
        $this->assertStringNotContainsString('JAKIM-123', $details->getContent());
        $this->assertSame([], $details->json('communityPhotos'));

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/halal/history")->assertOk()->assertJsonPath('entries.0.method', 'moderator_review');
    }

    public function test_details_show_the_viewers_own_vouch_and_its_outcome(): void
    {
        $restaurant = $this->makeRestaurant();
        $voucher = $this->makeUser();
        Sanctum::actingAs($voucher, ['*']);

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertJsonPath('halal.myReport', null);

        $id = $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'Owner is Muslim, no pork'])->json('submission.id');
        $this->submit($id)->assertOk();
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")
            ->assertJsonPath('halal.myReport.status', 'pending')
            ->assertJsonPath('halal.myReport.claim', 'muslim_friendly')
            ->assertJsonPath('halal.myReport.comment', 'Owner is Muslim, no pork')
            ->assertJsonPath('halal.myReport.photoCount', 0)
            ->assertJsonPath('halal.myReport.maxPhotos', 5);

        $this->moderation()->requestChanges(RestaurantSubmission::find($id), 'Add a storefront photo please', $this->makeAdmin());
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")
            ->assertJsonPath('halal.myReport.status', 'changes_requested')
            ->assertJsonPath('halal.myReport.reviewNote', 'Add a storefront photo please');

        // Someone else never sees it.
        Sanctum::actingAs($this->makeUser(), ['*']);
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->assertJsonPath('halal.myReport', null);
    }

    public function test_downgrade_keeps_claim_and_records_resolved_status(): void
    {
        $restaurant = $this->makeRestaurant();
        $report = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified);

        $this->moderation()->approve($report, $this->makeAdmin(), halal: ['resolved_status' => 'muslim_friendly']);

        $report->refresh();
        $this->assertSame(HalalStatus::Certified, $report->halal_claim);
        $this->assertSame(HalalStatus::MuslimFriendly, $report->halal_resolved_status);
        $this->assertSame(HalalStatus::MuslimFriendly, $restaurant->fresh()->halal_status);

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")
            ->assertJsonPath('halal.reports.0.claim', 'certified')
            ->assertJsonPath('halal.reports.0.resolvedStatus', 'muslim_friendly')
            ->assertJsonPath('halal.display.shortLabel', 'Not certified · community notes');
    }

    public function test_one_open_report_per_user_per_restaurant(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);

        $first = $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'Owner is Muslim'])->assertCreated()->json('submission.id');
        // A second open returns the same draft, updated.
        $again = $this->openReport($restaurant->id, ['claim' => 'non_halal', 'comment' => 'Actually saw pork'])->assertOk();
        $this->assertSame($first, $again->json('submission.id'));
        $this->assertSame('non_halal', $again->json('submission.halalClaim'));

        $this->submit($first)->assertOk();
        $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'x'])->assertStatus(422);
        $this->assertSame(1, RestaurantSubmission::where('submission_type', 'halal_report')->count());
    }

    public function test_report_needs_comment_or_photo(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);

        $id = $this->openReport($restaurant->id, ['claim' => 'non_halal'])->assertCreated()->json('submission.id');
        $this->submit($id)->assertStatus(422);
        $this->uploadPhoto($id, 'menu')->assertCreated();
        $this->submit($id)->assertOk();
    }

    public function test_unreadable_photo_is_a_422_not_a_500(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);
        $id = $this->openReport($restaurant->id, ['claim' => 'certified'])->json('submission.id');

        // JPEG magic bytes pass the `image` rule, but GD can't decode the rest.
        $corrupt = UploadedFile::fake()->createWithContent('cert.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\0", 64));

        $this->uploadPhoto($id, 'halal_cert', $corrupt)
            ->assertUnprocessable()
            ->assertJsonPath('errors.photo.0', "We couldn't read that photo. Try a different one.");
    }

    public function test_duplicate_photo_hash_is_flagged_and_lowers_priority(): void
    {
        $file = UploadedFile::fake()->image('same.jpg', 500, 500);
        $a = $this->makeRestaurant();
        $b = $this->makeRestaurant();

        Sanctum::actingAs($this->makeUser(), ['*']);
        $first = $this->openReport($a->id, ['claim' => 'muslim_friendly'])->json('submission.id');
        $this->uploadPhoto($first, 'storefront', $file)->assertCreated();

        Sanctum::actingAs($this->makeUser(), ['*']);
        $second = $this->openReport($b->id, ['claim' => 'muslim_friendly'])->json('submission.id');
        $this->uploadPhoto($second, 'storefront', $file)->assertCreated();
        $this->submit($second)->assertOk();

        $service = app(HalalReportService::class);
        $this->assertTrue($service->hasDuplicatePhoto(RestaurantSubmission::find($second)));
        $this->assertLessThan(50, RestaurantSubmission::find($second)->review_priority);
        $this->assertSame(-20, RestaurantSubmission::find($second)->review_priority_breakdown['rules']['duplicate_photo']);
    }

    public function test_non_halal_claim_against_certified_place_gets_top_priority(): void
    {
        $restaurant = $this->makeRestaurant();
        app(HalalVerificationService::class)->recordModeratorDecision(
            $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $this->makeAdmin(), $this->jakimCert()
        );
        Sanctum::actingAs($this->makeUser(['created_at' => now()->subYear()]), ['*']);

        $id = $this->openReport($restaurant->id, ['claim' => 'non_halal', 'comment' => 'Pork on the menu board'])->json('submission.id');
        $this->submit($id)->assertOk();

        $this->assertGreaterThanOrEqual(75, RestaurantSubmission::find($id)->review_priority);
        $this->assertSame(HalalReviewState::ConflictingEvidence, $restaurant->fresh()->halal_review_state);
        $this->assertSame(HalalStatus::Certified, $restaurant->fresh()->halal_status);
    }

    public function test_reject_and_request_changes_notify_and_update_stats(): void
    {
        $restaurant = $this->makeRestaurant();
        $reporter = $this->makeUser();
        $admin = $this->makeAdmin();

        $report = $this->makeHalalReport($restaurant, $reporter, HalalStatus::Certified);
        $this->moderation()->requestChanges($report, 'Photo is blurry', $admin);
        Notification::assertSentTo($reporter, HalalReportDecided::class);

        $report2 = $this->makeHalalReport($this->makeRestaurant(), $reporter, HalalStatus::NonHalal);
        $this->moderation()->reject($report2, 'Not the same outlet', $admin);
        $this->assertSame(1, $reporter->fresh()->contribution_stats['rejected']);
    }

    public function test_changes_requested_report_can_be_resubmitted(): void
    {
        $restaurant = $this->makeRestaurant();
        $reporter = $this->makeUser();
        Sanctum::actingAs($reporter, ['*']);
        $id = $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'x'])->json('submission.id');
        $this->submit($id)->assertOk();
        $this->moderation()->requestChanges(RestaurantSubmission::find($id), 'Add a storefront photo', $this->makeAdmin());

        $this->uploadPhoto($id, 'storefront')->assertCreated();
        $this->submit($id)->assertOk()->assertJsonPath('submission.status', 'pending');
    }

    public function test_overturned_report_notifies_contributor_and_counts_as_overturned(): void
    {
        $restaurant = $this->makeRestaurant();
        $first = $this->makeUser();
        $admin = $this->makeAdmin();

        $this->moderation()->approve($this->makeHalalReport($restaurant, $first, HalalStatus::MuslimFriendly), $admin);
        $this->moderation()->approve($this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::NonHalal), $admin);

        Notification::assertSentTo($first, HalalReportSuperseded::class);
        $this->assertSame(1, $first->fresh()->contribution_stats['overturned']);
        $this->assertFalse($first->fresh()->trusted_contributor);
    }

    public function test_trusted_contributor_after_five_clean_approvals(): void
    {
        $reporter = $this->makeUser();
        $admin = $this->makeAdmin();
        foreach (range(1, 5) as $_) {
            $this->moderation()->approve($this->makeHalalReport($this->makeRestaurant(), $reporter, HalalStatus::MuslimFriendly), $admin);
        }

        $this->assertTrue($reporter->fresh()->trusted_contributor);
        // Never exposed.
        Sanctum::actingAs($reporter, ['*']);
        $this->assertStringNotContainsString('trusted', $this->getJson('/api/v1/auth/me')->getContent());
    }

    public function test_owner_claim_flow_and_owner_reports_are_tagged_but_still_moderated(): void
    {
        $restaurant = $this->makeRestaurant();
        $owner = $this->makeUser(['created_at' => now()->subYear()]);
        Sanctum::actingAs($owner, ['*']);

        $claimId = $this->postJson("/api/v1/restaurants/{$restaurant->id}/owner-claim", ['contactPhone' => '0123456789'])->assertCreated()->json('submission.id');
        $this->submit($claimId)->assertStatus(422); // proof photo required
        $this->uploadPhoto($claimId, 'other')->assertCreated();
        $this->submit($claimId)->assertOk();

        $this->moderation()->approve(RestaurantSubmission::find($claimId), $this->makeAdmin());
        $this->assertTrue(RestaurantOwner::isVerifiedOwner($owner->id, $restaurant->id));
        Notification::assertSentTo($owner, OwnerClaimDecided::class);

        $reportId = $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'We are Muslim-owned, no pork no alcohol'])->json('submission.id');
        $this->submit($reportId)->assertOk();
        $this->assertSame(HalalStatus::Unknown, $restaurant->fresh()->halal_status); // still needs moderation
        $this->assertSame(20, RestaurantSubmission::find($reportId)->review_priority_breakdown['rules']['verified_owner']);

        $this->moderation()->approve(RestaurantSubmission::find($reportId), $this->makeAdmin());
        $this->assertSame('restaurant_owner', $restaurant->fresh()->activeHalalVerification->evidence_source->value);
        $this->assertStringStartsWith('Verified from owner evidence', $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")->json('halal.display.verificationLabel'));
    }

    public function test_per_user_open_report_cap(): void
    {
        $user = $this->makeUser();
        foreach (range(1, 10) as $_) {
            $this->makeHalalReport($this->makeRestaurant(), $user, HalalStatus::MuslimFriendly, 'pending');
        }
        Sanctum::actingAs($user, ['*']);

        $this->openReport($this->makeRestaurant()->id, ['claim' => 'muslim_friendly', 'comment' => 'x'])->assertStatus(429);
    }

    public function test_abandoned_drafts_do_not_count_toward_the_open_report_cap(): void
    {
        $user = $this->makeUser();
        foreach (range(1, 10) as $_) {
            $this->makeHalalReport($this->makeRestaurant(), $user, HalalStatus::MuslimFriendly, 'draft');
        }
        Sanctum::actingAs($user, ['*']);

        $this->openReport($this->makeRestaurant()->id, ['claim' => 'muslim_friendly', 'comment' => 'x'])->assertCreated();
    }

    public function test_retrying_a_failed_vouch_resumes_the_same_draft_with_its_photos(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);

        $id = $this->openReport($restaurant->id, ['claim' => 'certified'])->json('submission.id');
        $this->uploadPhoto($id)->assertCreated();
        // The app died before submit — the retry reopens the same draft, cert photo still attached.
        $this->assertSame($id, $this->openReport($restaurant->id, ['claim' => 'certified', 'comment' => 'Retry'])->assertOk()->json('submission.id'));
        $this->getJson("/api/v1/restaurants/{$restaurant->id}/details")
            ->assertJsonPath('halal.myReport.status', 'draft')
            ->assertJsonPath('halal.myReport.photoCount', 1)
            ->assertJsonPath('halal.myReport.certPhotoCount', 1);
        $this->submit($id)->assertOk();
    }

    public function test_switching_away_from_certified_drops_the_drafts_cert_photos(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);

        $id = $this->openReport($restaurant->id, ['claim' => 'certified'])->json('submission.id');
        $this->uploadPhoto($id, 'halal_cert')->assertCreated();
        $this->uploadPhoto($id, 'storefront')->assertCreated();
        $certPath = RestaurantPhoto::where('restaurant_submission_id', $id)->where('photo_type', 'halal_cert')->value('path');

        $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'No cert after all'])->assertOk();

        $this->assertSame(['storefront'], RestaurantPhoto::where('restaurant_submission_id', $id)->pluck('photo_type')->all());
        Storage::disk(config('restaurant_photos.pending_disk'))->assertMissing($certPath);
    }

    public function test_submit_survives_a_queue_outage(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);
        $id = $this->openReport($restaurant->id, ['claim' => 'muslim_friendly', 'comment' => 'Owner is Muslim'])->json('submission.id');

        config(['queue.default' => 'unreachable', 'queue.connections.unreachable' => ['driver' => 'does-not-exist']]);

        $this->submit($id)->assertOk()->assertJsonPath('submission.status', 'pending');
        $this->assertSame('pending', RestaurantSubmission::find($id)->status);
    }

    public function test_approval_skips_a_pending_photo_whose_file_is_gone(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);
        $id = $this->openReport($restaurant->id, ['claim' => 'non_halal', 'comment' => 'Beer on the menu'])->json('submission.id');
        $this->uploadPhoto($id, 'menu')->assertCreated();
        $this->uploadPhoto($id, 'storefront')->assertCreated();
        $this->submit($id)->assertOk();

        // e.g. the pending disk was wiped by a redeploy before review.
        $lost = RestaurantPhoto::where('restaurant_submission_id', $id)->where('photo_type', 'menu')->first();
        Storage::disk($lost->disk)->delete($lost->path);

        $this->moderation()->approve(RestaurantSubmission::find($id), $this->makeAdmin());
        app(RestaurantPhotoPromotionService::class)->promote(RestaurantSubmission::find($id), $restaurant);

        $this->assertNull(RestaurantPhoto::find($lost->id));
        $kept = RestaurantPhoto::where('restaurant_submission_id', $id)->sole();
        $this->assertSame($restaurant->id, $kept->restaurant_id);
        Storage::disk(config('restaurant_photos.public_disk'))->assertExists($kept->path);
    }

    public function test_owner_claim_retry_resumes_the_abandoned_draft(): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs($this->makeUser(), ['*']);

        $first = $this->postJson("/api/v1/restaurants/{$restaurant->id}/owner-claim", ['contactPhone' => '0123456789'])->assertCreated()->json('submission.id');
        // Proof upload failed, user tries again — same draft back, not a 422 lockout.
        $again = $this->postJson("/api/v1/restaurants/{$restaurant->id}/owner-claim", ['contactPhone' => '0199999999'])->assertOk()->json('submission.id');
        $this->assertSame($first, $again);
        $this->assertSame('0199999999', RestaurantSubmission::find($first)->contact_phone);

        $this->uploadPhoto($first, 'other')->assertCreated();
        $this->submit($first)->assertOk();
        $this->postJson("/api/v1/restaurants/{$restaurant->id}/owner-claim", ['contactPhone' => '0123456789'])->assertStatus(422);
    }
}
