<?php

namespace Tests\Feature;

use App\Filament\Widgets\OverviewStatsWidget;
use App\Models\CommunityPost;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\RestaurantPhoto;
use App\Models\University;
use App\Models\User;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

/**
 * Everything that nudges a guest toward a real account and measures whether it worked: signup
 * source + upgrade tracking, sliding guest tokens, contribution counts, and the halal "N people
 * picked this" demand signal.
 */
class GuestConversionTest extends TestCase
{
    use BuildsHalalFixtures, FakesIdentityTokens, RefreshDatabase;

    private const GOOGLE_CLIENT_ID = 'server-client-id.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        // Real bearer tokens below, not the base TestCase's pinned user.
        $this->app['auth']->forgetGuards();
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function guestWithToken(?\DateTimeInterface $expiresAt = null): array
    {
        $guest = User::factory()->guest()->create();

        return [$guest, $guest->createToken('Guest device', expiresAt: $expiresAt ?? now()->addDays(90))->plainTextToken];
    }

    private function registerBody(array $extra = []): array
    {
        return [
            'name' => 'Hakeem',
            'email' => 'hakeem@example.com',
            'password' => 'correct-horse-battery',
            'deviceLabel' => 'iPad Air',
            ...$extra,
        ];
    }

    public function test_upgrading_a_guest_records_the_source_and_upgrade_time(): void
    {
        [$guest, $guestToken] = $this->guestWithToken();

        $this->withToken($guestToken)
            ->postJson('/api/v1/auth/register', $this->registerBody(['signupSource' => 'nudge_picks']))
            ->assertCreated()
            ->assertJsonPath('user.id', $guest->id);

        $upgraded = $guest->fresh();
        $this->assertSame('nudge_picks', $upgraded->signup_source);
        $this->assertNotNull($upgraded->upgraded_from_guest_at);
    }

    public function test_fresh_sign_up_records_the_source_but_is_not_an_upgrade(): void
    {
        $id = $this->postJson('/api/v1/auth/register', $this->registerBody(['signupSource' => 'login_screen']))
            ->assertCreated()
            ->json('user.id');

        $user = User::findOrFail($id);
        $this->assertSame('login_screen', $user->signup_source);
        $this->assertNull($user->upgraded_from_guest_at);
    }

