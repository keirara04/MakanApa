<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CommunityPost;
use App\Models\Restaurant;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Notifications\CommunityPostReacted;
use App\Notifications\CommunityPostReplied;
use App\Services\Community\CommunityPostModerationService;
use App\Support\CommunityReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommunityPostTest extends TestCase
{
    use RefreshDatabase;

    private University $ku;

    private University $um;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('moderation.community_posts.min_account_age_hours', 0);
        $this->ku = University::create(['name' => 'Kolej Universiti', 'short_name' => 'KU', 'country' => 'Malaysia', 'active' => true]);
        $this->um = University::create(['name' => 'Universiti Malaya', 'short_name' => 'UM', 'country' => 'Malaysia', 'active' => true]);
    }

    private function member(?University $university = null, array $attributes = []): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active', ...$attributes]);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'university', 'university_id' => ($university ?? $this->ku)->id]);

        return $user->fresh();
    }

    private function signIn(User $user): static
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    private function publish(User $author, string $body = 'Nasi lemak at the cafe is 🔥', array $extra = []): int
    {
        return $this->signIn($author)->postJson('/api/v1/community/posts', ['body' => $body, ...$extra])
            ->assertCreated()->json('post.id');
    }

    public function test_member_can_post_and_see_it_in_their_community_feed(): void
    {
        $author = $this->member();
        $this->publish($author);

        $this->signIn($this->member())->getJson('/api/v1/community/posts')
            ->assertOk()
            ->assertJsonCount(1, 'posts')
            ->assertJsonPath('posts.0.body', 'Nasi lemak at the cafe is 🔥')
            ->assertJsonPath('posts.0.author.name', $author->name)
            ->assertJsonPath('posts.0.isMine', false)
            ->assertJsonPath('canPost', true);

        $this->assertDatabaseHas('community_posts', ['user_id' => $author->id, 'university_id' => $this->ku->id, 'area_id' => null]);
    }

    public function test_posts_never_leak_across_communities(): void
    {
        $this->publish($this->member($this->ku));

        $this->signIn($this->member($this->um))->getJson('/api/v1/community/posts')
            ->assertOk()->assertJsonCount(0, 'posts');
    }

    public function test_area_members_post_to_their_area(): void
    {
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'area', 'area_id' => $kl->id]);

        $this->publish($user->fresh());

        $this->assertDatabaseHas('community_posts', ['user_id' => $user->id, 'area_id' => $kl->id, 'university_id' => null]);
    }

    public function test_unaffiliated_user_gets_an_empty_read_only_board(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->signIn($user)->getJson('/api/v1/community/posts')
            ->assertOk()->assertJsonPath('canPost', false)->assertJsonCount(0, 'posts');

        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'hello'])->assertForbidden();
    }

    public function test_new_accounts_must_wait_unless_trusted(): void
    {
        Config::set('moderation.community_posts.min_account_age_hours', 24);

        $this->signIn($this->member())->postJson('/api/v1/community/posts', ['body' => 'hi'])->assertForbidden();
        $this->signIn($this->member(attributes: ['trusted_contributor' => true]))->postJson('/api/v1/community/posts', ['body' => 'hi'])->assertCreated();

        $old = $this->member();
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->signIn($old)->postJson('/api/v1/community/posts', ['body' => 'hi'])->assertCreated();
    }

    public function test_suspended_user_cannot_post(): void
    {
        $this->signIn($this->member(attributes: ['status' => 'suspended']))
            ->postJson('/api/v1/community/posts', ['body' => 'hi'])->assertForbidden();
    }

    public function test_content_filter_rejects_profanity_and_links(): void
    {
        $user = $this->member();

        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'this place is FUUUCK bad'])->assertUnprocessable();
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'pukimak betul'])->assertUnprocessable();
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'promo at https://spam.example'])->assertUnprocessable();
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'check spamsite.com'])->assertUnprocessable();
        // Food vocabulary that overlaps with insults elsewhere must still be postable.
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'Confirm takde babi, halal cert on the wall'])->assertCreated();
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => str_repeat('a', 281)])->assertUnprocessable();
    }

    public function test_post_can_tag_an_active_restaurant(): void
    {
        $restaurant = Restaurant::create(['name' => 'Warung Pak Ali', 'latitude' => 3.1, 'longitude' => 101.6, 'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-ali']);
        $user = $this->member();

        $this->publish($user, 'Best roti canai', ['restaurantId' => $restaurant->id]);

        $this->signIn($user)->getJson("/api/v1/community/posts?restaurantId={$restaurant->id}")
            ->assertOk()->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.restaurant.name', 'Warung Pak Ali');
    }

    public function test_one_level_replies_with_preview_and_counts(): void
    {
        Notification::fake();
        $author = $this->member();
        $parentId = $this->publish($author);
        $replier = $this->member();

        foreach (['one', 'two', 'three'] as $body) {
            $this->signIn($replier)->postJson('/api/v1/community/posts', ['body' => $body, 'parentId' => $parentId])->assertCreated();
        }

        $feed = $this->signIn($author)->getJson('/api/v1/community/posts')->assertOk();
        $feed->assertJsonCount(1, 'posts'); // replies aren't top-level feed items
        $feed->assertJsonPath('posts.0.replyCount', 3);
        $feed->assertJsonCount(2, 'posts.0.replies');
        $feed->assertJsonPath('posts.0.replies.0.body', 'two');
        $feed->assertJsonPath('posts.0.replies.1.body', 'three');

        $this->signIn($author)->getJson("/api/v1/community/posts/{$parentId}")
            ->assertOk()->assertJsonPath('post.id', $parentId)->assertJsonCount(3, 'replies')->assertJsonPath('replies.0.body', 'one');

        Notification::assertSentToTimes($author, CommunityPostReplied::class, 3);
    }

    public function test_cannot_reply_to_a_reply_or_tag_a_place_in_a_reply(): void
    {
        $parentId = $this->publish($this->member());
        $user = $this->member();
        $replyId = $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'r', 'parentId' => $parentId])->json('post.id');
        $restaurant = Restaurant::create(['name' => 'X', 'latitude' => 3.1, 'longitude' => 101.6, 'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'ChIJ-x']);

        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'nested', 'parentId' => $replyId])->assertUnprocessable();
        $this->signIn($user)->postJson('/api/v1/community/posts', ['body' => 'tag', 'parentId' => $parentId, 'restaurantId' => $restaurant->id])->assertUnprocessable();
    }

    public function test_cannot_reply_across_communities(): void
    {
        $parentId = $this->publish($this->member($this->ku));

        $this->signIn($this->member($this->um))
            ->postJson('/api/v1/community/posts', ['body' => 'hi', 'parentId' => $parentId])->assertNotFound();
        $this->signIn($this->member($this->um))->getJson("/api/v1/community/posts/{$parentId}")->assertNotFound();
    }

    public function test_deleting_a_reply_updates_reply_count_and_only_author_can_delete(): void
    {
        $parentId = $this->publish($this->member());
        $replier = $this->member();
        $replyId = $this->signIn($replier)->postJson('/api/v1/community/posts', ['body' => 'r', 'parentId' => $parentId])->json('post.id');

        $this->signIn($this->member())->deleteJson("/api/v1/community/posts/{$replyId}")->assertForbidden();
        $this->signIn($replier)->deleteJson("/api/v1/community/posts/{$replyId}")->assertOk();

        $this->assertSame(0, CommunityPost::find($parentId)->reply_count);
        $this->assertSoftDeleted('community_posts', ['id' => $replyId]);
    }

    public function test_reaction_toggle_and_switch(): void
    {
        Notification::fake();
        $author = $this->member(attributes: ['notification_preferences' => ['community_reactions' => true]]);
        $postId = $this->publish($author);
        $reactor = $this->member();

        $this->signIn($reactor)->postJson("/api/v1/community/posts/{$postId}/react", ['type' => 'fire'])
            ->assertOk()->assertJsonPath('myReaction', 'fire')->assertJsonPath('reactionCount', 1)->assertJsonPath('reactions.fire', 1);
        $this->signIn($reactor)->postJson("/api/v1/community/posts/{$postId}/react", ['type' => 'up'])
            ->assertOk()->assertJsonPath('myReaction', 'up')->assertJsonPath('reactionCount', 1);
        $this->signIn($reactor)->postJson("/api/v1/community/posts/{$postId}/react", ['type' => 'up'])
            ->assertOk()->assertJsonPath('myReaction', null)->assertJsonPath('reactionCount', 0);
        $this->signIn($reactor)->postJson("/api/v1/community/posts/{$postId}/react", ['type' => 'nope'])->assertUnprocessable();

        // Only the first new reaction notifies (not the switch/removal), and only for opted-in
        // users — reactions default off.
        Notification::assertSentToTimes($author, CommunityPostReacted::class, 1);
        $this->assertSame([], (new CommunityPostReacted(CommunityPost::find($postId), CommunityReaction::Fire, $reactor))->via($this->member()));
    }

    public function test_reports_auto_hide_at_threshold_and_hide_replies(): void
    {
        $author = $this->member();
        $postId = $this->publish($author);

        $this->signIn($author)->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'spam'])->assertUnprocessable();

        $reporter = $this->member();
        $this->signIn($reporter)->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'spam'])->assertOk();
        $this->signIn($reporter)->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'spam'])->assertOk(); // idempotent
        $this->assertSame('visible', CommunityPost::find($postId)->status);

        $this->signIn($this->member())->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'offensive'])->assertOk();
        $this->signIn($this->member())->postJson("/api/v1/community/posts/{$postId}/report", ['reason' => 'harassment'])->assertOk();

        $post = CommunityPost::find($postId);
        $this->assertSame('hidden', $post->status);
        $this->assertSame(3, $post->report_count);

        $viewer = $this->member();
        $this->signIn($viewer)->getJson('/api/v1/community/posts')->assertJsonCount(0, 'posts');
        $this->signIn($viewer)->getJson("/api/v1/community/posts/{$postId}")->assertNotFound();
        $this->signIn($viewer)->postJson('/api/v1/community/posts', ['body' => 'r', 'parentId' => $postId])->assertNotFound();
    }

    public function test_admin_restore_resolves_reports_and_remove_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $parentId = $this->publish($this->member());
        $replyId = $this->signIn($this->member())->postJson('/api/v1/community/posts', ['body' => 'r', 'parentId' => $parentId])->json('post.id');
        foreach (range(1, 3) as $_) {
            $this->signIn($this->member())->postJson("/api/v1/community/posts/{$replyId}/report", ['reason' => 'spam'])->assertOk();
        }
        $this->assertSame(0, CommunityPost::find($parentId)->reply_count);

        $moderation = app(CommunityPostModerationService::class);
        $moderation->restore(CommunityPost::find($replyId), $admin);

        $this->assertSame('visible', CommunityPost::find($replyId)->status);
        $this->assertSame(0, CommunityPost::find($replyId)->report_count);
        $this->assertSame(1, CommunityPost::find($parentId)->reply_count);
        $this->assertDatabaseHas('community_post_reports', ['community_post_id' => $replyId, 'resolution' => 'kept']);

        $moderation->remove(CommunityPost::find($parentId), $admin, 'rule breaking');
        $this->assertSame('removed', CommunityPost::find($parentId)->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'community_post.remove', 'subject_id' => $parentId, 'reason' => 'rule breaking']);
    }

    public function test_blocking_hides_content_both_ways_and_suppresses_reply_notifications(): void
    {
        Notification::fake();
        $alice = $this->member();
        $bob = $this->member();
        $aliceId = $this->publish($alice, 'from alice');
        $this->publish($bob, 'from bob');

        $this->signIn($alice)->postJson("/api/v1/users/{$bob->id}/block")->assertOk();
        $this->signIn($alice)->postJson("/api/v1/users/{$alice->id}/block")->assertUnprocessable();

        $this->signIn($alice)->getJson('/api/v1/community/posts')->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.body', 'from alice');
        $this->signIn($bob)->getJson('/api/v1/community/posts')->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.body', 'from bob');
        $this->signIn($bob)->postJson('/api/v1/community/posts', ['body' => 'r', 'parentId' => $aliceId])->assertNotFound();

        $this->signIn($alice)->getJson('/api/v1/me/blocks')->assertOk()->assertJsonPath('users.0.id', $bob->id);
        $this->signIn($alice)->deleteJson("/api/v1/users/{$bob->id}/block")->assertOk();
        $this->signIn($alice)->getJson('/api/v1/community/posts')->assertJsonCount(2, 'posts');

        Notification::assertNothingSent();
    }

    public function test_feed_paginates_with_a_cursor(): void
    {
        $user = $this->member();
        foreach (range(1, 3) as $i) {
            $this->publish($user, "post {$i}");
        }

        $first = $this->signIn($user)->getJson('/api/v1/community/posts?limit=2')->assertOk()->assertJsonCount(2, 'posts');
        $first->assertJsonPath('posts.0.body', 'post 3');
        $cursor = $first->json('nextCursor');
        $this->assertNotNull($cursor);

        $this->signIn($user)->getJson('/api/v1/community/posts?limit=2&cursor='.urlencode($cursor))
            ->assertOk()->assertJsonCount(1, 'posts')->assertJsonPath('posts.0.body', 'post 1')->assertJsonPath('nextCursor', null);
    }
}
