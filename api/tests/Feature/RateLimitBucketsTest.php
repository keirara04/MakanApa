<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitBucketsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsPersistedUser(): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    public function test_traffic_on_one_throttled_route_does_not_spend_another_routes_budget(): void
    {
        $this->actingAsPersistedUser();

        // me/profile allows 10/min. Before per-bucket prefixes, these reads shared its counter.
        for ($i = 0; $i < 12; $i++) {
            $this->getJson('/api/v1/me/blocks')->assertOk();
        }

        $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi'])->assertOk();
    }

    public function test_a_bucket_still_enforces_its_own_limit(): void
    {
        $this->actingAsPersistedUser();

        for ($i = 0; $i < 10; $i++) {
            $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi'])->assertOk();
        }

        $this->patchJson('/api/v1/me/profile', ['name' => 'Hakeemi'])->assertTooManyRequests();
    }
}
