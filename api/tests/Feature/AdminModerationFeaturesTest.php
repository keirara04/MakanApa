<?php

namespace Tests\Feature;

use App\Filament\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Resources\CommunityRequests\Pages\ListCommunityRequests;
use App\Filament\Resources\RestaurantSubmissions\Pages\ListRestaurantSubmissions;
use App\Filament\Resources\TermsAcceptances\Pages\ListTermsAcceptances;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Widgets\ModerationSlaWidget;
use App\Models\AdminAuditLog;
use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\CommunityRequest;
use App\Models\RestaurantSubmission;
use App\Models\TermsAcceptance;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Notifications\ModerationSlaBreached;
use App\Support\Halal\HalalStatus;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

/**
 * Admin moderation tooling: response-time widget + hourly SLA alert, the Filament notification
 * bell (kept out of the app's own inbox), bulk moderation, the terms log and the user view.
 */
class AdminModerationFeaturesTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    private University $ku;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('moderation.community_posts.min_account_age_hours', 0);
        $this->ku = University::create(['name' => 'Kolej Universiti', 'short_name' => 'KU', 'country' => 'Malaysia', 'active' => true]);
        $this->admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    private function member(): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'university', 'university_id' => $this->ku->id]);

        return $user->fresh();
    }

    private function makePost(?User $author = null, array $attributes = []): CommunityPost
    {
        return CommunityPost::create([
            'user_id' => ($author ?? $this->member())->id,
            'university_id' => $this->ku->id,
            'body' => 'Nasi lemak at the cafe is 🔥',
            ...$attributes,
        ]);
    }

    private function openReport(CommunityPost $post, ?\DateTimeInterface $at = null): CommunityPostReport
    {
        $report = CommunityPostReport::create(['community_post_id' => $post->id, 'reporter_id' => $this->member()->id, 'reason' => 'spam']);
        if ($at) {
            $report->forceFill(['created_at' => $at])->save();
        }
        $post->update(['report_count' => $post->reports()->whereNull('resolved_at')->count()]);

        return $report;
    }

    private function actingAsAdmin(): void
    {
        $this->actingAs($this->admin, 'web');
    }

    // --- SLA ---------------------------------------------------------------------------------

    public function test_sla_alert_fires_once_per_window_for_items_older_than_12_hours(): void
    {
        Notification::fake();
        $this->openReport($this->makePost(), now()->subHours(13));

        $this->artisan('admin:moderation-sla')->assertSuccessful();
        $this->artisan('admin:moderation-sla')->assertSuccessful();

        Notification::assertSentToTimes($this->admin, ModerationSlaBreached::class, 1);
        Notification::assertSentToTimes($this->admin, FilamentDatabaseNotification::class, 1);
    }

    public function test_sla_alert_stays_quiet_while_everything_is_recent(): void
    {
        Notification::fake();
        $this->openReport($this->makePost(), now()->subHours(2));
        $submission = $this->makeHalalReport($this->makeRestaurant(), $this->member(), HalalStatus::Certified, 'draft');
        $submission->update(['status' => 'pending']);
        Notification::fake(); // ignore the "new submission" bell from the line above

        $this->artisan('admin:moderation-sla')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_sla_measures_submissions_from_when_they_were_submitted(): void
    {
        $submission = $this->makeHalalReport($this->makeRestaurant(), $this->member(), HalalStatus::Certified, 'draft');
        $this->travel(3)->days();
        $submission->update(['status' => 'pending']);

        $this->assertTrue($submission->fresh()->submitted_at->isToday());
        $this->assertTrue($submission->fresh()->created_at->lt(now()->subDays(2)));
    }

    public function test_sla_widget_shows_each_queue(): void
    {
        $this->openReport($this->makePost(), now()->subHours(30));
        $this->actingAsAdmin();

        Livewire::test(ModerationSlaWidget::class)
            ->assertOk()
            ->assertSee('Reported posts')
            ->assertSee('Halal reports &amp; owner claims', escape: false)
            ->assertSee('1d 6h')
            ->assertSee('Nothing waiting');
    }

    // --- Bell --------------------------------------------------------------------------------

    public function test_first_report_on_a_post_rings_the_admin_bell(): void
    {
        Notification::fake();
        $post = $this->makePost();

        Sanctum::actingAs($this->member(), ['*']);
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => 'spam'])->assertOk();

        Notification::assertSentTo($this->admin, FilamentDatabaseNotification::class);
    }

    public function test_submitting_for_review_and_new_community_requests_ring_the_bell(): void
    {
        Notification::fake();
        $draft = $this->makeHalalReport($this->makeRestaurant(), $this->member(), HalalStatus::Certified, 'draft');
        Notification::assertNothingSent();

        $draft->update(['status' => 'pending']);
        Notification::assertSentToTimes($this->admin, FilamentDatabaseNotification::class, 1);

        // A later unrelated update doesn't ring it again.
        $draft->update(['review_priority' => 5]);
        Notification::assertSentToTimes($this->admin, FilamentDatabaseNotification::class, 1);

        CommunityRequest::create(['user_id' => $this->member()->id, 'type' => 'university', 'name' => 'UPM', 'status' => 'pending']);
        Notification::assertSentToTimes($this->admin, FilamentDatabaseNotification::class, 2);
    }

    public function test_admin_bell_notifications_never_reach_the_app_inbox(): void
    {
        FilamentNotification::make()->title('New halal report')->sendToDatabase($this->admin);
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ReleaseAnnouncement',
            'notifiable_type' => $this->admin->getMorphClass(),
            'notifiable_id' => $this->admin->id,
            'data' => ['type' => 'release', 'title' => 'New in MakanApa'],
        ]);
        $this->assertSame(2, $this->admin->notifications()->count());

        Sanctum::actingAs($this->admin, ['*']);
        $this->getJson('/api/v1/me/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.title', 'New in MakanApa')
            ->assertJsonPath('unreadCount', 1);

        $this->postJson('/api/v1/me/notifications/read-all')->assertOk();

        $this->assertNull($this->admin->notifications()->where('type', FilamentDatabaseNotification::class)->first()->read_at);
        $filamentId = $this->admin->notifications()->where('type', FilamentDatabaseNotification::class)->value('id');
        $this->postJson("/api/v1/me/notifications/{$filamentId}/read")->assertNotFound();
    }

    // --- Bulk moderation ---------------------------------------------------------------------

    public function test_bulk_hide_restore_and_dismiss_reports_on_posts(): void
    {
        $reported = $this->makePost();
        $this->openReport($reported);
        $alsoReported = $this->makePost();
        $this->openReport($alsoReported);
        $this->actingAsAdmin();

        Livewire::test(ListCommunityPosts::class)
            ->callTableBulkAction('bulkHide', [$reported], data: ['reason' => 'Checking'])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame(CommunityPost::STATUS_HIDDEN, $reported->fresh()->status);

        Livewire::test(ListCommunityPosts::class)
            ->callTableBulkAction('bulkDismissReports', [$reported, $alsoReported]);
        // Hidden post isn't "visible with reports", so only the visible one was cleared.
        $this->assertSame(0, $alsoReported->fresh()->report_count);
        $this->assertSame(CommunityPost::STATUS_VISIBLE, $alsoReported->fresh()->status);
        $this->assertSame(1, $reported->fresh()->report_count);

        Livewire::test(ListCommunityPosts::class)
            ->callTableBulkAction('bulkRestore', [$reported]);
        $this->assertSame(CommunityPost::STATUS_VISIBLE, $reported->fresh()->status);
        $this->assertSame(0, $reported->fresh()->report_count);

        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'community_post.hide', 'subject_id' => $reported->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'community_post.restore', 'subject_id' => $alsoReported->id]);
    }

    public function test_bulk_dismiss_community_requests(): void
    {
        $requests = collect(['UPM', 'UKM'])->map(fn ($name) => CommunityRequest::create([
            'user_id' => $this->member()->id, 'type' => 'university', 'name' => $name, 'status' => 'pending',
        ]));
        $this->actingAsAdmin();

        Livewire::test(ListCommunityRequests::class)
            ->callTableBulkAction('bulkDismiss', $requests);

        $this->assertSame(['dismissed', 'dismissed'], $requests->map(fn ($r) => $r->fresh()->status)->all());
        $this->assertSame(2, AdminAuditLog::where('action', 'community_request.dismiss')->count());
    }

    public function test_bulk_reject_submissions_with_one_reason_skips_non_pending(): void
    {
        Notification::fake();
        $restaurant = $this->makeRestaurant();
        $pending = RestaurantSubmission::create([
            'user_id' => $this->member()->id, 'submission_type' => 'new_place', 'source_type' => 'manual',
            'name' => 'Warung Spam', 'latitude' => 2.9, 'longitude' => 101.7, 'location_source' => 'current_location',
            'changed_fields' => [], 'status' => 'pending',
        ]);
        $approved = $this->makeHalalReport($restaurant, $this->member(), HalalStatus::Certified, 'approved');
        $this->actingAsAdmin();

        Livewire::test(ListRestaurantSubmissions::class)
            ->filterTable('status', null)
            ->callTableBulkAction('bulkReject', [$pending, $approved], data: ['reviewNote' => 'Spam'])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('rejected', $pending->fresh()->status);
        $this->assertSame('Spam', $pending->fresh()->review_note);
        $this->assertSame('approved', $approved->fresh()->status);
    }

    // --- Terms log & user view ---------------------------------------------------------------

    public function test_terms_acceptance_log_lists_agreements(): void
    {
        $member = $this->member();
        $acceptance = TermsAcceptance::create([
            'user_id' => $member->id, 'terms_version' => '2026-09-26', 'guidelines_version' => '2026-09-26',
            'privacy_version' => '2026-09-26', 'context' => 'contribution', 'app_version' => '1.0 (15)', 'accepted_at' => now(),
        ]);
        $this->actingAsAdmin();

        Livewire::test(ListTermsAcceptances::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$acceptance])
            ->assertSee($member->email);
    }

    public function test_user_view_shows_the_support_picture(): void
    {
        $member = $this->member();
        $member->forceFill(['signup_source' => 'nudge_picks', 'upgraded_from_guest_at' => now()->subDay(), 'trusted_contributor' => true])->save();
        $post = $this->makePost($member, ['body' => 'Best roti canai near KU']);
        $this->openReport($post);
        $this->actingAsAdmin();

        Livewire::test(ViewUser::class, ['record' => $member->id])
            ->assertOk()
            ->assertSee('Registered (started as guest)')
            ->assertSee('nudge_picks')
            ->assertSee('Best roti canai near KU')
            ->assertSee('Reports against their posts');
    }
}
