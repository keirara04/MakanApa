<?php

namespace App\Services;

use App\Models\Decision;
use App\Services\Craving\CravingIntent;
use App\Services\Places\KakaoLocalSearch;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decide's live-only pick in Korea, from Kakao Local — used when MakanApa's own pool is thin or
 * doesn't match the craving. Kakao's terms allow storing only a place's id and place_url, so a
 * Kakao pick never becomes a `restaurants` row: the Decision keeps {place_id, url, shown_ids,
 * query, accepted_at} in `kakao_pick`, reroll re-runs the same search live, and the Brain's
 * per-decision tools (tune, what-if, why-not) don't apply.
 */
class KakaoDecide
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    /**
     * Kakao keyword for the Korea mood chips (KakaoLocalSearch translates them). Moods without
     * one (korean, healthy, quick) keep the plain restaurant category search.
     */
    private const MOOD_QUERIES = [
        'middle_eastern' => 'kebab',
        'indian' => 'indian',
        'malay' => 'malaysian',
        'chicken' => 'fried chicken',
        'burger' => 'burger',
        'thai' => 'thai',
    ];

    /** @param  string[]  $moods */
    private static function moodQuery(array $moods): ?string
    {
        foreach ($moods as $mood) {
            if (isset(self::MOOD_QUERIES[$mood])) {
                return self::MOOD_QUERIES[$mood];
            }
        }

        return null;
    }

    /** Kakao takes over when fewer than `services.kakao.decide_min_pool` places qualify, or none match the craving. */
    public function wanted(array $restaurants, array $preference, ?CravingIntent $craving): bool
    {
        $eligible = $this->recommendations->eligibleRestaurants($restaurants, $preference);

        if (count($eligible) < (int) Config::get('services.kakao.decide_min_pool', 5)) {
            return true;
        }

        return ($this->recommendations->cravingMatchStatus($eligible, $craving)['matched'] ?? true) === false;
    }

    /**
     * @return array{decision: Decision, pick: array<string, mixed>}|null null when Kakao has nothing (or fails) — the caller falls back to the normal pick
     */
    public function decide(KakaoLocalSearch $kakao, array $attributes, array $preference, ?CravingIntent $craving): ?array
    {
        $query = $craving !== null && trim($craving->raw) !== '' ? trim($craving->raw) : self::moodQuery($preference['moodTags'] ?? []);
        $pick = $this->pickFrom($kakao, $query, (float) $preference['latitude'], (float) $preference['longitude'], (float) $preference['maxDistanceKm'], (bool) $preference['halalOnly'], []);

        if ($pick === null) {
            return null;
        }

        $decision = Decision::create([
            ...$attributes,
            'selected_restaurant_id' => null,
            'algorithm_version' => Decision::KAKAO_ALGORITHM,
            'kakao_pick' => [
                'place_id' => $pick['kakao_place_id'],
                'url' => $pick['kakao_url'],
                'shown_ids' => [$pick['kakao_place_id']],
                'query' => $query,
                'accepted_at' => null,
            ],
        ]);

        return ['decision' => $decision, 'pick' => $pick];
    }

    /** "Another one": the same search again, minus every place already shown. Null once Kakao runs out. */
    public function reroll(Decision $decision, bool $halalOnly): ?array
    {
        $state = $decision->kakao_pick;
        $kakao = KakaoLocalSearch::at((float) $decision->latitude, (float) $decision->longitude, true);
        if ($kakao === null) {
            return null;
        }

        $pick = $this->pickFrom($kakao, $state['query'] ?? null, (float) $decision->latitude, (float) $decision->longitude, (float) $decision->max_distance, $halalOnly, $state['shown_ids'] ?? []);

        // ponytail: no row lock — two near-simultaneous rerolls can both skip the same shown_ids
        // and show two places. lockForUpdate() on the decision (live call outside it) if it shows.
        if ($pick !== null) {
            $decision->update(['kakao_pick' => [
                ...$state,
                'place_id' => $pick['kakao_place_id'],
                'url' => $pick['kakao_url'],
                'shown_ids' => [...($state['shown_ids'] ?? []), $pick['kakao_place_id']],
                'accepted_at' => null,
            ]]);
        }

        return $pick;
    }

    /** Idempotent, like accept() for a normal pick. */
    public function accept(Decision $decision): bool
    {
        $state = $decision->kakao_pick;
        if (($state['accepted_at'] ?? null) === null) {
            $decision->update(['kakao_pick' => [...$state, 'accepted_at' => now()->toIso8601String()]]);
        }

        return true;
    }

    /** The `kakaoPick` response shape — live Kakao fields, shown and never stored. */
    public static function present(array $pick): array
    {
        return [
            'kakaoPlaceId' => $pick['kakao_place_id'],
            'kakaoUrl' => $pick['kakao_url'],
            'name' => $pick['name'],
            'cuisine' => $pick['cuisines'][0] ?? null,
            'address' => $pick['address'],
            'latitude' => $pick['latitude'],
            'longitude' => $pick['longitude'],
            'distanceKm' => $pick['distance_km'],
        ];
    }

    private function pickFrom(KakaoLocalSearch $kakao, ?string $query, float $latitude, float $longitude, float $radiusKm, bool $halalOnly, array $excludedIds): ?array
    {
        try {
            $rows = $query !== null
                ? $kakao->search($query, $latitude, $longitude, $radiusKm, $halalOnly)
                : $kakao->nearby($latitude, $longitude, $radiusKm, $halalOnly);
        } catch (Throwable $e) {
            Log::warning('Decide: Kakao pick failed, using the normal pool', ['error' => $e->getMessage()]);

            return null;
        }

        $rows = array_values(array_filter($rows, fn (array $row) => ! in_array($row['kakao_place_id'], $excludedIds, true)));

        // ponytail: uniform random over Kakao's nearest page (≤30 rows), no scoring — Kakao gives
        // distance only. Upgrade: weight by distance, or map cuisine labels onto Selera traits.
        return $rows === [] ? null : $rows[random_int(0, count($rows) - 1)];
    }
}
