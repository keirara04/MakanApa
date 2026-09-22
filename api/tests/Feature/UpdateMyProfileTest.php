<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateMyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPersistedUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 'user', 'status' => 'active'], $attributes));
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_name_and_avatar_key_are_saved(): void
    {
        $user = $this->actingAsPersistedUser();

        $response = $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi', 'avatarKey' => 'nasi'])->assertOk();

        $response->assertJsonPath('user.name', 'Hakeemi');
        $response->assertJsonPath('user.avatarKey', 'nasi');
        $this->assertSame('Hakeemi', $user->refresh()->name);
        $this->assertSame('nasi', $user->refresh()->avatar_key);
    }

    public function test_unknown_avatar_key_is_rejected(): void
    {
        $this->actingAsPersistedUser();

        $this->patchJson('/api/v1/me/profile', ['avatarKey' => 'not_a_real_character'])->assertStatus(422);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        app('auth')->guard('sanctum')->forgetUser();

        $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi'])->assertStatus(401);
    }

    public function test_updating_only_name_leaves_avatar_key_untouched(): void
    {
        $user = $this->actingAsPersistedUser(['avatar_key' => 'roti']);

        $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi'])->assertOk();

        $this->assertSame('roti', $user->refresh()->avatar_key);
    }

    public function test_explicit_null_avatar_key_resets_it(): void
    {
        $user = $this->actingAsPersistedUser(['avatar_key' => 'roti']);

        $response = $this->patchJson('/api/v1/me/profile', ['avatarKey' => null])->assertOk();

        $response->assertJsonPath('user.avatarKey', null);
        $this->assertNull($user->refresh()->avatar_key);
    }

    public function test_empty_payload_succeeds_without_changing_profile(): void
    {
        $user = $this->actingAsPersistedUser(['name' => 'Original', 'avatar_key' => 'milo']);

        $this->patchJson('/api/v1/me/profile', [])->assertOk();

        $user->refresh();
        $this->assertSame('Original', $user->name);
        $this->assertSame('milo', $user->avatar_key);
    }
}
