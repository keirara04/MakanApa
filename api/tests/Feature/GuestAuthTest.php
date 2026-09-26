<?php

namespace Tests\Feature;

use App\Models\AccountDeletion;
use App\Models\Restaurant;
use App\Models\RestaurantSave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

class GuestAuthTest extends TestCase
{
    use FakesIdentityTokens, RefreshDatabase;

    private const GOOGLE_CLIENT_ID = 'server-client-id.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        // The base TestCase pins a made-up user onto the sanctum guard; these tests exercise
        // real bearer tokens (or none), so the guard must resolve from the request instead.
        $this->app['auth']->forgetGuards();
    }

    private function makeRestaurant(): Restaurant
    {
        return Restaurant::create([
            'name' => 'KFC Jalan Reko', 'latitude' => 2.9284, 'longitude' => 101.7802, 'is_active' => true,
            'provider' => 'google', 'provider_place_id' => 'ChIJ-kfc', 'address' => 'Jalan Reko, Kajang',
            'food_category' => 'fast_food', 'price_level' => 2, 'rating' => 3.9, 'user_rating_count' => 97531,
        ]);
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function guestWithToken(): array
    {
        $guest = User::factory()->guest()->create();

        return [$guest, $guest->createToken('Guest device')->plainTextToken];
    }

    public function test_guest_endpoint_creates_anonymous_account_with_token(): void
    {
        $response = $this->postJson('/api/v1/auth/guest', ['deviceLabel' => 'iPad Air']);

        $response->assertCreated()
            ->assertJsonPath('user.isGuest', true)
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('user.name', null)
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure(['token', 'user' => ['id']]);

        $guest = User::findOrFail($response->json('user.id'));
        $this->assertTrue($guest->isGuest());
        $this->assertNull($guest->password);
        $this->assertSame(['iPad Air'], $guest->tokens()->pluck('name')->all());
    }

    public function test_guest_endpoint_requires_device_label(): void
    {
        $this->postJson('/api/v1/auth/guest', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deviceLabel' => 'The device label field is required.']);

        $this->assertDatabaseMissing('users', ['is_guest' => true]);
    }

    public function test_guest_token_authenticates_requests(): void
    {
        $token = $this->postJson('/api/v1/auth/guest', ['deviceLabel' => 'iPad Air'])->json('token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.isGuest', true);
    }

    public function test_registered_user_is_not_a_guest(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.isGuest', false);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function accountBasedRoutes(): array
    {
        return [
            'publish community post' => ['post', '/api/v1/community/posts'],
            'request a community' => ['post', '/api/v1/community/requests'],
            'add a place' => ['post', '/api/v1/community/submissions'],
            'list my submissions' => ['get', '/api/v1/community/submissions/mine'],
            'quick-add photo' => ['post', '/api/v1/restaurants/{restaurant}/photos/quick-add'],
            'halal report' => ['post', '/api/v1/restaurants/{restaurant}/halal-reports'],
            'owner claim' => ['post', '/api/v1/restaurants/{restaurant}/owner-claim'],
        ];
    }

    #[DataProvider('accountBasedRoutes')]
    public function test_guest_is_refused_account_based_routes(string $method, string $uri): void
    {
        $restaurant = $this->makeRestaurant();
        Sanctum::actingAs(User::factory()->guest()->create(), ['*']);

        $this->json($method, str_replace('{restaurant}', (string) $restaurant->id, $uri))
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Create an account or sign in to use this.',
                'code' => 'account_required',
            ]);
    }

    public function test_guest_can_save_a_restaurant(): void
    {
        $restaurant = $this->makeRestaurant();
        $guest = User::factory()->guest()->create();
        Sanctum::actingAs($guest, ['*']);

        $this->postJson("/api/v1/restaurants/{$restaurant->id}/save", ['installationId' => 'install-1'])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('restaurant_saves', ['restaurant_id' => $restaurant->id, 'user_id' => $guest->id]);
    }

    public function test_guest_can_update_profile_preferences(): void
    {
        $guest = User::factory()->guest()->create();
        Sanctum::actingAs($guest, ['*']);

        $this->patchJson('/api/v1/me/profile', ['halalPreference' => true])
            ->assertOk()
            ->assertJsonPath('user.halalPreference', true);

        $this->assertTrue($guest->fresh()->halal_preference);
    }

    public function test_register_with_guest_token_upgrades_the_same_account(): void
    {
        [$guest, $guestToken] = $this->guestWithToken();
        $restaurant = $this->makeRestaurant();
        RestaurantSave::create(['restaurant_id' => $restaurant->id, 'installation_id' => 'install-1', 'user_id' => $guest->id]);
        $usersBefore = User::count();

        $response = $this->withToken($guestToken)->postJson('/api/v1/auth/register', [
            'name' => 'Hakeem',
            'email' => 'hakeem@example.com',
            'password' => 'correct-horse-battery',
            'deviceLabel' => 'iPad Air',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.id', $guest->id)
            ->assertJsonPath('user.isGuest', false)
            ->assertJsonPath('user.email', 'hakeem@example.com');

        $this->assertSame($usersBefore, User::count());
        $this->assertDatabaseHas('restaurant_saves', ['user_id' => $guest->id]);
        $this->assertNull(PersonalAccessToken::findToken($guestToken));
        $this->assertNotNull(PersonalAccessToken::findToken($response->json('token')));
    }

    public function test_register_ignores_an_invalid_bearer_token(): void
    {
        $response = $this->withToken('not-a-real-token')->postJson('/api/v1/auth/register', [
            'name' => 'Hakeem',
            'email' => 'hakeem@example.com',
            'password' => 'correct-horse-battery',
            'deviceLabel' => 'iPad Air',
        ]);

        $response->assertCreated()->assertJsonPath('user.isGuest', false);
        $this->assertDatabaseHas('users', ['email' => 'hakeem@example.com', 'is_guest' => false]);
    }

    public function test_register_with_a_registered_users_token_creates_a_separate_account(): void
    {
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $existingToken = $existing->createToken('Old device')->plainTextToken;

        $response = $this->withToken($existingToken)->postJson('/api/v1/auth/register', [
            'name' => 'Hakeem',
            'email' => 'hakeem@example.com',
            'password' => 'correct-horse-battery',
            'deviceLabel' => 'iPad Air',
        ]);

        $response->assertCreated();
        $this->assertNotSame($existing->id, $response->json('user.id'));
        $this->assertSame('existing@example.com', $existing->fresh()->email);
        $this->assertNotNull(PersonalAccessToken::findToken($existingToken));
    }

    public function test_google_sign_in_with_guest_token_upgrades_the_same_account(): void
    {
        config(['services.google.server_client_id' => self::GOOGLE_CLIENT_ID]);
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->fakeJwks())]);
        [$guest, $guestToken] = $this->guestWithToken();
        $usersBefore = User::count();

        $response = $this->withToken($guestToken)->postJson('/api/v1/auth/google', [
            'idToken' => $this->signIdToken([
                'iss' => 'https://accounts.google.com',
                'aud' => self::GOOGLE_CLIENT_ID,
                'sub' => 'google-sub-guest',
                'email' => 'guest-upgraded@example.com',
                'iat' => time(),
                'exp' => time() + 300,
            ]),
            'deviceLabel' => 'iPad Air',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $guest->id)
            ->assertJsonPath('user.isGuest', false)
            ->assertJsonPath('user.email', 'guest-upgraded@example.com');

        $this->assertSame($usersBefore, User::count());
        $this->assertSame('google-sub-guest', $guest->fresh()->google_sub);
        $this->assertNull(PersonalAccessToken::findToken($guestToken));
    }

    public function test_guest_can_delete_account_without_leaving_an_audit_row(): void
    {
        $guest = User::factory()->guest()->create();
        Sanctum::actingAs($guest, ['*']);

        $this->deleteJson('/api/v1/auth/me')->assertOk()->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
        $this->assertSame(0, AccountDeletion::count());
    }

    public function test_admin_user_list_excludes_guests(): void
    {
        $guest = User::factory()->guest()->create();
        $member = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => 'superadmin']), ['*']);

        $ids = collect($this->getJson('/api/v1/admin/users')->assertOk()->json('users'))->pluck('id');

        $this->assertContains($member->id, $ids);
        $this->assertNotContains($guest->id, $ids);
    }
}
