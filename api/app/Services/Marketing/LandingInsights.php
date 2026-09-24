<?php

namespace App\Services\Marketing;

use App\Http\Controllers\SharePlaceController;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\University;
use App\Services\Halal\HalalPresenter;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\RecommendationHeadline;
use App\Support\ShareLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Real data for the public landing page: the "try it" demo pick, the live numbers strip and the
 * "most picked near …" list. Everything reads MakanApa's own database only — never Google — and
 * is anchored on config('marketing.demo'), since the page doesn't ask for the visitor's location.
 */
class LandingInsights
{
    /** Radius for the top-rated fallback list around the demo anchor. */
    private const NEARBY_LIST_RADIUS_KM = 3.0;

    /** Below this many places, the "near …" list is hidden rather than looking empty. */
    private const MIN_LIST_ITEMS = 3;

    /** A top-rated fallback place needs at least this many Google ratings to count. */
    private const MIN_RATING_COUNT = 20;

    public function __construct(
        private readonly PlacesService $places,
        private readonly RecommendationService $recommendations,
        private readonly HalalPresenter $halal,
    ) {}

    /**
     * One real pick around the demo anchor, ranked by the same RecommendationService the app uses.
     * Nothing is recorded: demo picks aren't decisions and never feed stats or trending.
     *
     * @param  array<int, int>  $excludeIds  places already shown this session ("Cari lagi")
     * @return array{id: int, name: string, headline: string, distanceKm: float, price: ?string, rating: ?float, halal: string, url: string}|null
     */
    public function demoPick(?string $mood, ?int $budgetMax, float $maxDistanceKm, array $excludeIds = []): ?array
    {
        $anchor = $this->anchor();

        $restaurants = array_values(array_filter(
            $this->places->localRestaurantsNear($anchor['latitude'], $anchor['longitude'], $maxDistanceKm),
            fn (array $restaurant) => ! in_array($restaurant['id'], $excludeIds, true),
        ));

        $result = $this->recommendations->recommend($restaurants, [
            'moodTags' => $mood !== null ? [$mood] : [],
            'cuisines' => [],
            'cravingIntent' => null,
            'budgetMax' => $budgetMax,
            'maxDistanceKm' => $maxDistanceKm,
            'latitude' => $anchor['latitude'],
            'longitude' => $anchor['longitude'],
            'halalOnly' => false,
        ]);

        if ($result['pick'] === null) {
            return null;
        }

        $restaurant = $result['pick']['restaurant'];

        return [
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'headline' => RecommendationHeadline::for($restaurant),
            'distanceKm' => round($result['pick']['distanceKm'], 1),
            'price' => SharePlaceController::priceLabel($restaurant['price_level']),
            'rating' => $restaurant['rating'],
            'halal' => $this->halal->summaryFromArray($restaurant)['display']['shortLabel'],
            'url' => ShareLinks::place($restaurant['id'], $restaurant['name'], 'landing'),
        ];
    }

    /**
     * Live totals for the numbers strip. Each is null when below its configured floor.
     *
     * @return array{places: ?int, picks: ?int, community: ?int}
     */
    public function stats(): array
    {
        $totals = Cache::remember('marketing:landing:stats:v1', (int) Config::get('marketing.stats.cache_seconds'), fn () => [
            'places' => $this->activeRestaurants()->count(),
            'picks' => DecisionRecommendation::whereNotNull('accepted_at')->count(),
            'community' => $this->activeRestaurants()->whereNotNull('source_submission_id')->count(),
        ]);

        $floors = Config::get('marketing.stats.min');
        foreach ($totals as $key => $total) {
            $totals[$key] = $total >= ($floors[$key] ?? 0) ? $total : null;
        }

        return $totals;
    }

