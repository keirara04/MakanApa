<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Notifications\CommunityPostReported;
use App\Support\CommunityReportReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityPostReportNotificationTest extends TestCase
{
    use RefreshDatabase;

    private University $ku;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('moderation.community_posts.min_account_age_hours', 0);
        Config::set('moderation.community_posts.report_hide_threshold', 3);
        Notification::fake();

        $this->ku = University::create(['name' => 'Kolej Universiti', 'short_name' => 'KU', 'country' => 'Malaysia', 'active' => true]);
        $this->admin = User::factory()->create(['role' => 'superadmin']);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'university', 'university_id' => $this->ku->id]);

        return $user->fresh();
    }

    private function publishedPost(): CommunityPost
    {
        Sanctum::actingAs($this->member(), ['*']);
        $postId = $this->postJson('/api/v1/community/posts', ['body' => 'Nasi lemak at the cafe is 🔥'])->assertCreated()->json('post.id');

        return CommunityPost::findOrFail($postId);
    }

    private function reportAs(User $reporter, CommunityPost $post, string $reason = 'spam'): void
    {
        Sanctum::actingAs($reporter, ['*']);
        $this->postJson("/api/v1/community/posts/{$post->id}/report", ['reason' => $reason])->assertOk();
    }

    public function test_first_report_alerts_active_superadmins_by_push_and_email(): void
    {
        $suspendedAdmin = User::factory()->create(['role' => 'superadmin', 'status' => 'suspended']);
        $post = $this->publishedPost();
        $reporter = $this->member();

        $this->reportAs($reporter, $post, 'harassment');

        Notification::assertSentTo(
            $this->admin,
            CommunityPostReported::class,
            fn (CommunityPostReported $notification, array $channels) => $channels === ['apn', 'mail']
                && $notification->toApn($this->admin)->title === 'Community post reported'
                && $notification->toApn($this->admin)->custom === ['type' => 'community_post_reported', 'postId' => $post->id],
        );
        Notification::assertNotSentTo($suspendedAdmin, CommunityPostReported::class);
        Notification::assertNotSentTo($reporter, CommunityPostReported::class);
    }

    public function test_superadmin_without_email_gets_push_only(): void
    {
        $this->admin->forceFill(['email' => null])->save();
        $post = $this->publishedPost();

        $this->reportAs($this->member(), $post);

        Notification::assertSentTo($this->admin, CommunityPostReported::class, fn ($notification, array $channels) => $channels === ['apn']);
    }

    public function test_further_reports_below_the_threshold_do_not_alert_again(): void
    {
        $post = $this->publishedPost();
        $firstReporter = $this->member();

        $this->reportAs($firstReporter, $post);
        $this->reportAs($firstReporter, $post); // same reporter again is a no-op
        $this->reportAs($this->member(), $post, 'offensive');

        Notification::assertSentToTimes($this->admin, CommunityPostReported::class, 1);
    }

    public function test_report_that_auto_hides_the_post_alerts_again(): void
    {
        $post = $this->publishedPost();

        $this->reportAs($this->member(), $post);
        $this->reportAs($this->member(), $post);
        $this->reportAs($this->member(), $post);

        $this->assertSame(CommunityPost::STATUS_HIDDEN, $post->fresh()->status);
        Notification::assertSentToTimes($this->admin, CommunityPostReported::class, 2);
        Notification::assertSentTo(
            $this->admin,
            CommunityPostReported::class,
            fn (CommunityPostReported $notification) => $notification->toApn($this->admin)->title === 'Reported post auto-hidden',
        );
    }

    public function test_email_links_to_the_post_in_the_admin_panel_and_states_the_24_hour_window(): void
    {
        $post = $this->publishedPost();

        $mail = (new CommunityPostReported($post, CommunityReportReason::Spam, autoHidden: false))->toMail($this->admin);

        $this->assertStringEndsWith("/community-posts/{$post->id}", $mail->actionUrl);
        $this->assertContains('Reason: Spam or advertising', $mail->introLines);
        $this->assertStringContainsString('within 24 hours', implode(' ', $mail->outroLines));
    }
}
