<?php

namespace Tests\Feature;

use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Resources\RestaurantSubmissions\Pages\ListRestaurantSubmissions;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AdminAuditLog;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fires the actual Filament Actions (not just page renders) to prove the panel wiring calls
 * the same services the JSON API uses, and that every mutation gets an audit log row.
 */
class AdminPanelActionsTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_deactivate_action_on_restaurant_edit_page(): void
    {
        $admin = $this->superadmin();
        $restaurant = Restaurant::create([
            'name' => 'Action Test Cafe', 'latitude' => 2.9, 'longitude' => 101.7,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('deactivate');

        $this->assertFalse($restaurant->fresh()->is_active);
        $this->assertSame(1, AdminAuditLog::where('action', 'restaurant.remove')->count());
    }

    public function test_approve_action_on_submissions_list(): void
    {
        $admin = $this->superadmin();
        $university = University::create(['name' => 'Test University', 'short_name' => 'UTest', 'active' => true]);
        $submission = RestaurantSubmission::create([
            'submission_type' => 'new_place', 'source_type' => 'manual', 'status' => 'pending',
            'name' => 'Brand New Place', 'latitude' => 2.9, 'longitude' => 101.7,
            'location_source' => 'map_pin', 'university_id' => $university->id,
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(ListRestaurantSubmissions::class)
            ->callTableAction('approve', $submission, data: ['releaseToGoogle' => false]);

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->restaurant_id);
        $this->assertSame(1, AdminAuditLog::where('action', 'submission.approve')->count());
    }

    public function test_suspend_action_on_user_edit_page(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($admin, 'web');

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->callAction('suspend', data: ['reason' => 'Spam reports']);

        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertSame(1, AdminAuditLog::where('action', 'user.suspend')->count());
    }

    public function test_suspend_action_blocked_for_last_active_superadmin(): void
    {
        $onlySuperadmin = $this->superadmin();
        $actingAsSomeoneElse = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($actingAsSomeoneElse, 'web');

        Livewire::test(EditUser::class, ['record' => $onlySuperadmin->id])
            ->callAction('suspend', data: ['reason' => 'test']);

        $this->assertSame('active', $onlySuperadmin->fresh()->status, 'guard must block suspending the last active superadmin');
        $this->assertSame(0, AdminAuditLog::where('action', 'user.suspend')->count());
    }
}
