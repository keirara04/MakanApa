<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\SearchMiss;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlaceSearchExperienceTest extends TestCase
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

    /** A point this many km due east of the search center. */
    private function eastBy(float $km): float
    {
        return self::LNG + $km / (111.320 * cos(deg2rad(self::LAT)));
    }

    private function search(array $params = []): TestResponse
    {
        return $this->getJson('/api/v1/places/search?'.http_build_query(array_merge([
            'query' => 'kfc', 'latitude' => self::LAT, 'longitude' => self::LNG,
        ], $params)));
    }

    private function useGoogle(): void
    {
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    public function test_result_carries_canonical_summary_fields_and_legacy_aliases(): void
    {
        $restaurant = $this->makeRestaurant([
            'name' => 'KFC', 'address' => 'Jalan Reko, Kajang', 'food_category' => 'fast_food',
            'opening_hours' => ['open_now' => false],
        ]);

        $response = $this->search()->assertOk();

        $response->assertJsonPath('results.0.id', $restaurant->id)
            ->assertJsonPath('results.0.address', 'Jalan Reko, Kajang')
            ->assertJsonPath('results.0.category', 'Fast Food')
            ->assertJsonPath('results.0.openStatus', 'closed')
            ->assertJsonPath('results.0.halal.status', HalalStatus::Unknown->value)
            ->assertJsonPath('results.0.restaurantId', $restaurant->id)
            ->assertJsonPath('results.0.foodCategory', 'fast_food')
            ->assertJsonPath('meta.radiusKm', 6)
            ->assertJsonPath('meta.source', 'local')
            ->assertJsonPath('meta.widerRadiusKm', 12);
    }

    public function test_halal_only_hides_confirmed_non_halal_but_keeps_unknown(): void
    {
        $this->makeRestaurant(['name' => 'KFC Unknown']);
        $this->makeRestaurant(['name' => 'KFC Pork Corner', 'halal_status' => HalalStatus::NonHalal]);

        $names = collect($this->search(['halal' => 1])->assertOk()->json('results'))->pluck('name');
        $this->assertContains('KFC Unknown', $names);
        $this->assertNotContains('KFC Pork Corner', $names);

        $this->assertCount(2, $this->search(['halal' => 0])->json('results'));
    }

    public function test_search_wider_reaches_places_outside_the_default_radius(): void
    {
        $this->makeRestaurant(['name' => 'KFC Far', 'longitude' => $this->eastBy(9)]);

        $this->search()->assertOk()->assertJsonCount(0, 'results');

        $this->search(['radiusKm' => 12])->assertOk()
            ->assertJsonPath('results.0.name', 'KFC Far')
            ->assertJsonPath('meta.widerRadiusKm', 25);
    }

    public function test_fuzzy_local_matches_do_not_block_google_but_strong_name_matches_do(): void
    {
        $this->useGoogle();
        Http::fake(['*searchText*' => Http::response(['places' => []])]);
        foreach (range(1, 3) as $i) {
            $this->makeRestaurant(['name' => "Restoran {$i}", 'signature_dish' => 'nasi lemak']);
        }

        $this->search(['query' => 'nasi lemak'])->assertOk();
        Http::assertSentCount(1);

        foreach (range(1, 3) as $i) {
            $this->makeRestaurant(['name' => "KFC {$i}"]);
        }
        $this->search()->assertOk()->assertJsonPath('meta.googleAvailable', true);
        Http::assertSentCount(1);

        $this->search(['includeGoogle' => 1])->assertOk()->assertJsonPath('meta.googleAvailable', false);
        Http::assertSentCount(2);
    }

    public function test_likely_branches_are_kept_together_without_hiding_any(): void
    {
        $near = $this->makeRestaurant(['name' => 'KFC', 'food_category' => 'fast_food', 'longitude' => $this->eastBy(0.2)]);
        // Same name, different category — not the same chain signal, ranks between the branches.
        $between = $this->makeRestaurant(['name' => 'KFC', 'food_category' => 'chicken', 'longitude' => $this->eastBy(1.5)]);
        $far = $this->makeRestaurant(['name' => 'KFC', 'food_category' => 'fast_food', 'longitude' => $this->eastBy(3.5)]);

        $results = $this->search()->assertOk()->json('results');

        $this->assertSame([$near->id, $far->id, $between->id], array_column($results, 'id'));
        $this->assertSame([2, 2, 1], array_column($results, 'groupSize'));
        $this->assertSame(['kfc', 'kfc', null], array_column($results, 'groupKey'));
    }

    public function test_same_name_alone_is_not_treated_as_a_chain(): void
    {
        $this->makeRestaurant(['name' => 'Restoran Ali']);
        $this->makeRestaurant(['name' => 'Restoran Ali', 'longitude' => $this->eastBy(2)]);

        $results = $this->search(['query' => 'restoran ali'])->assertOk()->json('results');

        $this->assertSame([null, null], array_column($results, 'groupKey'));
    }

    public function test_empty_search_suggests_close_spellings_and_counts_the_miss(): void
    {
        $this->makeRestaurant(['name' => 'KFC']);

        $this->search(['query' => 'kfcc'])->assertOk()
            ->assertJsonCount(0, 'results')
            ->assertJsonPath('suggestions.0', 'KFC');
        $this->search(['query' => 'kfcc'])->assertOk();

        $miss = SearchMiss::sole();
        $this->assertSame('kfcc', $miss->query);
        $this->assertSame(2, $miss->hits);
    }

    public function test_resolving_a_google_result_returns_the_nearby_place_shape(): void
    {
        $this->useGoogle();
        Http::fake(['*places/new-google-place*' => Http::response([
            'id' => 'new-google-place',
            'displayName' => ['text' => 'Mamak Corner'],
            'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
            'types' => ['restaurant'],
            'shortFormattedAddress' => 'Seksyen 9, Bangi',
            'currentOpeningHours' => ['openNow' => true],
        ])]);

        $this->postJson('/api/v1/places/resolve', ['googlePlaceId' => 'new-google-place'])->assertOk()
            ->assertJsonPath('restaurant.id', Restaurant::sole()->id)
            ->assertJsonPath('restaurant.openStatus', 'open')
            ->assertJsonPath('restaurant.address', 'Seksyen 9, Bangi');
    }
}
