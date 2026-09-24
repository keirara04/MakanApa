<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\Restaurant;
use App\Models\University;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LandingInsightsTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 2.9290;

    private const LNG = 101.7775;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('marketing.demo', ['label' => 'UKM Bangi', 'university' => 'UKM', 'latitude' => self::LAT, 'longitude' => self::LNG]);
        Config::set('marketing.domain', 'makanapa.test');
    }

    /** @param  array<string, mixed>  $overrides */
    private function restaurant(array $overrides = []): Restaurant
    {
        return Restaurant::create(array_merge([
            'name' => 'Nasi Kandar Pelita', 'latitude' => self::LAT + 0.002, 'longitude' => self::LNG,
            'is_active' => true, 'provider' => 'google', 'provider_place_id' => 'p-'.uniqid(),
            'rating' => 4.6, 'price_level' => 1, 'user_rating_count' => 120,
        ], $overrides));
    }

    private function acceptedBy(User $user, Restaurant $restaurant, University $university): void
    {
        Decision::create([
            'user_id' => $user->id, 'university_id' => $university->id, 'mode' => 'solo',
            'client_token' => 'tok-'.uniqid(), 'latitude' => self::LAT, 'longitude' => self::LNG, 'max_distance' => 2.0,
        ])->recommendations()->create([
            'restaurant_id' => $restaurant->id, 'rank' => 1, 'score' => 1.0, 'shown_at' => now(), 'accepted_at' => now(),
        ]);
    }

    public function test_try_returns_a_real_nearby_pick_without_recording_a_decision(): void
    {
        $near = $this->restaurant();
        $this->restaurant(['name' => 'Far Away Cafe', 'latitude' => self::LAT + 0.05]);

        $this->getJson('/try?km=1')
            ->assertOk()
            ->assertJsonPath('near', 'UKM Bangi')
            ->assertJsonPath('pick.id', $near->id)
            ->assertJsonPath('pick.name', 'Nasi Kandar Pelita')
            ->assertJsonPath('pick.price', '≈ RM10/person')
            ->assertJsonPath('pick.url', "https://makanapa.test/p/{$near->id}-nasi-kandar-pelita?ref=landing");

        $this->assertSame(0, Decision::count());
    }

    public function test_try_skips_places_already_shown(): void
    {
        $first = $this->restaurant();
        $second = $this->restaurant(['name' => 'Mee Goreng Mamak']);

        $this->getJson('/try?km=2&exclude[]='.$first->id)
            ->assertOk()
            ->assertJsonPath('pick.id', $second->id);
    }

    public function test_try_answers_with_no_pick_when_nothing_is_nearby(): void
    {
        $this->getJson('/try?km=1')->assertOk()->assertJsonPath('pick', null);
    }

    public function test_try_rejects_answers_the_page_never_offers(): void
    {
        $this->getJson('/try?km=3')->assertUnprocessable()->assertJsonValidationErrors('km');
        $this->getJson('/try?km=1&mood=sushi')->assertUnprocessable()->assertJsonValidationErrors('mood');
        $this->getJson('/try?km=1&budget=9')->assertUnprocessable()->assertJsonValidationErrors('budget');
    }

    public function test_numbers_below_their_floor_are_left_off_the_page(): void
    {
        Config::set('marketing.stats.min', ['places' => 2, 'picks' => 1, 'community' => 1]);
        $this->restaurant();
        $this->restaurant(['name' => 'Ayam Gepuk Pak Gembus']);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-stat="places"', false)
            ->assertDontSee('data-stat="picks"', false)
            ->assertDontSee('data-stat="community"', false);
    }

    public function test_most_picked_list_needs_enough_distinct_pickers(): void
    {
        $ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'active' => true]);
        $places = collect(['Nasi Kandar Pelita', 'Ayam Gepuk Pak Gembus', 'Mee Goreng Mamak'])
            ->map(fn (string $name) => $this->restaurant(['name' => $name]));
        $lonePick = $this->restaurant(['name' => 'Only One Fan Cafe']);

        foreach (range(1, 2) as $ignored) {
            $user = User::factory()->create();
            $places->each(fn (Restaurant $place) => $this->acceptedBy($user, $place, $ukm));
        }
        $this->acceptedBy(User::factory()->create(), $lonePick, $ukm);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-nearby="picked"', false)
            ->assertSee('Mee Goreng Mamak')
            ->assertDontSee('Only One Fan Cafe');
    }

    public function test_nearby_list_falls_back_to_top_rated_and_says_so(): void
    {
        $this->restaurant(['name' => 'Rated Place One', 'rating' => 4.8]);
        $this->restaurant(['name' => 'Rated Place Two', 'rating' => 4.5]);
        $this->restaurant(['name' => 'Rated Place Three', 'rating' => 4.2]);
        $this->restaurant(['name' => 'Barely Rated', 'rating' => 5.0, 'user_rating_count' => 3]);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-nearby="rated"', false)
            ->assertSeeInOrder(['Rated Place One', 'Rated Place Two', 'Rated Place Three'])
            ->assertDontSee('Barely Rated');
    }

    public function test_nearby_list_is_hidden_when_there_is_too_little_to_show(): void
    {
        $this->restaurant();

        $this->get('/')->assertOk()->assertDontSee('data-nearby=', false);
    }
}
