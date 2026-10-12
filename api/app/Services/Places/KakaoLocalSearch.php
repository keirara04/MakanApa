<?php

namespace App\Services\Places;

use App\Support\Halal\HalalStatus;
use App\Support\Region;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Kakao Local search — searchPlaces()'s fallback and Decide's live-only pick inside South Korea, where Google's
 * coverage is thin. Kakao's terms only allow storing a place's id and place_url, so results are
 * live-only: this class never touches the database, and PlacesService never upserts its rows
 * (no resolve path — the client links out to `kakao_url` instead).
 */
class KakaoLocalSearch
{
    private const KEYWORD_ENDPOINT = 'https://dapi.kakao.com/v2/local/search/keyword.json';

    private const CATEGORY_ENDPOINT = 'https://dapi.kakao.com/v2/local/search/category.json';

    /** Kakao's category group for restaurants — keeps embassies, marts and travel agencies out of a "halal" search. */
    private const RESTAURANT_GROUP = 'FD6';

    /** Kakao caps radius at 20km. */
    private const MAX_RADIUS_M = 20000;

    /**
     * English/Malay → Korean, applied with strtr() (longest key wins). Kakao indexes some English
     * ("halal", "kebab" match), but Malay dish names return nothing, so the translated query runs
     * alongside the raw one. Grow this from SearchMiss rows in Korea.
     */
    private const TRANSLATIONS = [
        'halal' => '할랄',
        'nasi goreng' => '나시고랭',
        'mee goreng' => '미고랭',
        'nasi lemak' => '나시르막',
        'satay' => '사테',
        'roti canai' => '로티',
        'chicken rice' => '치킨라이스',
        'fried chicken' => '치킨',
        'chicken' => '치킨',
        'ayam' => '치킨',
        'mamak' => '인도음식',
        'indian' => '인도음식',
        'curry' => '커리',
        'kari' => '커리',
        'malaysian' => '말레이시아',
        'malay' => '말레이시아',
        'indonesian' => '인도네시아',
        'thai' => '태국음식',
        'vietnamese' => '베트남음식',
        'pho' => '쌀국수',
        'turkish' => '튀르키예음식',
        'kebab' => '케밥',
        'arab' => '아랍음식',
        'middle eastern' => '중동음식',
        'uzbek' => '우즈베키스탄',
        'pakistani' => '파키스탄',
        'korean bbq' => '고기구이',
        'bbq' => '바베큐',
        'seafood' => '해산물',
        'fish' => '생선',
        'beef' => '소고기',
        'lamb' => '양고기',
        'mutton' => '양고기',
        'noodles' => '국수',
        'noodle' => '국수',
        'mee' => '국수',
        'ramen' => '라멘',
        'sushi' => '초밥',
        'bibimbap' => '비빔밥',
        'tteokbokki' => '떡볶이',
        'kimbap' => '김밥',
        'bakery' => '베이커리',
        'pizza' => '피자',
        'burger' => '햄버거',
        'vegetarian' => '채식',
        'vegan' => '비건',
    ];

    /**
     * Kakao category_name segment → English cuisine label, most specific segment wins
     * ("음식점 > 아시아음식 > 인도음식" → Indian). Korean place names stay as they are; this label
     * is what tells an English reader what the place serves.
     */
    private const CUISINE_LABELS = [
        '인도음식' => 'Indian',
        '튀르키예음식' => 'Turkish',
        '베트남음식' => 'Vietnamese',
        '태국음식' => 'Thai',
        '동남아음식' => 'Southeast Asian',
        '중동음식' => 'Middle Eastern',
        '멕시칸,브라질' => 'Mexican',
        '이탈리안' => 'Italian',
        '초밥,롤' => 'Sushi',
        '일본식라면' => 'Ramen',
        '돈까스,우동' => 'Katsu and udon',
        '육류,고기' => 'Korean BBQ',
        '해물,생선' => 'Seafood',
        '냉면' => 'Cold noodles',
        '국수' => 'Noodles',
        '피자' => 'Pizza',
        '햄버거' => 'Burgers',
        '샌드위치' => 'Sandwiches',
        '제과,베이커리' => 'Bakery',
        '샤브샤브' => 'Shabu-shabu',
        '뷔페' => 'Buffet',
        '도시락' => 'Lunch boxes',
        '치킨' => 'Fried chicken',
        '분식' => 'Korean street food',
        '패스트푸드' => 'Fast food',
        '아시아음식' => 'Asian',
        '한식' => 'Korean',
        '일식' => 'Japanese',
        '중식' => 'Chinese',
        '양식' => 'Western',
        '간식' => 'Snacks',
        '술집' => 'Bar',
    ];

    /**
     * Category/name fragments that mean alcohol or pork — rows carrying one are hidden for
     * halal-preference users. This only hides likely-bad places; nothing here ever marks a place
     * halal (Kakao rows stay `unknown`, same as an unverified local row).
     */
    private const NON_HALAL_SIGNALS = ['술집', '호프', '요리주점', '포장마차', '와인', '삼겹살', '족발', '보쌈', '돼지', '순대', '감자탕', '곱창', '막창'];

    public function __construct(private readonly string $restKey) {}

