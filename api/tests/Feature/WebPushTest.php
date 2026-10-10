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

    /** An uncompressed P-256 point (0x04 + 64 bytes) and a 16-byte auth secret, base64url. */
    private const P256DH = 'BAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8gISIjJCUmJygpKissLS4vMDEyMzQ1Njc4OTo7PD0-P0A';

    private const AUTH = 'AAECAwQFBgcICQoLDA0ODw';

    /** @return array<string, mixed> */
    private function subscription(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => self::P256DH, 'auth' => self::AUTH], 'contentEncoding' => 'aes128gcm'];
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

    public function test_only_browser_push_services_are_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/me/web-push', $this->subscription('https://attacker.example/push'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/me/web-push', $this->subscription('https://wns2-par02p.notify.windows.com/w/?token=abc'))
            ->assertOk();
    }

    public function test_keys_that_cannot_encrypt_are_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/me/web-push', ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'BPublicKeyValue', 'auth' => 'authSecret']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
    }

    public function test_an_account_keeps_at_most_ten_browsers(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $n) {
            $this->actingAs($user)->postJson('/api/v1/me/web-push', $this->subscription(self::ENDPOINT.$n))->assertOk();
        }

        $endpoints = $user->pushSubscriptions()->pluck('endpoint');
        $this->assertCount(10, $endpoints);
        $this->assertNotContains(self::ENDPOINT.'1', $endpoints);
        $this->assertContains(self::ENDPOINT.'12', $endpoints);
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

        // No web subscription: no web job (so web push settings can't touch iOS-only users).
        $this->assertNotContains(WebPushChannel::class, $notification->via($user));

        $user->updatePushSubscription(self::ENDPOINT, self::P256DH, self::AUTH, 'aes128gcm');
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

        $user->updatePushSubscription(self::ENDPOINT, self::P256DH, self::AUTH, 'aes128gcm');

        $this->assertNotSame('no_device', $eligibility->reasonToSkip($user->fresh(), $state, $noon));
    }

    public function test_deleting_an_account_removes_its_push_subscriptions(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription(self::ENDPOINT, self::P256DH, self::AUTH, 'aes128gcm');

        app(UserAccountDeletionService::class)->delete($user);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => self::ENDPOINT]);
    }
}
