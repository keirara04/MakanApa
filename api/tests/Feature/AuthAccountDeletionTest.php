<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_account_requires_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $wrong = $this->actingAs($user)->deleteJson('/api/v1/auth/me', ['password' => 'wrong']);
        $wrong->assertStatus(401);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $correct = $this->actingAs($user)->deleteJson('/api/v1/auth/me', ['password' => 'correct-password']);
        $correct->assertOk()->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_social_only_account_deletes_without_password(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);

        $response = $this->actingAs($user)->deleteJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deletion_removes_all_sanctum_tokens(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        $user->createToken('device-a');
        $user->createToken('device-b');

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_deletion_cascades_to_affiliation(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        UserAffiliation::create([
            'user_id' => $user->id,
            'type' => 'public',
            'verification_status' => 'self_reported',
            'verification_method' => 'self_reported',
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertDatabaseMissing('user_affiliations', ['user_id' => $user->id]);
    }

    public function test_apple_refresh_token_is_revoked_before_deletion(): void
    {
        config([
            'services.apple.client_id' => 'com.makanapa.app',
            'services.apple.team_id' => 'TEAMID1234',
            'services.apple.key_id' => 'KEYID1234',
        ]);
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $ecPem);
        config(['services.apple.private_key' => $ecPem]);

        Http::fake(['appleid.apple.com/auth/revoke' => Http::response([])]);

        $user = User::factory()->create([
            'password' => null,
            'apple_sub' => 'apple-sub-1',
            'apple_refresh_token' => 'the-refresh-token',
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'appleid.apple.com/auth/revoke')
                && $request['token'] === 'the-refresh-token';
        });
    }

    public function test_a_failed_apple_revocation_does_not_block_deletion(): void
    {
        config([
            'services.apple.client_id' => 'com.makanapa.app',
            'services.apple.team_id' => 'TEAMID1234',
            'services.apple.key_id' => 'KEYID1234',
        ]);
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $ecPem);
        config(['services.apple.private_key' => $ecPem]);

        Http::fake(['appleid.apple.com/auth/revoke' => Http::response(['error' => 'invalid_token'], 400)]);

        $user = User::factory()->create([
            'password' => null,
            'apple_sub' => 'apple-sub-1',
            'apple_refresh_token' => 'the-refresh-token',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('deleted', true);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deletion_leaves_a_trace_in_account_deletions_log(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1', 'email' => 'gone@example.com']);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertDatabaseHas('account_deletions', ['user_id' => $user->id, 'email' => 'gone@example.com']);
    }
}