    /**
     * A client for this point, or null when Kakao mustn't be called: the client can't render
     * Kakao rows, the point is outside Korea, no key, or fixture mode (tests, local dev without
     * live providers never place a live call — same gate as Google).
     */
    public static function at(float $latitude, float $longitude, bool $clientAllows): ?self
    {
        $key = Config::get('services.kakao.rest_key');

        return $clientAllows && self::covers($latitude, $longitude) && ! empty($key)
            && Config::get('services.places.provider', 'fixture') !== 'fixture'
            ? new self($key)
            : null;
    }

    /** Rough South Korea box (takes in some sea, harmless) — where Kakao replaces the Google fallback. */
    public static function covers(float $latitude, float $longitude): bool
    {
        return Region::at($latitude, $longitude) === Region::Korea;
    }

    /** The Korean form of $query, or null when the dictionary changes nothing. */
    public static function translate(string $query): ?string
    {
        $normalized = mb_strtolower(trim($query));
        // ponytail: strtr matches substrings ("thai" in "thailand" → junk second query, one wasted
        // call with no results). Switch to word-boundary preg_replace if misses show it matters.
        $translated = strtr($normalized, self::TRANSLATIONS);

        return $translated === $normalized ? null : $translated;
    }

    /**
     * Raw query plus its Korean form (at most 2 calls), merged by Kakao id, nearest first.
     *
     * @return array<int, array<string, mixed>> rows shaped like PlacesService's search results, provenance 'kakao'
     */
    public function search(string $query, float $latitude, float $longitude, float $radiusKm, bool $halalOnly): array
    {
        $calls = array_map(
            fn (string $q) => [self::KEYWORD_ENDPOINT, ['query' => $q]],
            array_values(array_filter([trim($query), self::translate($query)])),
        );

        return $this->fetch($calls, $latitude, $longitude, $radiusKm, $halalOnly);
    }

    /**
     * Any restaurant near a point (category search, one call) — Decide's "anything" in Korea.
     *
     * @return array<int, array<string, mixed>> same shape as search()
     */
    public function nearby(float $latitude, float $longitude, float $radiusKm, bool $halalOnly): array
    {
        return $this->fetch([[self::CATEGORY_ENDPOINT, []]], $latitude, $longitude, $radiusKm, $halalOnly);
    }

    /**
     * @param  array<int, array{0: string, 1: array<string, mixed>}>  $calls  endpoint + extra params
     * @return array<int, array<string, mixed>>
     */
    private function fetch(array $calls, float $latitude, float $longitude, float $radiusKm, bool $halalOnly): array
    {
        $radiusM = (int) min(self::MAX_RADIUS_M, round($radiusKm * 1000));

        $rows = [];
        foreach ($calls as [$endpoint, $params]) {
            $response = Http::withHeaders(['Authorization' => 'KakaoAK '.$this->restKey])
                ->timeout(4)
                ->get($endpoint, [
                    ...$params,
                    'x' => $longitude,
                    'y' => $latitude,
                    'radius' => $radiusM,
                    'sort' => 'distance',
                    'category_group_code' => self::RESTAURANT_GROUP,
                    'size' => 15,
                ])
                ->throw();

            foreach ($response->json('documents', []) as $place) {
                $rows[$place['id']] ??= $this->toRow($place);
            }
        }

        if ($halalOnly) {
            $rows = array_filter($rows, fn (array $row) => ! $this->hasNonHalalSignal($row['_kakao_category'].' '.$row['name']));
        }

        usort($rows, fn (array $a, array $b) => $a['distance_km'] <=> $b['distance_km']);

        return array_map(fn (array $row) => array_diff_key($row, ['_kakao_category' => true]), $rows);
    }

    private function toRow(array $place): array
    {
        $label = $this->cuisineLabel($place['category_name'] ?? '');

        return [
            'id' => null,
            'google_place_id' => null,
            'kakao_place_id' => (string) $place['id'],
            'kakao_url' => $place['place_url'] ?? null,
            'name' => $place['place_name'],
            'address' => ($place['road_address_name'] ?? '') ?: ($place['address_name'] ?? null),
            // Kakao's x is longitude, y is latitude.
            'latitude' => (float) $place['y'],
            'longitude' => (float) $place['x'],
            'price_level' => null,
            'rating' => null,
            'food_category' => null,
            'signature_dish' => null,
            'cuisines' => $label ? [$label] : [],
            'tags' => [],
            'open_status' => 'unknown',
            'closes_at' => null,
            'halal_status' => HalalStatus::Unknown->value,
            'provenance' => 'kakao',
            'is_community_find' => false,
            'distance_km' => round(((int) ($place['distance'] ?? 0)) / 1000, 2),
            'group_key' => null,
            'group_size' => 1,
            '_kakao_category' => (string) ($place['category_name'] ?? ''),
        ];
    }

    private function cuisineLabel(string $categoryName): ?string
    {
        foreach (array_reverse(array_map('trim', explode('>', $categoryName))) as $segment) {
            if (isset(self::CUISINE_LABELS[$segment])) {
                return self::CUISINE_LABELS[$segment];
            }
        }

        return null;
    }

    private function hasNonHalalSignal(string $text): bool
    {
        foreach (self::NON_HALAL_SIGNALS as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }
}
