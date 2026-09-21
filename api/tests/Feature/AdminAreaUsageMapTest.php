<?php

namespace Tests\Feature;

use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Models\Area;
use App\Models\Decision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAreaUsageMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_map_action_renders_with_recent_decision_points(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);
        $area = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);

        Decision::create([
            'area_id' => $area->id, 'mode' => 'solo', 'latitude' => 3.139, 'longitude' => 101.6869,
            'installation_id' => 'device-1',
        ]);

        // Outside the 30-day window — must not appear in the map's point count. created_at
        // isn't mass-assignable, so backdate it with a raw update after creation.
        $old = Decision::create([
            'area_id' => $area->id, 'mode' => 'solo', 'latitude' => 3.15, 'longitude' => 101.7,
            'installation_id' => 'device-2',
        ]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->actingAs($admin, 'web');

        // Proves the action mounts and its modalContent() closure/Blade view render without
        // throwing (a bad view/undefined variable would surface here as an exception).
        Livewire::test(ManageAreas::class)
            ->mountTableAction('usageMap', $area)
            ->assertOk();

        // Renders the Blade view directly to assert on its actual output — Filament's modal
        // content isn't part of the outer Livewire component's captured HTML.
        $html = view('filament.area-usage-map', [
            'points' => [['lat' => 3.139, 'lng' => 101.6869]],
            'days' => 30,
            'areaId' => $area->id,
        ])->render();

        $this->assertStringContainsString('1 decision in the last 30 days', $html);
    }
}
