<?php

namespace Tests\Feature;

use App\Models\MealNudgeState;
use App\Models\User;
use App\Notifications\CommunityPostReplied;
use App\Notifications\ReleaseAnnouncement;
use App\Services\Nudges\MealNudgeEligibility;
use App\Services\UserAccountDeletionService;
use App\Support\NotificationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    /** @return array<string, mixed> */
    private function subscription(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKeyValue', 'auth' => 'authSecret'], 'contentEncoding' => 'aes128gcm'];
    }

    public function test_a_browser_subscribes_and_unsubscribes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/me/web-push', $this->subscription())->assertOk()->assertJson(['subscribed' => true]);
        $this->assertSame([self::ENDPOINT], $user->pushSubscriptions()->pluck('endpoint')->all());

        $this->actingAs($user)->deleteJson('/api/v1/me/web-push', ['endpoint' => self::ENDPOINT])->assertOk()->assertJson(['subscribed' => false]);
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_a_browser_that_switches_account_moves_its_subscription(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)->postJson('/api/v1/me/web-push', $this->subscription())->assertOk();
        $this->actingAs($second)->postJson('/api/v1/me/web-push', $this->subscription())->assertOk();

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());
    }

    public function test_only_https_push_endpoints_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/me/web-push', $this->subscription('http://example.test/push'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');
    }

    public function test_the_public_key_is_served(): void
    {
        config(['webpush.vapid.public_key' => 'BTestPublicKey']);

        $this->actingAs(User::factory()->create())->getJson('/api/v1/web-push/key')->assertOk()->assertJson(['publicKey' => 'BTestPublicKey']);
    }

    public function test_pushes_go_to_the_web_with_the_same_payload_as_ios(): void
    {
        $user = User::factory()->create(['notification_preferences' => [NotificationCategory::RELEASE_ANNOUNCEMENTS => true]]);
        $notification = new ReleaseAnnouncement('2.1', 'Group mode is here.');

        $this->assertContains(WebPushChannel::class, $notification->via($user));
        $this->assertContains('apn', $notification->via($user));

        $message = $notification->toWebPush($user)->toArray();
        $this->assertSame('MakanApa 2.1 is here', $message['title']);
        $this->assertSame('Group mode is here.', $message['body']);
        $this->assertSame('release', $message['data']['type']);
        $this->assertSame('release', $message['tag']);
    }

    public function test_web_pushes_respect_the_same_preferences(): void
    {
        $user = User::factory()->create(['notification_preferences' => [NotificationCategory::COMMUNITY_REPLIES => false]]);
        $notification = (new \ReflectionClass(CommunityPostReplied::class))->newInstanceWithoutConstructor();

        $this->assertSame([], $notification->via($user));
    }

    public function test_mealtime_nudges_can_reach_someone_with_only_the_web_app(): void
    {
        config(['nudges.enabled' => true]);
        $user = User::factory()->create(['notification_preferences' => [NotificationCategory::MEALTIME_NUDGES => true]]);
        $state = MealNudgeState::create(['user_id' => $user->id]);
        $eligibility = app(MealNudgeEligibility::class);
        $noon = CarbonImmutable::parse('2026-10-12 04:30:00', 'UTC');

        $this->assertSame('no_device', $eligibility->reasonToSkip($user, $state, $noon));

        $user->updatePushSubscription(self::ENDPOINT, 'BPublicKeyValue', 'authSecret', 'aes128gcm');

        $this->assertNotSame('no_device', $eligibility->reasonToSkip($user->fresh(), $state, $noon));
    }

    public function test_deleting_an_account_removes_its_push_subscriptions(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription(self::ENDPOINT, 'BPublicKeyValue', 'authSecret', 'aes128gcm');

        app(UserAccountDeletionService::class)->delete($user);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => self::ENDPOINT]);
    }
}
