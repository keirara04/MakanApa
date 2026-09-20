<?php

namespace Tests\Feature;

use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\RestaurantMenuItem;
use App\Services\PlacesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceSearchTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.928400;

    private const LNG = 101.780200;

    private function makeRestaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Some Restaurant',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
            'is_active' => true,
            'provider' => 'google',
            'provider_place_id' => 'place-'.uniqid(),
        ], $overrides));
    }

    public function test_search_matches_food_category_not_just_name(): void
    {
        $this->makeRestaurant(['name' => 'Kedai Uncle Lim', 'food_category' => 'nasi lemak']);

        $results = app(PlacesService::class)->searchPlaces('nasi lemak', self::LAT, self::LNG);

        $this->assertCount(1, $results);
        $this->assertSame('Kedai Uncle Lim', $results[0]['name']);
    }

    public function test_search_matches_cuisine(): void
    {
        $restaurant = $this->makeRestaurant(['name' => 'Seoul Kitchen']);
        $cuisine = Cuisine::create(['name' => 'Korean', 'slug' => 'korean']);
        $restaurant->cuisines()->sync([$cuisine->id]);

        $results = app(PlacesService::class)->searchPlaces('korean', self::LAT, self::LNG);

        $this->assertCount(1, $results);
        $this->assertSame('Seoul Kitchen', $results[0]['name']);
    }

    public function test_exact_canonical_name_match_outranks_weaker_community_match(): void
    {
        $this->makeRestaurant(['name' => 'KFC', 'provider' => 'google']);
        $this->makeRestaurant(['name' => 'KFC Korner Cafe', 'provider' => 'user_submitted']);

        $results = app(PlacesService::class)->searchPlaces('KFC', self::LAT, self::LNG);

        $this->assertCount(2, $results);
        $this->assertSame('KFC', $results[0]['name']);
        $this->assertSame('canonical', $results[0]['provenance']);
    }

    public function test_matching_multiple_cuisines_and_menu_items_does_not_duplicate_the_restaurant(): void
    {
        $restaurant = $this->makeRestaurant(['name' => 'Warung Nasi Lemak Wanjo']);
        $cuisineA = Cuisine::create(['name' => 'Nasi Lemak', 'slug' => 'nasi-lemak']);
        $cuisineB = Cuisine::create(['name' => 'Malay', 'slug' => 'malay']);
        $restaurant->cuisines()->sync([$cuisineA->id, $cuisineB->id]);
        RestaurantMenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Nasi Lemak Special', 'sort_order' => 1]);
        RestaurantMenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Nasi Lemak Ayam', 'sort_order' => 2]);

        $results = app(PlacesService::class)->searchPlaces('nasi lemak', self::LAT, self::LNG);

        $this->assertCount(1, $results);
    }

    public function test_search_with_thin_local_results_falls_back_to_google(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Http::fake([
            '*searchText*' => Http::response(['places' => [[
                'id' => 'google-1',
                'displayName' => ['text' => 'Nasi Lemak Antarabangsa'],
                'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
                'types' => ['malaysian_restaurant'],
                'rating' => 4.5,
                'priceLevel' => 'PRICE_LEVEL_INEXPENSIVE',
                'currentOpeningHours' => ['openNow' => true],
            ]]]),
        ]);

        $results = app(PlacesService::class)->searchPlaces('nasi lemak', self::LAT, self::LNG);

        $googleResults = array_filter($results, fn ($r) => $r['provenance'] === 'google_fallback');
        $this->assertNotEmpty($googleResults);
        $this->assertSame('google-1', reset($googleResults)['google_place_id']);
    }

    public function test_search_skips_google_when_local_results_are_already_sufficient(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        for ($i = 0; $i < 8; $i++) {
            $this->makeRestaurant(['name' => "Nasi Lemak Spot {$i}", 'food_category' => 'nasi lemak']);
        }

        Http::fake(['*searchText*' => Http::response(['places' => []])]);

        app(PlacesService::class)->searchPlaces('nasi lemak', self::LAT, self::LNG);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'searchText'));
    }

    public function test_resolve_upserts_and_returns_canonical_restaurant_for_a_google_place_id(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);

        Http::fake([
            'https://places.googleapis.com/v1/places/new-google-place' => Http::response([
                'id' => 'new-google-place',
                'displayName' => ['text' => 'Mamak Corner'],
                'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
                'types' => ['malaysian_restaurant'],
                'rating' => 4.2,
                'priceLevel' => 'PRICE_LEVEL_MODERATE',
                'currentOpeningHours' => ['openNow' => true],
            ]),
        ]);

        $resolved = app(PlacesService::class)->resolveGooglePlace('new-google-place');

        $this->assertSame('canonical', $resolved['provenance']);
        $this->assertSame('Mamak Corner', $resolved['name']);
        $this->assertNotNull($resolved['id']);
        $this->assertDatabaseHas('restaurants', [
            'provider_place_id' => 'new-google-place',
            'name' => 'Mamak Corner',
        ]);
    }
}
