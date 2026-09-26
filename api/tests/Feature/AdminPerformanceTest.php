<?php

namespace Tests\Feature;

use App\Filament\Pages\BroadcastNotification;
use App\Filament\Resources\RestaurantSubmissions\Pages\ViewRestaurantSubmission;
use App\Filament\Widgets\SystemHealthWidget;
use App\Jobs\SendNotificationBroadcast;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\NotificationBroadcast;
use App\Models\NotificationDelivery;
use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Models\User;
use App\Notifications\ReleaseAnnouncement;
use App\Services\Brain\BrainEvaluationReport;
use App\Services\NotificationBroadcastService;
use App\Services\RestaurantSubmissionModerationService;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

/**
 * Behavior that changed while making the admin panel cheaper to load: the SQL-prefiltered
 * duplicate check, signed evidence-photo URLs instead of inlined base64, queued broadcasts with
 * bulk delivery rows, queue health from the real queue connection, and the SQL concentration
 * report.
 */
class AdminPerformanceTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private function newPlaceSubmission(User $user, string $name, float $latitude, float $longitude): RestaurantSubmission
    {
        return RestaurantSubmission::create([
            'user_id' => $user->id,
            'submission_type' => 'new_place',
            'source_type' => 'manual',
            'name' => $name,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'location_source' => 'current_location',
            'changed_fields' => [],
            'status' => 'pending',
        ]);
    }

    private function pendingEvidencePhoto(RestaurantSubmission $submission): RestaurantPhoto
    {
        $disk = config('restaurant_photos.pending_disk');
        Storage::disk($disk)->put('pending/cert.jpg', 'fake-jpeg-bytes');

        return RestaurantPhoto::create([
            'restaurant_submission_id' => $submission->id,
            'disk' => $disk,
            'path' => 'pending/cert.jpg',
            'photo_type' => 'halal_cert',
            'uploaded_by' => $submission->user_id,
            'is_active' => false,
        ]);
    }

    public function test_duplicate_hint_only_matches_active_similar_names_within_100m(): void
    {
        // Created first so a missing distance check would find them before the real match.
        $this->makeRestaurant(['name' => 'Pelita Cafe', 'latitude' => 2.9284 + 0.00135, 'longitude' => 101.7802]);
        $this->makeRestaurant(['name' => 'Pelita', 'latitude' => 2.9284 + 0.00001, 'longitude' => 101.7802, 'is_active' => false]);
        $this->makeRestaurant(['name' => 'Pelita', 'latitude' => 2.9384, 'longitude' => 101.7802]);
        $match = $this->makeRestaurant(['name' => 'Nasi Kandar Pelita', 'latitude' => 2.9284 + 0.00045, 'longitude' => 101.7802]);

        $hint = app(RestaurantSubmissionModerationService::class)
            ->duplicateHint($this->newPlaceSubmission($this->makeUser(), 'Pelita', 2.9284, 101.7802));

        $this->assertSame($match->id, $hint['id']);
        $this->assertEqualsWithDelta(50, $hint['distanceMeters'], 2);
    }

    public function test_duplicate_hint_is_null_when_nothing_is_nearby(): void
    {
        $this->makeRestaurant(['name' => 'Pelita', 'latitude' => 3.1, 'longitude' => 101.7]);

        $this->assertNull(app(RestaurantSubmissionModerationService::class)
            ->duplicateHint($this->newPlaceSubmission($this->makeUser(), 'Pelita', 2.9284, 101.7802)));
    }

    public function test_evidence_photo_route_needs_a_signed_url_and_a_superadmin_session(): void
    {
        $submission = $this->makeHalalReport($this->makeRestaurant(), $this->makeUser(), HalalStatus::Certified);
        $photo = $this->pendingEvidencePhoto($submission);
        $signed = URL::temporarySignedRoute('admin.panel.submission-photos.show', now()->addMinutes(5), ['photo' => $photo->id]);
        $unsigned = route('admin.panel.submission-photos.show', ['photo' => $photo->id]);

        $this->get($signed)->assertUnauthorized();
        $this->actingAs($this->makeUser(), 'web')->get($signed)->assertForbidden();
        $this->actingAs($this->makeAdmin(), 'web')->get($unsigned)->assertForbidden();

        $this->actingAs($this->makeAdmin(), 'web')->get($signed)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_submission_page_links_private_evidence_instead_of_inlining_it(): void
    {
        $submission = $this->makeHalalReport($this->makeRestaurant(), $this->makeUser(), HalalStatus::Certified);
        $this->pendingEvidencePhoto($submission);
        $this->actingAs($this->makeAdmin(), 'web');

        Livewire::test(ViewRestaurantSubmission::class, ['record' => $submission->id])
            ->assertOk()
            ->assertSee('admin-files/submission-photos')
            ->assertDontSee('data:image/jpeg;base64', false);
    }

    public function test_admin_broadcast_is_queued_instead_of_sent_inside_the_request(): void
    {
        Bus::fake([SendNotificationBroadcast::class]);
        $this->actingAs($this->makeAdmin(), 'web');
        User::factory()->create();

        Livewire::test(BroadcastNotification::class)
            ->fillForm(['category' => 'account_admin', 'title' => 'Heads up', 'body' => 'Something changed'])
            ->call('send')
            ->assertNotified('Broadcast queued');

        Bus::assertDispatched(SendNotificationBroadcast::class, fn (SendNotificationBroadcast $job) => $job->broadcast->title === 'Heads up');
        $this->assertSame(0, NotificationDelivery::count());
    }

    public function test_fan_out_writes_one_delivery_row_per_user_and_notifies_reachable_ones(): void
    {
        Notification::fake();
        $optedIn = User::factory()->create(['notification_preferences' => ['release_announcements' => true]]);
        $optedOut = User::factory()->create();
        $broadcast = NotificationBroadcast::create(['category' => 'release_announcements', 'version' => '1.2.0', 'message' => 'New stuff', 'source' => 'manual']);

        $considered = app(NotificationBroadcastService::class)->fanOut($broadcast, new ReleaseAnnouncement('1.2.0', 'New stuff', null));

        $this->assertSame(1, $considered);
        $this->assertSame(1, $broadcast->fresh()->recipients_considered);
        $this->assertSame('queued', NotificationDelivery::where('user_id', $optedIn->id)->sole()->status);
        $this->assertSame('skipped_preference', NotificationDelivery::where('user_id', $optedOut->id)->sole()->status);
        Notification::assertSentTo($optedIn, ReleaseAnnouncement::class);
        Notification::assertNotSentTo($optedOut, ReleaseAnnouncement::class);
    }

    public function test_system_health_reads_the_configured_queue_connection(): void
    {
        config(['queue.default' => 'database']);
        Queue::connection('database')->pushRaw(json_encode(['displayName' => 'TestJob', 'job' => 'TestJob', 'data' => [], 'createdAt' => now()->subMinutes(10)->getTimestamp()]));
        $this->actingAs($this->makeAdmin(), 'web');

        Livewire::test(SystemHealthWidget::class)
            ->assertOk()
            ->assertSee('1 pending');
    }

    public function test_concentration_report_matches_per_version_accept_patterns(): void
    {
        $mamakA = $this->makeRestaurant(['food_category' => 'mamak']);
        $mamakB = $this->makeRestaurant(['food_category' => 'mamak']);
        $cafe = $this->makeRestaurant(['food_category' => 'cafe']);
        $this->makeRestaurant(['food_category' => 'dessert']); // active, never picked
        $v2User = $this->makeUser();
        $v1User = $this->makeUser();

        $accept = function (string $version, ?User $user, ?string $installationId, Restaurant $restaurant, int $minutesAgo): void {
            $decision = Decision::create([
                'user_id' => $user?->id, 'installation_id' => $installationId, 'mode' => 'solo',
                'latitude' => 2.9, 'longitude' => 101.7, 'algorithm_version' => $version,
            ]);
            DecisionRecommendation::create([
                'decision_id' => $decision->id, 'restaurant_id' => $restaurant->id, 'rank' => 1, 'score' => 90,
                'shown_at' => now()->subMinutes($minutesAgo), 'accepted_at' => now()->subMinutes($minutesAgo),
            ]);
        };

        // v2: one person goes mamak → mamak → cafe (1 repeat in 2 pairs); an anonymous install picks once.
        $accept('v2', $v2User, null, $mamakA, 30);
        $accept('v2', $v2User, null, $mamakB, 20);
        $accept('v2', $v2User, null, $cafe, 10);
        $accept('v2', null, 'install-x', $mamakA, 5);
        $accept('v2', $v2User, null, $cafe, 60 * 24 * 40); // outside the window
        // v1: the same place twice in a row.
        $accept('v1', $v1User, null, $cafe, 30);
        $accept('v1', $v1User, null, $cafe, 10);

        $report = app(BrainEvaluationReport::class)->concentration(30);

        $this->assertSame(['v1', 'v2'], array_column($report, 'version'));
        [$v1, $v2] = $report;

        $this->assertSame(2, $v1['accepts']);
        $this->assertEqualsWithDelta(1.0, $v1['top10Share'], 1e-9);
        $this->assertEqualsWithDelta(0.25, $v1['coverage'], 1e-9);
        $this->assertSame(1, $v1['categories']);
        $this->assertEqualsWithDelta(1.0, $v1['repeatCategoryRate'], 1e-9);

        $this->assertSame(4, $v2['accepts']);
        $this->assertEqualsWithDelta(1.0, $v2['top10Share'], 1e-9);
        $this->assertEqualsWithDelta(0.75, $v2['coverage'], 1e-9);
        $this->assertSame(2, $v2['categories']);
        $this->assertEqualsWithDelta(0.5, $v2['repeatCategoryRate'], 1e-9);
    }
}