    public function test_social_upgrade_records_the_source(): void
    {
        config(['services.google.server_client_id' => self::GOOGLE_CLIENT_ID]);
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->fakeJwks())]);
        [$guest, $guestToken] = $this->guestWithToken();

        $this->withToken($guestToken)->postJson('/api/v1/auth/google', [
            'idToken' => $this->signIdToken([
                'iss' => 'https://accounts.google.com',
                'aud' => self::GOOGLE_CLIENT_ID,
                'sub' => 'google-sub-converted',
                'email' => 'converted@example.com',
                'iat' => time(),
                'exp' => time() + 300,
            ]),
            'deviceLabel' => 'iPad Air',
            'signupSource' => 'feature:post_in_the_community',
        ])->assertOk()->assertJsonPath('user.id', $guest->id);

        $this->assertSame('feature:post_in_the_community', $guest->fresh()->signup_source);
        $this->assertNotNull($guest->fresh()->upgraded_from_guest_at);
    }

    public function test_signup_source_must_be_a_short_slug(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerBody(['signupSource' => 'Not A Slug!']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signupSource']);

        $this->postJson('/api/v1/auth/register', $this->registerBody(['signupSource' => str_repeat('a', 41)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['signupSource']);
    }

    public function test_active_guest_token_slides_forward_when_near_expiry(): void
    {
        [, $token] = $this->guestWithToken(now()->addDays(10));

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $expiresAt = PersonalAccessToken::findToken($token)->expires_at;
        $this->assertTrue($expiresAt->gt(now()->addDays(89)), "Expected ~90 days left, got {$expiresAt}");
    }

    public function test_fresh_guest_token_is_not_rewritten_on_every_request(): void
    {
        $original = now()->addDays(80)->startOfSecond();
        [, $token] = $this->guestWithToken($original);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertTrue(PersonalAccessToken::findToken($token)->expires_at->equalTo($original));
    }

    public function test_registered_users_tokens_keep_their_fixed_expiry(): void
    {
        $original = now()->addDays(10)->startOfSecond();
        $user = User::factory()->create();
        $token = $user->createToken('Phone', expiresAt: $original)->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertTrue(PersonalAccessToken::findToken($token)->expires_at->equalTo($original));
    }

    public function test_contributions_count_only_what_others_can_see(): void
    {
        $user = $this->makeUser(['trusted_contributor' => true]);
        $restaurant = $this->makeRestaurant();

        $this->makeHalalReport($restaurant, $user, HalalStatus::Certified, 'approved');
        $this->makeHalalReport($this->makeRestaurant(['provider_place_id' => 'ChIJ-other']), $user, HalalStatus::Certified, 'pending');

        RestaurantPhoto::create(['restaurant_id' => $restaurant->id, 'disk' => config('restaurant_photos.public_disk'), 'path' => 'a.jpg', 'uploaded_by' => $user->id, 'is_active' => true]);
        RestaurantPhoto::create(['restaurant_id' => $restaurant->id, 'disk' => config('restaurant_photos.pending_disk'), 'path' => 'b.jpg', 'uploaded_by' => $user->id, 'is_active' => false]);

        $ku = University::create(['name' => 'Kolej Universiti', 'short_name' => 'KU', 'country' => 'Malaysia', 'active' => true]);
        CommunityPost::create(['user_id' => $user->id, 'university_id' => $ku->id, 'body' => 'Best nasi lemak in Kajang']);
        CommunityPost::create(['user_id' => $user->id, 'university_id' => $ku->id, 'body' => 'Hidden one', 'status' => 'hidden', 'hidden_reason' => 'reports']);

        $this->actingAs($user)->getJson('/api/v1/me/contributions')
            ->assertOk()
            ->assertExactJson([
                'trustedContributor' => true,
                'placesAdded' => 0,
                'halalVerified' => 1,
                'photosAdded' => 1,
                'posts' => 1,
            ]);
    }

    public function test_guest_contributions_are_all_zero(): void
    {
        [, $token] = $this->guestWithToken();

        $this->withToken($token)->getJson('/api/v1/me/contributions')
            ->assertOk()
            ->assertJsonPath('trustedContributor', false)
            ->assertJsonPath('placesAdded', 0)
            ->assertJsonPath('posts', 0);
    }

    public function test_place_halal_details_show_recent_pickers_and_trusted_reporters(): void
    {
        $restaurant = $this->makeRestaurant();
        $reporter = $this->makeUser(['name' => 'Aina', 'trusted_contributor' => true]);
        $this->makeHalalReport($restaurant, $reporter, HalalStatus::MuslimFriendly, 'approved');

        // Two distinct people accepted it recently (one of them twice), one only long ago.
        $recentA = User::factory()->create();
        $recentB = User::factory()->guest()->create();
        $longAgo = User::factory()->create();
        foreach ([[$recentA, now()->subDays(2)], [$recentA, now()->subDay()], [$recentB, now()->subDays(20)], [$longAgo, now()->subDays(45)]] as [$picker, $acceptedAt]) {
            $decision = Decision::create(['user_id' => $picker->id, 'mode' => 'solo', 'latitude' => 2.9, 'longitude' => 101.7]);
            DecisionRecommendation::create([
                'decision_id' => $decision->id, 'restaurant_id' => $restaurant->id, 'rank' => 1, 'score' => 90,
                'shown_at' => $acceptedAt, 'accepted_at' => $acceptedAt,
            ]);
        }

        $this->actingAs($this->makeUser())
            ->getJson("/api/v1/restaurants/{$restaurant->id}/details")
            ->assertOk()
            ->assertJsonPath('halal.recentPickers', 2)
            ->assertJsonPath('halal.reports.0.userName', 'Aina')
            ->assertJsonPath('halal.reports.0.userTrusted', true);
    }

    public function test_admin_dashboard_shows_guest_conversion_for_the_last_30_days(): void
    {
        User::factory()->guest()->count(2)->create();
        User::factory()->create(['signup_source' => 'nudge_picks', 'upgraded_from_guest_at' => now()]);
        User::factory()->guest()->create(['created_at' => now()->subDays(40)]);

        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');

        Livewire::test(OverviewStatsWidget::class)
            ->assertOk()
            ->assertSee('Guest → account (30d)')
            ->assertSee('33.3%')
            ->assertSee('1 of 3 new guests · top: nudge_picks (1)');
    }
}
