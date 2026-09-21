<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hakeem',
            'email' => 'hakeem@example.com',
            'password' => 'correct-horse-battery',
            'deviceLabel' => 'Test device',
        ], $overrides);
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('user.email', 'hakeem@example.com')
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role', 'status']]);

        $this->assertDatabaseHas('users', ['email' => 'hakeem@example.com', 'role' => 'user', 'status' => 'active']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'hakeem@example.com']);

        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertStatus(422);
        $this->assertSame(1, User::where('email', 'hakeem@example.com')->count());
    }

    public function test_password_login_still_works_after_register_endpoint_added(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@example.com',
            'password' => 'secret123',
            'deviceLabel' => 'Test device',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_admin_created_beta_account_still_logs_in(): void
    {
        // Mirrors Admin\UserController::store()'s shape: role/status set explicitly, password
        // generated server-side — nothing about opening self-serve signup should touch this path.
        $user = User::factory()->create([
            'email' => 'beta@example.com',
            'password' => Hash::make('temp-password'),
            'role' => 'user',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'beta@example.com',
            'password' => 'temp-password',
            'deviceLabel' => 'Test device',
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_social_only_account_cannot_password_login(): void
    {
        User::factory()->create([
            'email' => 'social@example.com',
            'password' => null,
            'google_sub' => 'google-sub-123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'social@example.com',
            'password' => 'anything',
            'deviceLabel' => 'Test device',
        ]);

        $response->assertStatus(401);
    }
}
