<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KakaoPlaceSearchTest extends TestCase
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

    private function search(array $params = []): TestResponse
    {
        return $this->getJson('/api/v1/places/search?'.http_build_query(array_merge([
            'query' => 'kebab', 'latitude' => self::LAT, 'longitude' => self::LNG, 'kakao' => 1,
        ], $params)));
    }

    private static function place(string $id, string $name, string $category, int $distance): array
    {
        return [
            'id' => $id, 'place_name' => $name, 'category_name' => $category,
            'road_address_name' => '서울 성북구 안암로 1', 'address_name' => '서울 성북구 안암동',
            'x' => '127.0300', 'y' => '37.5900', 'distance' => (string) $distance,
            'place_url' => "http://place.map.kakao.com/{$id}",
        ];
    }

    private static function kakaoResponse(array $places): array
    {
        return ['documents' => $places, 'meta' => ['total_count' => count($places), 'pageable_count' => count($places), 'is_end' => true]];
    }

    public function test_korea_search_uses_kakao_instead_of_google(): void
    {
        Http::fake([
            'dapi.kakao.com/*' => Http::response(self::kakaoResponse([
                self::place('111', '아다나케밥 본점', '음식점 > 아시아음식 > 튀르키예음식', 221),
            ])),
            '*' => Http::response(['places' => []]),
        ]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('meta.source', 'kakao')
            ->assertJsonPath('meta.fallback', 'kakao')
            ->assertJsonPath('results.0.provenance', 'kakao')
            ->assertJsonPath('results.0.kakaoPlaceId', '111')
            ->assertJsonPath('results.0.kakaoUrl', 'http://place.map.kakao.com/111')
            ->assertJsonPath('results.0.cuisine', 'Turkish')
            ->assertJsonPath('results.0.latitude', 37.59)
            ->assertJsonPath('results.0.longitude', 127.03)
            ->assertJsonPath('results.0.distanceKm', 0.22)
            ->assertJsonPath('results.0.halal.status', 'unknown');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'dapi.kakao.com')
            && $r->hasHeader('Authorization', 'KakaoAK fake-kakao')
            && $r['category_group_code'] === 'FD6'
            && $r['sort'] === 'distance');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'googleapis'));
    }

    public function test_clients_that_do_not_opt_in_keep_the_google_fallback_in_korea(): void
    {
        Http::fake(['*' => Http::response(['places' => []])]);

        $this->search(['kakao' => null])->assertOk();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'dapi.kakao.com'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'searchText'));
    }

    public function test_outside_korea_never_calls_kakao(): void
    {
        Http::fake(['*' => Http::response(['places' => []])]);

        $this->search(['latitude' => 2.9284, 'longitude' => 101.7802])->assertOk();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'dapi.kakao.com'));
    }

    public function test_english_query_also_runs_in_korean_and_merges_by_id(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::sequence()
            ->push(self::kakaoResponse([self::place('1', 'Nasi Place', '음식점 > 아시아음식 > 동남아음식', 900)]))
            ->push(self::kakaoResponse([
                self::place('1', 'Nasi Place', '음식점 > 아시아음식 > 동남아음식', 900),
                self::place('2', '발리문', '음식점 > 아시아음식 > 동남아음식', 300),
            ])),
        ]);

        $results = $this->search(['query' => 'nasi goreng'])->assertOk()->json('results');

        $this->assertSame(['2', '1'], array_column($results, 'kakaoPlaceId'));
        Http::assertSent(fn (Request $r) => $r['query'] === 'nasi goreng');
        Http::assertSent(fn (Request $r) => $r['query'] === '나시고랭');
    }

    public function test_halal_users_do_not_see_bars_or_pork_places(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response(self::kakaoResponse([
            self::place('1', '케밥집', '음식점 > 아시아음식 > 튀르키예음식', 100),
            self::place('2', '고기집', '음식점 > 한식 > 육류,고기 > 삼겹살', 200),
            self::place('3', '바', '음식점 > 술집 > 칵테일바', 300),
        ]))]);

        $halal = $this->search(['halal' => 1])->assertOk()->json('results');
        $everyone = $this->search(['halal' => 0])->assertOk()->json('results');

        $this->assertSame(['1'], array_column($halal, 'kakaoPlaceId'));
        $this->assertCount(3, $everyone);
    }

    public function test_kakao_failure_degrades_to_local_results(): void
    {
        Http::fake(['dapi.kakao.com/*' => Http::response(['errorType' => 'RequestThrottled'], 429)]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('meta.source', 'local')
            ->assertJsonCount(0, 'results');
    }
}
