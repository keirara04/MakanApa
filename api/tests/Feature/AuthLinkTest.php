<?php

namespace Tests\Feature;

use App\Models\PendingProviderLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

class AuthLinkTest extends TestCase
{
    use FakesIdentityTokens, RefreshDatabase;

    private const SERVER_CLIENT_ID = 'server-client-id.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.server_client_id' => self::SERVER_CLIENT_ID]);

        Http::fake([
            'www.googleapis.com/oauth2/v3/certs' => Http::response($this->fakeJwks()),
        ]);
    }

    private function googleIdToken(string $sub, string $email): string
    {
        return $this->signIdToken([
            'iss' => 'https://accounts.google.com',
            'aud' => self::SERVER_CLIENT_ID,
            'sub' => $sub,
            'email' => $email,
            'iat' => time(),
            'exp' => time() + 300,
        ]);
    }

    public function test_existing_email_does_not_auto_create_or_merge(): void
    {
        $existing = User::factory()->create([
            'email' => 'hakeem@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', 'hakeem@example.com'),
            'deviceLabel' => 'Test device',
        ]);

        $response->assertOk()
            ->assertJsonPath('needsLinking', true)
            ->assertJsonPath('email', 'hakeem@example.com')
            ->assertJsonStructure(['linkToken']);

        $this->assertSame(1, User::count());
        $this->assertNull($existing->fresh()->google_sub);
        $this->assertDatabaseHas('pending_provider_links', [
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_sub' => 'google-sub-1',
        ]);
    }

    public function test_correct_password_and_valid_token_links_and_issues_session(): void
    {
        $existing = User::factory()->create([
            'email' => 'hakeem@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $linkToken = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', 'hakeem@example.com'),
            'deviceLabel' => 'Test device',
        ])->json('linkToken');

        $response = $this->postJson('/api/v1/auth/link', [
            'password' => 'correct-password',
            'linkToken' => $linkToken,
            'deviceLabel' => 'Test device',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $existing->id)->assertJsonStructure(['token']);
        $this->assertSame('google-sub-1', $existing->fresh()->google_sub);

        // Linked account can now log in via Google directly.
        $second = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', 'hakeem@example.com'),
            'deviceLabel' => 'Test device',
        ]);
        $second->assertOk()->assertJsonPath('user.id', $existing->id);
    }

    public function test_wrong_password_rejected_without_consuming_token(): void
    {
        $existing = User::factory()->create([
            'email' => 'hakeem@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $linkToken = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', 'hakeem@example.com'),
            'deviceLabel' => 'Test device',
        ])->json('linkToken');

        $wrong = $this->postJson('/api/v1/auth/link', [
            'password' => 'totally-wrong',
            'linkToken' => $linkToken,
            'deviceLabel' => 'Test device',
        ]);
        $wrong->assertStatus(401);
        $this->assertNull($existing->fresh()->google_sub);

        // Token still usable after a wrong-password attempt.
        $retry = $this->postJson('/api/v1/auth/link', [
            'password' => 'correct-password',
            'linkToken' => $linkToken,
            'deviceLabel' => 'Test device',
        ]);
        $retry->assertOk();
        $this->assertSame('google-sub-1', $existing->fresh()->google_sub);
    }

    public function test_expired_link_token_rejected(): void
    {
        $existing = User::factory()->create(['password' => Hash::make('correct-password')]);
        $rawToken = 'raw-test-token';

        PendingProviderLink::create([
            'token_hash' => hash('sha256', $rawToken),
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_sub' => 'google-sub-1',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/link', [
            'password' => 'correct-password',
            'linkToken' => $rawToken,
            'deviceLabel' => 'Test device',
        ]);

        $response->assertStatus(401);
        $this->assertNull($existing->fresh()->google_sub);
    }

    public function test_already_consumed_link_token_rejected(): void
    {
        $existing = User::factory()->create(['password' => Hash::make('correct-password')]);
        $rawToken = 'raw-test-token';

        PendingProviderLink::create([
            'token_hash' => hash('sha256', $rawToken),
            'user_id' => $existing->id,
            'provider' => 'google',
            'provider_sub' => 'google-sub-1',
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/link', [
            'password' => 'correct-password',
            'linkToken' => $rawToken,
            'deviceLabel' => 'Test device',
        ]);

        $response->assertStatus(401);
    }

    public function test_stored_token_hash_never_round_trips_a_usable_raw_token(): void
    {
        $existing = User::factory()->create(['password' => Hash::make('correct-password')]);

        $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', $existing->email),
            'deviceLabel' => 'Test device',
        ]);

        $stored = PendingProviderLink::first();
        $this->assertNotNull($stored);

        // The raw value is 64 random chars from Str::random(); a sha256 hex digest is 64 chars
        // too but from a wholly different character set/derivation — this is a smoke check that
        // what's persisted is a digest, not the plaintext linkToken.
        $response = $this->postJson('/api/v1/auth/link', [
            'password' => 'correct-password',
            'linkToken' => $stored->token_hash,
            'deviceLabel' => 'Test device',
        ]);
        $response->assertStatus(401);
    }

    public function test_apple_private_relay_email_does_not_bypass_linking_flow(): void
    {
        $existing = User::factory()->create([
            'email' => 'abc123@privaterelay.appleid.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-1', 'abc123@privaterelay.appleid.com'),
            'deviceLabel' => 'Test device',
        ]);

        // Same treatment as any other email match — offered a link, never auto-merged.
        $response->assertOk()->assertJsonPath('needsLinking', true);
        $this->assertNull($existing->fresh()->google_sub);
    }

    public function test_two_simultaneous_first_time_logins_with_same_sub_yield_exactly_one_user_row(): void
    {
        // RefreshDatabase wraps this whole test in one outer transaction/savepoint on the
        // shared 'pgsql' connection — inserting the "racer" row through that same connection
        // would get rolled back along with our own failed insert's savepoint, silently erasing
        // the very row this test depends on. A raw, separate PDO connection with its own
        // autocommit is what actually reproduces a second overlapping *request* (its own
        // connection, its own transaction) winning the race and committing for real before our
        // request's insert attempt runs.
        $dbConfig = config('database.connections.pgsql');
        $pdo = new \PDO(
            "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']}",
            $dbConfig['username'],
            $dbConfig['password'],
        );

        $fired = false;
        User::creating(function (User $model) use (&$fired, $pdo) {
            if ($fired) {
                return;
            }
            $fired = true;

            $stmt = $pdo->prepare(
                'insert into users (name, email, password, role, status, google_sub, created_at, updated_at)
                 values (:name, :email, null, :role, :status, :google_sub, now(), now())'
            );
            $stmt->execute([
                'name' => 'Racer',
                'email' => 'racer-'.Str::random(6).'@example.com',
                'role' => 'user',
                'status' => 'active',
                'google_sub' => $model->google_sub,
            ]);
        });

        $response = $this->postJson('/api/v1/auth/google', [
            'idToken' => $this->googleIdToken('google-sub-race', 'racing@example.com'),
            'deviceLabel' => 'Test device',
        ]);

        $response->assertOk();
        $this->assertSame(1, User::where('google_sub', 'google-sub-race')->count());
        $this->assertSame('Racer', User::where('google_sub', 'google-sub-race')->first()->name);
    }
}
