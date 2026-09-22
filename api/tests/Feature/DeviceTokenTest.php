<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_an_anonymous_row(): void
    {
        $response = $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'ABCDEF0123',
            'environment' => 'sandbox',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            // Registration lowercases the token before persisting.
            'token' => 'abcdef0123',
            'user_id' => null,
        ]);
    }

    public function test_claim_after_auth_sets_user_id(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            'user_id' => $user->id,
        ]);
    }

    public function test_reregistering_after_claim_does_not_null_out_user_id(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);
        $this->actingAs($user)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        // APNs re-registers on a later app launch — must not detach the existing claim.
        $response = $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            'user_id' => $user->id,
        ]);
    }

    public function test_token_rotation_updates_in_place_rather_than_duplicating(): void
    {
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response = $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'fedcba9876',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertSame(1, DeviceToken::where('installation_id', 'install-1')->count());
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            'token' => 'fedcba9876',
        ]);
    }

    public function test_unclaim_on_logout_sets_user_id_null_without_deleting(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);
        $this->actingAs($user)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response = $this->actingAs($user)->deleteJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            'user_id' => null,
        ]);
    }

    public function test_claiming_the_same_installation_as_a_different_user_transfers_ownership(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'shared-device',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);
        $this->actingAs($firstUser)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'shared-device',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response = $this->actingAs($secondUser)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'shared-device',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'shared-device',
            'user_id' => $secondUser->id,
        ]);
    }

    public function test_reregistration_with_fresh_token_clears_invalidated_at(): void
    {
        $device = DeviceToken::create([
            'installation_id' => 'install-1',
            'token' => 'abcdef0123',
            'platform' => 'ios',
            'environment' => 'sandbox',
            'invalidated_at' => now(),
        ]);

        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'fedcba9876',
            'environment' => 'sandbox',
        ]);

        $this->assertNull($device->fresh()->invalidated_at);
    }

    public function test_unclaim_by_one_user_cannot_detach_an_installation_owned_by_another(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->postJson('/api/v1/device-tokens', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);
        $this->actingAs($owner)->postJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'token' => 'abcdef0123',
            'environment' => 'sandbox',
        ]);

        $response = $this->actingAs($intruder)->deleteJson('/api/v1/me/device-tokens/claim', [
            'installationId' => 'install-1',
            'environment' => 'sandbox',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('device_tokens', [
            'installation_id' => 'install-1',
            'user_id' => $owner->id,
        ]);
    }
}
