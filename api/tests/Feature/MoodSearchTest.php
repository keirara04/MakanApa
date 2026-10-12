<?php

namespace Tests\Feature;

use App\Filament\Widgets\PickMatchWidget;
use App\Models\Decision;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * An abroad cuisine mood ("Indian") that the area pool has no match for asks Google for that
 * cuisine once, and the response says whether anything matched.
 */
class MoodSearchTest extends TestCase
{
    use RefreshDatabase;

    // Penn State, State College.
    private const LAT = 40.7982;

    private const LNG = -77.8599;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.places.provider' => 'google', 'services.places.google_api_key' => 'fake-key']);
    }

    private static function place(string $id, array $types): array
    {
        return [
            'id' => $id, 'displayName' => ['text' => "Place {$id}"],
            'location' => ['latitude' => self::LAT + 0.001, 'longitude' => self::LNG],
            'types' => $types, 'rating' => 4.3, 'priceLevel' => 'PRICE_LEVEL_INEXPENSIVE',
            'currentOpeningHours' => ['openNow' => true],
        ];
    }

    private function solo(string $mood): TestResponse
    {
        return $this->postJson('/api/v1/recommendations/solo', [
            'latitude' => self::LAT, 'longitude' => self::LNG, 'maxDistanceKm' => 2.0, 'moods' => [$mood],
        ]);
    }

    public function test_a_missing_cuisine_is_searched_for_and_picked(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [self::place('burger', ['hamburger_restaurant', 'restaurant'])]]),
            '*searchText*' => Http::response(['places' => [self::place('curry', ['indian_restaurant', 'restaurant'])]]),
        ]);

        $this->solo('indian')->assertOk()
            ->assertJsonPath('recommendation.name', 'Place curry')
            ->assertJsonPath('moodMatch', ['mood' => 'indian', 'label' => 'Indian', 'matched' => true]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchText') && $r['includedType'] === 'indian_restaurant');
    }

    public function test_no_search_when_the_pool_already_has_the_cuisine(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [self::place('curry', ['indian_restaurant', 'restaurant'])]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $this->solo('indian')->assertOk()->assertJsonPath('moodMatch.matched', true);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'searchText'));
    }

    public function test_a_non_halal_match_does_not_count_for_halal_users(): void
    {
        $user = User::factory()->create(['halal_preference' => true]);
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [self::place('curry', ['indian_restaurant', 'restaurant'])]]),
            '*searchText*' => Http::response(['places' => [self::place('halal-curry', ['indian_restaurant', 'restaurant'])]]),
        ]);
        $this->solo('indian')->assertOk(); // stores "curry"
        Restaurant::where('provider_place_id', 'curry')->update(['halal_status' => HalalStatus::NonHalal->value]);
        Cache::flush();

        $this->actingAs($user)->solo('indian')->assertOk()
            ->assertJsonPath('recommendation.name', 'Place halal-curry')
            ->assertJsonPath('moodMatch.matched', true);
    }

    public function test_nothing_found_says_so(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [self::place('burger', ['hamburger_restaurant', 'restaurant'])]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $this->solo('indian')->assertOk()
            ->assertJsonPath('recommendation.name', 'Place burger')
            ->assertJsonPath('moodMatch', ['mood' => 'indian', 'label' => 'Indian', 'matched' => false]);

        // Feeds the admin's per-campus match rate.
        $this->assertFalse(Decision::latest('id')->first()->intent_matched);
        $this->actingAs(User::factory()->create(['role' => 'superadmin', 'status' => 'active']), 'web');
        Livewire::test(PickMatchWidget::class)->assertOk()->assertSee('0% matched')->assertSee('United States');
    }

    public function test_malaysian_dish_moods_search_by_dish_name(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [self::place('burger', ['hamburger_restaurant', 'restaurant'])]]),
            '*searchText*' => Http::response(['places' => [array_merge(self::place('kandar', ['restaurant']), ['displayName' => ['text' => 'Nasi Kandar Pelita']])]]),
        ]);

        $this->solo('nasi_kandar')->assertOk()
            ->assertJsonPath('recommendation.name', 'Nasi Kandar Pelita')
            ->assertJsonPath('moodMatch', ['mood' => 'nasi_kandar', 'label' => 'nasi kandar', 'matched' => true]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchText') && $r['textQuery'] === 'nasi kandar');
    }

    public function test_another_one_stays_with_the_mood_while_it_can(): void
    {
        Http::fake([
            '*searchNearby*' => Http::response(['places' => [
                self::place('curry1', ['indian_restaurant', 'restaurant']),
                self::place('curry2', ['indian_restaurant', 'restaurant']),
                self::place('burger1', ['hamburger_restaurant', 'restaurant']),
                self::place('burger2', ['hamburger_restaurant', 'restaurant']),
                self::place('burger3', ['hamburger_restaurant', 'restaurant']),
            ]]),
            '*searchText*' => Http::response(['places' => []]),
        ]);

        $decision = $this->solo('indian')->assertOk();
        $first = $decision->json('recommendation.name');
        $this->assertStringStartsWith('Place curry', $first);

        $next = $this->postJson("/api/v1/decisions/{$decision->json('decisionId')}/reroll", [], ['X-Decision-Token' => $decision->json('clientToken')])->assertOk();
        $this->assertSame($first === 'Place curry1' ? 'Place curry2' : 'Place curry1', $next->json('recommendation.name'));
    }
}
