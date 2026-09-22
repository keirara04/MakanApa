<?php

namespace Tests\Feature;

use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Notifications\CommunitySubmissionDecided;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\NotificationCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommunitySubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(User $submitter): RestaurantSubmission
    {
        return RestaurantSubmission::create([
            'user_id' => $submitter->id,
            'submission_type' => 'new_place',
            'source_type' => 'manual',
            'status' => 'pending',
            'name' => 'Test Place',
            'latitude' => 2.9,
            'longitude' => 101.7,
            'location_source' => 'map_pin',
        ]);
    }

    public function test_approving_a_submission_notifies_the_submitter(): void
    {
        Notification::fake();

        $submitter = User::factory()->create();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $submission = $this->makeSubmission($submitter);

        app(RestaurantSubmissionModerationService::class)->approve($submission, $admin);

        Notification::assertSentTo(
            $submitter,
            CommunitySubmissionDecided::class,
        );
    }

    public function test_rejecting_a_submission_notifies_the_submitter(): void
    {
        Notification::fake();

        $submitter = User::factory()->create();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $submission = $this->makeSubmission($submitter);

        app(RestaurantSubmissionModerationService::class)->reject($submission, 'Missing photos', $admin);

        Notification::assertSentTo($submitter, CommunitySubmissionDecided::class);
    }

    public function test_does_not_notify_when_submitter_opted_out(): void
    {
        Notification::fake();

        $submitter = User::factory()->create([
            'notification_preferences' => [NotificationCategory::COMMUNITY_SUBMISSIONS => false],
        ]);
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $submission = $this->makeSubmission($submitter);

        app(RestaurantSubmissionModerationService::class)->approve($submission, $admin);

        // approve() still calls ->notify() unconditionally, but via() returns no channels for
        // an opted-out user, so Laravel's NotificationFake never records it as sent.
        Notification::assertNotSentTo($submitter, CommunitySubmissionDecided::class);
    }
}
