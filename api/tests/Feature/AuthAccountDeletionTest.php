<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\RestaurantPhoto;
use App\Models\User;
use App\Models\UserAffiliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_deletion_removes_uploaded_photo_from_pending_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pending/photo.jpg', 'fake-bytes');

        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        $photo = RestaurantPhoto::create([
            'disk' => 'local',
            'path' => 'pending/photo.jpg',
            'photo_type' => 'other',
            'uploaded_by' => $user->id,
            'is_active' => false,
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertModelMissing($photo);
        Storage::disk('local')->assertMissing('pending/photo.jpg');
    }

    public function test_deletion_removes_uploaded_photo_from_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('restaurants/photo.jpg', 'fake-bytes');

        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        $photo = RestaurantPhoto::create([
            'disk' => 'public',
            'path' => 'restaurants/photo.jpg',
            'photo_type' => 'other',
            'uploaded_by' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing('restaurants/photo.jpg');
    }

    public function test_deletion_does_not_touch_another_users_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('restaurants/a.jpg', 'a');
        Storage::disk('public')->put('restaurants/b.jpg', 'b');

        $userA = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-a']);
        $userB = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-b']);

        RestaurantPhoto::create([
            'disk' => 'public', 'path' => 'restaurants/a.jpg', 'photo_type' => 'other',
            'uploaded_by' => $userA->id, 'is_active' => true,
        ]);
        $photoB = RestaurantPhoto::create([
            'disk' => 'public', 'path' => 'restaurants/b.jpg', 'photo_type' => 'other',
            'uploaded_by' => $userB->id, 'is_active' => true,
        ]);

        $this->actingAs($userA)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertModelExists($photoB);
        Storage::disk('public')->assertExists('restaurants/b.jpg');
    }

    public function test_deletion_fails_loudly_if_photo_storage_delete_fails(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        RestaurantPhoto::create([
            'disk' => 'local',
            'path' => 'pending/photo.jpg',
            'photo_type' => 'other',
            'uploaded_by' => $user->id,
            'is_active' => false,
        ]);

        // Flysystem's local adapter returns true from delete() even for a nonexistent file, so
        // a real disk can't simulate this — mock the disk to force delete() to return false,
        // the actual failure signal the service checks for.
        $failingDisk = \Mockery::mock();
        $failingDisk->shouldReceive('delete')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->once()->andReturn($failingDisk);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertStatus(500);

        // Deletion aborted, not silently reported as successful with an orphaned file.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deletion_still_anonymizes_rather_than_deletes_decisions(): void
    {
        $user = User::factory()->create(['password' => null, 'google_sub' => 'google-sub-1']);
        $decision = Decision::create([
            'user_id' => $user->id,
            'mode' => 'solo',
            'latitude' => 2.9,
            'longitude' => 101.7,
        ]);

        $this->actingAs($user)->deleteJson('/api/v1/auth/me')->assertOk();

        $this->assertDatabaseHas('decisions', ['id' => $decision->id, 'user_id' => null]);
    }
}
