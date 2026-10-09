<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_search_ignores_case(): void
    {
        Restaurant::create(['name' => 'Mamak Bistro', 'latitude' => 3.1, 'longitude' => 101.5, 'is_active' => true, 'provider' => 'fixture']);

        $this->getJson('/api/v1/community/places/search?query=mamak')
            ->assertOk()
            ->assertJsonPath('existing.0.name', 'Mamak Bistro');
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Restaurant::create(['name' => 'Mamak Bistro', 'latitude' => 3.1, 'longitude' => 101.5, 'is_active' => true, 'provider' => 'fixture']);

        $this->getJson('/api/v1/community/places/search?query=%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'existing');
    }

    public function test_google_results_carry_address_and_distance(): void
    {
        config(['services.places.google_api_key' => 'fake-key']);
        Http::fake([
            '*searchText*' => Http::response(['places' => [[
                'id' => 'g-kfc-bangi',
                'displayName' => ['text' => 'KFC Bandar Baru Bangi'],
                'location' => ['latitude' => 2.9600, 'longitude' => 101.7700],
                'types' => ['restaurant'],
                'shortFormattedAddress' => 'Seksyen 9, Bandar Baru Bangi',
            ]]]),
        ]);

        $response = $this->getJson('/api/v1/community/places/search?query=kfc&latitude=2.9500&longitude=101.7700')
            ->assertOk()
            ->assertJsonPath('google.0.name', 'KFC Bandar Baru Bangi')
            ->assertJsonPath('google.0.address', 'Seksyen 9, Bandar Baru Bangi');

        $this->assertEqualsWithDelta(1.11, $response->json('google.0.distanceKm'), 0.05);
    }
}
