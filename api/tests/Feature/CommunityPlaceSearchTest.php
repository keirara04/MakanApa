<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPlaceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_results_carry_coordinates_so_an_edit_can_be_submitted(): void
    {
        Restaurant::create(['name' => 'Warung Pak Ali', 'latitude' => 3.1234, 'longitude' => 101.5678, 'is_active' => true, 'provider' => 'fixture']);

        $this->getJson('/api/v1/community/places/search?query=Pak+Ali')
            ->assertOk()
            ->assertJsonPath('existing.0.name', 'Warung Pak Ali')
            ->assertJsonPath('existing.0.latitude', 3.1234)
            ->assertJsonPath('existing.0.longitude', 101.5678);
    }
}
