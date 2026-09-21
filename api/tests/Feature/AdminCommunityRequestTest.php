<?php

namespace Tests\Feature;

use App\Models\CommunityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCommunityRequestTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_admin_can_list_pending_requests(): void
    {
        $this->actingAsSuperadmin();
        $requester = User::factory()->create(['role' => 'user', 'status' => 'active']);
        CommunityRequest::create(['user_id' => $requester->id, 'type' => 'area', 'name' => 'Shah Alam', 'status' => 'pending']);
        CommunityRequest::create(['user_id' => $requester->id, 'type' => 'university', 'name' => 'UiTM', 'status' => 'resolved']);

        $response = $this->getJson('/api/v1/admin/community/requests')->assertOk();

        $response->assertJsonCount(1, 'requests');
        $response->assertJsonPath('requests.0.name', 'Shah Alam');
        $response->assertJsonPath('requests.0.requesterEmail', $requester->email);
    }

    public function test_admin_can_resolve_a_request(): void
    {
        $this->actingAsSuperadmin();
        $requester = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $request = CommunityRequest::create(['user_id' => $requester->id, 'type' => 'area', 'name' => 'Shah Alam', 'status' => 'pending']);

        $this->postJson("/api/v1/admin/community/requests/{$request->id}/resolve")
            ->assertOk()->assertJson(['resolved' => true]);

        $this->assertSame('resolved', $request->fresh()->status);
    }

    public function test_admin_can_dismiss_a_request(): void
    {
        $this->actingAsSuperadmin();
        $requester = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $request = CommunityRequest::create(['user_id' => $requester->id, 'type' => 'area', 'name' => 'Nowhere', 'status' => 'pending']);

        $this->postJson("/api/v1/admin/community/requests/{$request->id}/dismiss")
            ->assertOk()->assertJson(['dismissed' => true]);

        $this->assertSame('dismissed', $request->fresh()->status);
    }

    public function test_non_admin_cannot_list_requests(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/admin/community/requests')->assertForbidden();
    }

    public function test_admin_can_create_a_university(): void
    {
        $this->actingAsSuperadmin();

        $this->postJson('/api/v1/admin/universities', ['name' => 'Universiti Malaya', 'shortName' => 'UM'])
            ->assertOk()->assertJson(['university' => ['shortName' => 'UM', 'name' => 'Universiti Malaya']]);

        $this->assertDatabaseHas('universities', ['short_name' => 'UM', 'active' => true]);
    }

    public function test_admin_cannot_create_a_university_with_a_duplicate_short_name(): void
    {
        $this->actingAsSuperadmin();
        $this->postJson('/api/v1/admin/universities', ['name' => 'Universiti Malaya', 'shortName' => 'UM'])->assertOk();

        $this->postJson('/api/v1/admin/universities', ['name' => 'Another Name', 'shortName' => 'UM'])->assertStatus(422);
    }

    public function test_admin_can_create_an_area(): void
    {
        $this->actingAsSuperadmin();

        $this->postJson('/api/v1/admin/areas', ['name' => 'Shah Alam', 'shortName' => 'SA'])
            ->assertOk()->assertJson(['area' => ['shortName' => 'SA', 'name' => 'Shah Alam']]);

        $this->assertDatabaseHas('areas', ['short_name' => 'SA', 'active' => true]);
    }

    public function test_newly_created_area_immediately_selectable_via_public_list(): void
    {
        $this->actingAsSuperadmin();
        $this->postJson('/api/v1/admin/areas', ['name' => 'Shah Alam', 'shortName' => 'SA'])->assertOk();

        $response = $this->getJson('/api/v1/areas')->assertOk();
        $response->assertJsonFragment(['shortName' => 'SA', 'name' => 'Shah Alam']);
    }
}
