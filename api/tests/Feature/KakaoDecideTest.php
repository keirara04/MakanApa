<?php

namespace Tests\Feature;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KakaoDecideTest extends TestCase
{
    use RefreshDatabase;

    // Korea University, Seoul.
    private const LAT = 37.5894;

    private const LNG = 127.0276;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.places.provider' => 'google',
            'services.places.google_api_key' => 'fake-google',
            'services.kakao.rest_key' => 'fake-kakao',
        ]);
    }

    private static function kakaoPlace(string $id, string $category = '음식점 > 아시아음식 > 인도음식'): array
    {
        return [
            'id' => $id, 'place_name' => "식당 {$id}", 'category_name' => $category,
            'road_address_name' => '서울 성북구 안암로 1', 'address_name' => '서울 성북구 안암동',
            'x' => '127.0280', 'y' => '37.5890', 'distance' => '300',
            'place_url' => "http://place.map.kakao.com/{$id}",
        ];
    }

    private static function googlePlace(string $id): array
    {
        return [
            'id' => $id, 'displayName' => ['text' => "Place {$id}"],
            'location' => ['latitude' => self::LAT, 'longitude' => self::LNG],
            'types' => ['restaurant'], 'rating' => 4.2, 'priceLevel' => 'PRICE_LEVEL_INEXPENSIVE',
            'currentOpeningHours' => ['openNow' => true],
        ];
    }

    /** @param  array<int, string>  $kakaoIds */
    private function fakeProviders(array $kakaoIds, int $googleCount = 0): void
    {
        $google = ['places' => array_map(fn (int $i) => self::googlePlace("g{$i}"), range(1, max(1, $googleCount)))];
        Http::fake([
            'dapi.kakao.com/*' => Http::response([
                'documents' => array_map(fn (string $id) => self::kakaoPlace($id), $kakaoIds),
                'meta' => ['total_count' => count($kakaoIds), 'pageable_count' => count($kakaoIds), 'is_end' => true],
            ]),
            '*' => Http::response($googleCount > 0 ? $google : ['places' => []]),
        ]);
    }

    private function solo(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/recommendations/solo', array_merge([
            'latitude' => self::LAT, 'longitude' => self::LNG, 'maxDistanceKm' => 2.0, 'moods' => [], 'kakao' => true,
        ], $overrides));
    }

    private function onDecision(array $decision, string $path): TestResponse
    {
        return $this->postJson("/api/v1/decisions/{$decision['decisionId']}/{$path}", [], ['X-Decision-Token' => $decision['clientToken']]);
    }

    public function test_thin_pool_in_korea_gives_a_live_kakao_pick_that_stores_only_id_and_url(): void
    {
        $this->fakeProviders(['k1']);

        $response = $this->solo()
            ->assertOk()
            ->assertJsonPath('recommendation', null)
            ->assertJsonPath('algorithmVersion', 'kakao_live')
            ->assertJsonPath('kakaoPick.kakaoPlaceId', 'k1')
            ->assertJsonPath('kakaoPick.kakaoUrl', 'http://place.map.kakao.com/k1')
            ->assertJsonPath('kakaoPick.name', '식당 k1')
            ->assertJsonPath('kakaoPick.cuisine', 'Indian');

        $decision = Decision::findOrFail($response->json('decisionId'));
        $this->assertSame(['place_id', 'url', 'shown_ids', 'query', 'accepted_at'], array_keys($decision->kakao_pick));
        $this->assertNull($decision->selected_restaurant_id);
        $this->assertSame(0, DecisionRecommendation::count());
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'category.json') && $r['category_group_code'] === 'FD6');
    }

    public function test_a_craving_searches_kakao_by_keyword(): void
    {
        $this->fakeProviders(['k1']);

        $this->solo(['craving' => 'nasi goreng'])->assertOk()->assertJsonPath('kakaoPick.kakaoPlaceId', 'k1');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'keyword.json') && $r['query'] === '나시고랭');
        $this->assertSame('nasi goreng', Decision::latest('id')->first()->kakao_pick['query']);
    }

    public function test_a_korea_mood_searches_kakao_by_its_keyword(): void
    {
        $this->fakeProviders(['k1']);

        $this->solo(['moods' => ['middle_eastern']])->assertOk()->assertJsonPath('kakaoPick.kakaoPlaceId', 'k1');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'keyword.json') && $r['query'] === '케밥');
        $this->assertSame('kebab', Decision::latest('id')->first()->kakao_pick['query']);
    }

    public function test_clients_without_the_flag_get_the_normal_pick(): void
    {
        $this->fakeProviders(['k1'], googleCount: 1);

        $this->solo(['kakao' => null])->assertOk()->assertJsonMissingPath('kakaoPick');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'dapi.kakao.com'));
    }

    public function test_a_full_makanapa_pool_keeps_the_normal_pick(): void
    {
        $this->fakeProviders(['k1'], googleCount: 6);

        $this->solo()->assertOk()->assertJsonMissingPath('kakaoPick');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'dapi.kakao.com'));
    }

    public function test_reroll_skips_shown_places_and_ends_when_kakao_runs_out(): void
    {
        $this->fakeProviders(['k1', 'k2']);
        $decision = $this->solo()->assertOk()->json();
        $first = $decision['kakaoPick']['kakaoPlaceId'];

        $second = $this->onDecision($decision, 'reroll')->assertOk()->json('kakaoPick.kakaoPlaceId');
        $this->assertNotSame($first, $second);
        $this->assertContains($second, ['k1', 'k2']);

        $this->onDecision($decision, 'reroll')->assertOk()->assertJsonPath('kakaoPick', null);
    }

    public function test_accept_is_recorded_on_the_decision(): void
    {
        $this->fakeProviders(['k1']);
        $decision = $this->solo()->json();

        $this->onDecision($decision, 'accept')->assertOk()->assertJsonPath('accepted', true);
        $this->onDecision($decision, 'accept')->assertOk()->assertJsonPath('accepted', true);

        $this->assertNotNull(Decision::findOrFail($decision['decisionId'])->kakao_pick['accepted_at']);
    }

    public function test_brain_tools_do_not_apply_to_kakao_picks(): void
    {
        $this->fakeProviders(['k1']);
        $decision = $this->solo()->json();

        $this->onDecision($decision, 'tune')->assertNotFound();
        $this->onDecision($decision, 'choose')->assertNotFound();
        $this->getJson("/api/v1/decisions/{$decision['decisionId']}/what-if", ['X-Decision-Token' => $decision['clientToken']])->assertNotFound();
    }

    public function test_kakao_with_nothing_falls_back_to_the_normal_pick(): void
    {
        $this->fakeProviders([], googleCount: 1);

        $this->solo()->assertOk()->assertJsonMissingPath('kakaoPick')->assertJsonPath('recommendation.name', 'Place g1');
    }
}
