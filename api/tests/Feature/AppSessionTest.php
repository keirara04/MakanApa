<?php

namespace Tests\Feature;

use App\Models\AppSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_a_session_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/app-sessions/start', ['installationId' => 'device-1']);

        $response->assertOk();
        $sessionId = $response->json('sessionId');
        $this->assertDatabaseHas('app_sessions', [
            'id' => $sessionId, 'user_id' => $user->id, 'installation_id' => 'device-1', 'ended_at' => null,
        ]);
    }

    public function test_end_sets_ended_at(): void
    {
        $user = User::factory()->create();
        $session = AppSession::create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->postJson("/api/v1/app-sessions/{$session->id}/end");

        $response->assertOk()->assertJsonPath('ended', true);
        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_end_is_idempotent(): void
    {
        $user = User::factory()->create();
        $session = AppSession::create(['user_id' => $user->id, 'started_at' => now(), 'ended_at' => now()->subMinute()]);
        $originalEndedAt = $session->ended_at;

        $this->actingAs($user)->postJson("/api/v1/app-sessions/{$session->id}/end")->assertOk();

        $this->assertEquals($originalEndedAt, $session->fresh()->ended_at);
    }

    public function test_cannot_end_another_users_session(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $session = AppSession::create(['user_id' => $owner->id, 'started_at' => now()]);

        $this->actingAs($intruder)->postJson("/api/v1/app-sessions/{$session->id}/end")->assertForbidden();
        $this->assertNull($session->fresh()->ended_at);
    }
}
