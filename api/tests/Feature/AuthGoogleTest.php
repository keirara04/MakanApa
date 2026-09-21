<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesIdentityTokens;
use Tests\TestCase;

class AuthGoogleTest extends TestCase
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

    private function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::SERVER_CLIENT_ID,
            'sub' => 'google-sub-abc123',
            'email' => 'newuser@example.com',
            'iat' => time(),
            'exp' => time() + 300,
        ], $overrides);
    }

    private function payload(array $tokenOverrides = [], array $requestOverrides = []): array
    {
        return array_merge([
            'idToken' => $this->signIdToken($this->claims($tokenOverrides)),
            'deviceLabel' => 'Test device',
        ], $requestOverrides);
    }

    public function test_valid_token_creates_user(): void
    {
        $response = $this->postJson('/api/v1/auth/google', $this->payload());

        $response->assertOk()->assertJsonPath('user.email', 'newuser@example.com');
        $this->assertDatabaseHas('users', ['google_sub' => 'google-sub-abc123', 'email' => 'newuser@example.com']);
    }

    public function test_same_google_sub_reuses_same_user(): void
    {
        $first = $this->postJson('/api/v1/auth/google', $this->payload())->json('user.id');
        $second = $this->postJson('/api/v1/auth/google', $this->payload())->json('user.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, User::where('google_sub', 'google-sub-abc123')->count());
    }

    public function test_wrong_audience_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/google', $this->payload(['aud' => 'someone-elses-client-id']));

        $response->assertStatus(401);
    }

    public function test_expired_token_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/google', $this->payload(['exp' => time() - 60]));

        $response->assertStatus(401);
    }

    public function test_bad_signature_rejected(): void
    {
        $otherKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($otherKey, $otherPem);
        $forgedToken = JWT::encode($this->claims(), $otherPem, 'RS256', $this->fakeKid());

        $response = $this->postJson('/api/v1/auth/google', $this->payload([], ['idToken' => $forgedToken]));

        $response->assertStatus(401);
    }
}
