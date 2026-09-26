<?php

namespace Tests\Feature;

use App\Models\PendingProviderLink;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use FakesIdentityTokens, RefreshDatabase;

    private const GOOGLE_CLIENT_ID = 'server-client-id.apps.googleusercontent.com';

    private const SUSPENDED = [
        'message' => 'This account is suspended. Contact help@makanapa.test if you think this is a mistake.',
        'code' => 'account_suspended',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // These tests use real bearer tokens, so the guard must resolve from the request rather
        // than the made-up user the base TestCase pins onto it.
        $this->app['auth']->forgetGuards();
        Config::set('marketing.support_email', 'help@makanapa.test');
    }

    public function test_suspending_signs_the_user_out_everywhere(): void
    {
        $user = User::factory()->create();
        $user->createToken('iPhone');
        $user->createToken('iPad');

        app(AdminUserService::class)->suspend($user, 'harassment', User::factory()->create(['role' => 'superadmin']));

        $this->assertSame('suspended', $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lockedOutStatuses(): array
    {
        return ['suspended' => ['suspended'], 'revoked' => ['revoked']];
    }

    #[DataProvider('lockedOutStatuses')]
    public function test_locked_out_account_is_refused_everywhere_and_its_token_deleted(string $status): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;
        $user->update(['status' => $status]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertExactJson(self::SUSPENDED);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_suspended_message_omits_contact_when_no_support_email_is_configured(): void
    {
        Config::set('marketing.support_email', null);
        $user = User::factory()->create(['status' => 'suspended']);

        $this->withToken($user->createToken('iPhone')->plainTextToken)->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertExactJson(['message' => 'This account is suspended.', 'code' => 'account_suspended']);
    }

    public function test_suspended_user_cannot_sign_in_with_their_password(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('correct-password'),
            'status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'suspended@example.com', 'password' => 'correct-password', 'deviceLabel' => 'iPhone'])
            ->assertForbidden()
            ->assertExactJson(self::SUSPENDED);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_wrong_password_on_a_suspended_account_stays_generic(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('correct-password'),
            'status' => 'suspended',
        ]);

        $this->postJson('/api/v1/auth/login', ['email' => 'suspended@example.com', 'password' => 'wrong-password', 'deviceLabel' => 'iPhone'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Invalid credentials.']);
    }

    public function test_suspended_user_cannot_sign_back_in_with_google(): void
    {
        config(['services.google.server_client_id' => self::GOOGLE_CLIENT_ID]);
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response($this->fakeJwks())]);
        $user = User::factory()->create(['google_sub' => 'google-sub-suspended', 'status' => 'suspended']);

        $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->signIdToken([
                'iss' => 'https://accounts.google.com',
                'aud' => self::GOOGLE_CLIENT_ID,
                'sub' => 'google-sub-suspended',
                'email' => $user->email,
                'iat' => time(),
                'exp' => time() + 300,
            ]),
            'deviceLabel' => 'iPhone',
        ])->assertForbidden()->assertExactJson(self::SUSPENDED);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_suspended_user_cannot_complete_an_account_link(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password'), 'status' => 'suspended']);
        $linkToken = Str::random(64);
        $pending = PendingProviderLink::create([
            'token_hash' => hash('sha256', $linkToken),
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_sub' => 'google-sub-new',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/v1/auth/link', ['linkToken' => $linkToken, 'password' => 'correct-password', 'deviceLabel' => 'iPhone'])
            ->assertForbidden()
            ->assertExactJson(self::SUSPENDED);

        $this->assertNull($user->fresh()->google_sub);
        $this->assertNull($pending->fresh()->consumed_at);
    }

    public function test_suspended_guest_is_not_upgraded_by_signing_up(): void
    {
        $guest = User::factory()->guest()->create(['status' => 'suspended']);
        $guestToken = $guest->createToken('iPhone')->plainTextToken;

        $response = $this->withToken($guestToken)->postJson('/api/v1/auth/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-long-password',
            'deviceLabel' => 'iPhone',
        ])->assertCreated();

        $this->assertNotSame($guest->id, $response->json('user.id'));
        $this->assertTrue($guest->fresh()->isGuest());
        $this->assertSame('suspended', $guest->fresh()->status);
    }
}