    /**
     * What people around the demo campus actually picked lately; when too few places have enough
     * distinct pickers yet, the best-rated places nearby instead — labelled as such, never passed
     * off as "most picked". Empty when neither has enough to show.
     *
     * @return array{kind: 'picked'|'rated'|null, items: array<int, array{name: string, headline: string, distanceKm: float, rating: ?float, pickers: ?int, url: string}>}
     */
    public function nearbyList(): array
    {
        return Cache::remember('marketing:landing:nearby:v1', (int) Config::get('marketing.stats.cache_seconds'), function () {
            $picked = $this->mostPicked();
            if (count($picked) >= self::MIN_LIST_ITEMS) {
                return ['kind' => 'picked', 'items' => $picked];
            }

            $rated = $this->topRated();
            if (count($rated) >= self::MIN_LIST_ITEMS) {
                return ['kind' => 'rated', 'items' => $rated];
            }

            return ['kind' => null, 'items' => []];
        });
    }

    /** @return array{label: string, latitude: float, longitude: float, university: string} */
    public function anchor(): array
    {
        return Config::get('marketing.demo');
    }

    /**
     * Same scoping rules as CommunityPickStats (registered users only, the university snapshot on
     * the decision, the community feed's window and min distinct pickers), so the landing page can
     * never show a place as "most picked" that the in-app Community tab wouldn't.
     *
     * @return array<int, array<string, mixed>>
     */
    private function mostPicked(): array
    {
        $universityId = University::where('short_name', $this->anchor()['university'])->value('id');
        if ($universityId === null) {
            return [];
        }

        $feed = Config::get('recommendation.community_feed');

        $rows = DecisionRecommendation::query()
            ->join('decisions', 'decisions.id', '=', 'decision_recommendations.decision_id')
            ->whereNotNull('decision_recommendations.accepted_at')
            ->where('decision_recommendations.accepted_at', '>=', now()->subDays($feed['window_days']))
            ->whereNotNull('decisions.user_id')
            ->where('decisions.university_id', $universityId)
            ->groupBy('decision_recommendations.restaurant_id')
            ->havingRaw('count(distinct decisions.user_id) >= ?', [$feed['min_pickers']])
            ->orderByRaw('count(distinct decisions.user_id) desc')
            ->limit(5)
            ->get([
                'decision_recommendations.restaurant_id',
                DB::raw('count(distinct decisions.user_id) as picker_count'),
            ]);

        $restaurants = $this->activeRestaurants()->whereIn('id', $rows->pluck('restaurant_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($row) => $restaurants->has($row->restaurant_id))
            ->map(fn ($row) => $this->listItem($restaurants[$row->restaurant_id]->toRecommendationArray(), (int) $row->picker_count))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function topRated(): array
    {
        $anchor = $this->anchor();

        return collect($this->places->localRestaurantsNear($anchor['latitude'], $anchor['longitude'], self::NEARBY_LIST_RADIUS_KM))
            ->filter(fn (array $restaurant) => $restaurant['rating'] !== null && ($restaurant['user_rating_count'] ?? 0) >= self::MIN_RATING_COUNT)
            ->sortBy([['rating', 'desc'], ['user_rating_count', 'desc']])
            ->take(5)
            ->map(fn (array $restaurant) => $this->listItem($restaurant, null))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $restaurant  Restaurant::toRecommendationArray() row
     * @return array<string, mixed>
     */
    private function listItem(array $restaurant, ?int $pickers): array
    {
        $anchor = $this->anchor();

        return [
            'name' => $restaurant['name'],
            'headline' => RecommendationHeadline::for($restaurant),
            'distanceKm' => round(RecommendationService::distanceKm(
                $anchor['latitude'], $anchor['longitude'], $restaurant['latitude'], $restaurant['longitude']
            ), 1),
            'rating' => $restaurant['rating'],
            'pickers' => $pickers,
            'url' => ShareLinks::place($restaurant['id'], $restaurant['name'], 'landing'),
        ];
    }

    private function activeRestaurants(): Builder
    {
        return Restaurant::query()->where('is_active', true)->whereNull('merged_into_restaurant_id');
    }
}
