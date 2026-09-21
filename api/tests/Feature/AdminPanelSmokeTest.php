<?php

namespace Tests\Feature;

use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Filament\Resources\CommunityRequests\Pages\ListCommunityRequests;
use App\Filament\Resources\Cuisines\Pages\ManageCuisines;
use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Resources\Restaurants\Pages\ListRestaurants;
use App\Filament\Resources\RestaurantSubmissions\Pages\ListRestaurantSubmissions;
use App\Filament\Resources\RestaurantSubmissions\Pages\ViewRestaurantSubmission;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Filament\Resources\Universities\Pages\ManageUniversities;
use App\Filament\Resources\UserAffiliations\Pages\ListUserAffiliations;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Boots the actual Filament list/edit/view pages end-to-end (not just route registration) —
 * catches DI/class-not-found/render errors that route:list alone can't see.
 */
class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
    }

    public function test_non_superadmin_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
    }

    public function test_suspended_superadmin_cannot_access_the_panel(): void
    {
        $user = User::factory()->create(['role' => 'superadmin', 'status' => 'suspended']);
        $this->actingAs($user, 'web')->get('/admin')->assertForbidden();
    }

    public function test_dashboard_loads_for_superadmin(): void
    {
        $this->actingAs($this->superadmin(), 'web')->get('/admin')->assertOk();
    }

    public function test_restaurants_list_and_edit_pages_render(): void
    {
        $admin = $this->superadmin();
        $restaurant = Restaurant::create([
            'name' => 'Smoke Test Cafe', 'latitude' => 2.9, 'longitude' => 101.7,
            'is_active' => true, 'provider' => 'user_submitted',
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(ListRestaurants::class)->assertOk();
        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])->assertOk();
    }

    public function test_restaurant_submissions_list_and_view_pages_render(): void
    {
        $admin = $this->superadmin();
        $university = University::create(['name' => 'Test University', 'short_name' => 'UTest', 'active' => true]);
        $submission = RestaurantSubmission::create([
            'submission_type' => 'new_place', 'source_type' => 'manual', 'status' => 'pending',
            'name' => 'New Place', 'latitude' => 2.9, 'longitude' => 101.7,
            'location_source' => 'map_pin', 'university_id' => $university->id,
        ]);

        $this->actingAs($admin, 'web');

        Livewire::test(ListRestaurantSubmissions::class)->assertOk();
        Livewire::test(ViewRestaurantSubmission::class, ['record' => $submission->id])->assertOk();
    }

    public function test_user_list_and_edit_pages_render(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->actingAs($admin, 'web');

        Livewire::test(ListUsers::class)->assertOk();
        Livewire::test(EditUser::class, ['record' => $target->id])->assertOk();
    }

    public function test_remaining_resource_lists_render(): void
    {
        $this->actingAs($this->superadmin(), 'web');

        Livewire::test(ManageAreas::class)->assertOk();
        Livewire::test(ManageUniversities::class)->assertOk();
        Livewire::test(ManageCuisines::class)->assertOk();
        Livewire::test(ManageTags::class)->assertOk();
        Livewire::test(ListCommunityRequests::class)->assertOk();
        Livewire::test(ListUserAffiliations::class)->assertOk();
        Livewire::test(ListAdminAuditLogs::class)->assertOk();
    }
}
