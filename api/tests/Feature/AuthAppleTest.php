<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

class AuthAppleTest extends TestCase
{
    use FakesIdentityTokens, RefreshDatabase;

    private const CLIENT_ID = 'com.makanapa.app';

    protected function setUp(): void
    {
        parent::setUp();

        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $ecPem);

        config([
            'services.apple.client_id' => self::CLIENT_ID,
            'services.apple.team_id' => 'TEAMID1234',
            'services.apple.key_id' => 'KEYID1234',
            'services.apple.private_key' => $ecPem,
        ]);

        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response($this->fakeJwks()),
            'appleid.apple.com/auth/token' => Http::response(['refresh_token' => 'fake-apple-refresh-token']),
        ]);
    }

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://appleid.apple.com',
            'aud' => self::CLIENT_ID,
            'sub' => 'apple-sub-abc123',
            'email' => 'newuser@example.com',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => hash('sha256', 'raw-nonce-value'),
        ], $overrides);
    }

    private function payload(array $tokenOverrides = [], array $requestOverrides = []): array
    {
        return array_merge([
            'identityToken' => $this->signIdToken($this->claims($tokenOverrides)),
            'authorizationCode' => 'fake-auth-code',
            'nonce' => 'raw-nonce-value',
            'fullName' => 'Hakeem Iridza',
            'deviceLabel' => 'Test device',
        ], $requestOverrides);
    }

    public function test_valid_token_creates_user_and_stores_refresh_token(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', $this->payload());

        $response->assertOk()->assertJsonPath('user.email', 'newuser@example.com');

        $user = User::where('apple_sub', 'apple-sub-abc123')->firstOrFail();
        $this->assertSame('Hakeem Iridza', $user->name);
        $this->assertSame('fake-apple-refresh-token', $user->apple_refresh_token);
    }

    public function test_same_apple_sub_logs_into_same_user_on_second_call(): void
    {
        $first = $this->postJson('/api/v1/auth/apple', $this->payload());
        $first->assertOk();
        $firstUserId = $first->json('user.id');

        // Second authorization never resends fullName — must not matter.
        $second = $this->postJson('/api/v1/auth/apple', $this->payload([], ['fullName' => null]));
        $second->assertOk();

        $this->assertSame($firstUserId, $second->json('user.id'));
        $this->assertSame(1, User::where('apple_sub', 'apple-sub-abc123')->count());
    }

    public function test_wrong_audience_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', $this->payload(['aud' => 'com.someoneelse.app']));

        $response->assertStatus(401);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_wrong_issuer_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', $this->payload(['iss' => 'https://evil.example.com']));

        $response->assertStatus(401);
    }

    public function test_expired_token_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', $this->payload(['exp' => time() - 60]));

        $response->assertStatus(401);
    }

    public function test_bad_signature_rejected(): void
    {
        // Sign with a totally different keypair than the one served at /auth/keys.
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $otherPem);
        $forgedToken = \Firebase\JWT\JWT::encode($this->claims(), $otherPem, 'RS256', $this->fakeKid());

        $response = $this->postJson('/api/v1/auth/apple', $this->payload([], ['identityToken' => $forgedToken]));

        $response->assertStatus(401);
    }

    public function test_nonce_mismatch_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/apple', $this->payload(['nonce' => hash('sha256', 'a-different-nonce')]));

        $response->assertStatus(401);
    }
}
