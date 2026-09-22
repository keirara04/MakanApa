<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_resolve_when_column_is_null(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $response = $this->actingAs($user)->getJson('/api/v1/me/notification-preferences');

        $response->assertOk()->assertJson(['preferences' => [
            'community_submissions' => true,
            'account_admin' => true,
            'release_announcements' => false,
        ]]);
    }

    public function test_partial_patch_does_not_clobber_other_keys(): void
    {
        $user = User::factory()->create(['notification_preferences' => null]);

        $this->actingAs($user)->patchJson('/api/v1/me/notification-preferences', [
            'release_announcements' => true,
        ])->assertOk();

        $response = $this->actingAs($user)->getJson('/api/v1/me/notification-preferences');

        $response->assertJson(['preferences' => [
            'community_submissions' => true,
            'account_admin' => true,
            'release_announcements' => true,
        ]]);

        $this->actingAs($user)->patchJson('/api/v1/me/notification-preferences', [
            'community_submissions' => false,
        ])->assertOk();

        $this->actingAs($user)->getJson('/api/v1/me/notification-preferences')
            ->assertJson(['preferences' => [
                'community_submissions' => false,
                'account_admin' => true,
                'release_announcements' => true,
            ]]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        // TestCase::setUp() authenticates every request by default (private beta) — drop that
        // for this one test to exercise the actual unauthenticated path.
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me/notification-preferences')->assertStatus(401);
        $this->patchJson('/api/v1/me/notification-preferences', ['account_admin' => false])->assertStatus(401);
    }
}
