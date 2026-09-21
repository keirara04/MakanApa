<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\RestaurantVibeVote;
use App\Services\RecommendationService;
use App\Support\CommunityTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Read-only trending feed scoped to the caller's community: their university (via the
 * decisions.university_id/restaurant_vibe_votes.university_id snapshots) or, for Public
 * accounts, restaurants within a fixed radius of their current location. Registered users
 * only (decisions.user_id not null) — Community represents explicit profile affiliation, so
 * anonymous/installation-only activity is deliberately excluded from both branches.
 */
class CommunityController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $affiliationType = $user->affiliation?->type;
        $isUniversity = $affiliationType === 'university';

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lon = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if (! $isUniversity && ($lat === null || $lon === null)) {
            return response()->json(['message' => 'Location is required for the Public community feed.'], 422);
        }

        $config = Config::get('recommendation.community_feed');
        $windowStart = now()->subDays($config['window_days']);

        $query = DecisionRecommendation::query()
            ->join('decisions', 'decisions.id', '=', 'decision_recommendations.decision_id')
            ->whereNotNull('decision_recommendations.accepted_at')
            ->where('decision_recommendations.accepted_at', '>=', $windowStart)
            ->whereNotNull('decisions.user_id');

        if ($isUniversity) {
            $query->where('decisions.university_id', $user->universityId());
        } else {
            $radiusKm = (float) $config['public_radius_km'];
            $latDelta = $radiusKm / 111.0;
            $lonDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

            $query->whereNull('decisions.university_id')
                ->join('restaurants', 'restaurants.id', '=', 'decision_recommendations.restaurant_id')
                ->whereBetween('restaurants.latitude', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('restaurants.longitude', [$lon - $lonDelta, $lon + $lonDelta]);
        }

        $aggregates = $query
            ->selectRaw('decision_recommendations.restaurant_id as restaurant_id')
            ->selectRaw('count(*) as pick_count')
            ->selectRaw('count(distinct decisions.user_id) as picker_count')
            ->groupBy('decision_recommendations.restaurant_id')
            // Postgres can't reference a SELECT alias in HAVING — use the raw aggregate.
            ->havingRaw('count(distinct decisions.user_id) >= ?', [$config['min_pickers']])
            ->orderByDesc('picker_count')
            ->get();

        if (! $isUniversity && $aggregates->isNotEmpty()) {
            // The bounding box above is only a candidate reducer (a square, not the real
            // radius) — apply the exact circular cutoff before ranking/limiting, not after,
            // so a genuinely-nearby lower-count restaurant can't be pushed out by a
            // high-count one that only looked close because of a bbox corner.
            $coords = Restaurant::whereIn('id', $aggregates->pluck('restaurant_id'))
                ->get(['id', 'latitude', 'longitude'])
                ->keyBy('id');

            $aggregates = $aggregates->filter(function ($row) use ($coords, $lat, $lon, $radiusKm) {
                $restaurant = $coords->get($row->restaurant_id);

                return $restaurant && RecommendationService::distanceKm(
                    $lat, $lon, (float) $restaurant->latitude, (float) $restaurant->longitude
                ) <= $radiusKm;
            })->values();
        }

        $aggregates = $aggregates->sortByDesc('picker_count')->take($config['limit'])->values();

        if ($aggregates->isEmpty()) {
            return response()->json([
                'community' => $this->communityInfo($affiliationType, $user->universityShortName()),
                'trending' => [],
                'newInArea' => $this->newInArea($isUniversity, $user->universityId(), $lat, $lon),
            ]);
        }

        $restaurantIds = $aggregates->pluck('restaurant_id')->all();
        // is_active filter: a closure-approved (soft-deleted) restaurant must drop out of the
        // live feed even though its historical decisions/vibe votes stay untouched for analytics.
        $restaurants = Restaurant::whereIn('id', $restaurantIds)->where('is_active', true)->with('cuisines')->get()->keyBy('id');
        $trendingVibes = $this->trendingVibes($restaurantIds, $isUniversity, $user->universityId());

        $trending = $aggregates
            ->map(function ($row) use ($restaurants, $trendingVibes, $lat, $lon) {
                $restaurant = $restaurants->get($row->restaurant_id);
                if (! $restaurant) {
                    return null;
                }

                $base = $restaurant->toRecommendationArray();

                return [
                    'id' => $base['id'],
                    'name' => $base['name'],
                    'foodCategory' => $base['food_category'],
                    'rating' => $base['rating'],
                    'priceLevel' => $base['price_level'],
                    'cuisines' => $base['cuisines'],
                    'openStatus' => $base['open_status'],
                    'pickCount' => (int) $row->pick_count,
                    'pickerCount' => (int) $row->picker_count,
                    'distanceKm' => ($lat !== null && $lon !== null)
                        ? RecommendationService::distanceKm($lat, $lon, $base['latitude'], $base['longitude'])
                        : null,
                    'trendingVibe' => $trendingVibes[$row->restaurant_id] ?? null,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'community' => $this->communityInfo($affiliationType, $user->universityShortName()),
            'trending' => $trending,
            'newInArea' => $this->newInArea($isUniversity, $user->universityId(), $lat, $lon),
        ]);
    }

    /**
     * Recently-approved community submissions, independent of the trending aggregate above — a
     * brand-new place has zero decisions/picks and would never clear min_pickers, but the person
     * who just got it approved should still see it here. Filtered on `source_submission_id`, not
     * `provider = user_submitted` — RestaurantSubmissionController::approveNewPlace() sets
     * `provider = 'google'` when the submitter found the place via Google search (the common
     * path through AddPlaceFlow), so provider alone can't distinguish "came through community
     * moderation" from "background Google sync"; source_submission_id is set by both approval
     * branches and left null by the background sync in PlacesService.
     * Scoped the same way as the trending branch: university via the source submission's
     * university_id snapshot, Public via lat/lon radius.
     *
     * @return array<int, array<string, mixed>>
     */
    private function newInArea(bool $isUniversity, ?int $universityId, ?float $lat, ?float $lon): array
    {
        $config = Config::get('recommendation.community_feed');
        $since = now()->subDays($config['new_in_area_days']);

        $query = Restaurant::query()
            ->where('is_active', true)
            ->whereNotNull('source_submission_id')
            ->where('created_at', '>=', $since)
            ->with('cuisines');

        if ($isUniversity) {
            $query->whereHas('sourceSubmission', fn ($q) => $q->where('university_id', $universityId));
        } else {
            if ($lat === null || $lon === null) {
                return [];
            }

            $radiusKm = (float) $config['public_radius_km'];
            $latDelta = $radiusKm / 111.0;
            $lonDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

            $query->whereHas('sourceSubmission', fn ($q) => $q->whereNull('university_id'))
                ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('longitude', [$lon - $lonDelta, $lon + $lonDelta]);
        }

        $restaurants = $query->orderByDesc('created_at')->limit($config['new_in_area_limit'] * 3)->get();

        if (! $isUniversity) {
            // Same bbox-then-exact-radius pattern as the trending branch above.
            $restaurants = $restaurants->filter(fn ($restaurant) => RecommendationService::distanceKm(
                $lat, $lon, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm);
        }

        return $restaurants
            ->take($config['new_in_area_limit'])
            ->map(function ($restaurant) use ($lat, $lon) {
                $base = $restaurant->toRecommendationArray();

                return [
                    'id' => $base['id'],
                    'name' => $base['name'],
                    'foodCategory' => $base['food_category'],
                    'rating' => $base['rating'],
                    'priceLevel' => $base['price_level'],
                    'cuisines' => $base['cuisines'],
                    'openStatus' => $base['open_status'],
                    'distanceKm' => ($lat !== null && $lon !== null)
                        ? RecommendationService::distanceKm($lat, $lon, $base['latitude'], $base['longitude'])
                        : null,
                    'approvedAt' => $restaurant->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * One grouped query for the whole candidate list, never N — mirrors
     * PresentsRecommendation::communityTagBadge()'s per-restaurant confidence gate
     * (same config('recommendation.vibe_badge') thresholds), scoped per-community so a vibe
     * popular in one community can't leak into another's trending feed.
     *
     * @return array<int, string> restaurant_id => CommunityTag value
     */
    private function trendingVibes(array $restaurantIds, bool $isUniversity, ?int $universityId): array
    {
        if (empty($restaurantIds)) {
            return [];
        }

        $query = RestaurantVibeVote::query()->whereIn('restaurant_id', $restaurantIds);
        $query = $isUniversity ? $query->where('university_id', $universityId) : $query->whereNull('university_id');

        $rows = $query
            ->selectRaw('restaurant_id, vibe, count(*) as votes')
            ->groupBy('restaurant_id', 'vibe')
            ->get()
            ->groupBy('restaurant_id');

        $minVotes = (int) Config::get('recommendation.vibe_badge.min_votes', 5);
        $minShare = (float) Config::get('recommendation.vibe_badge.min_share', 0.4);

        $result = [];
        foreach ($rows as $restaurantId => $votes) {
            $total = (int) $votes->sum('votes');
            $top = $votes->sortByDesc('votes')->first();

            if ($total > 0 && $top->votes >= $minVotes && ($top->votes / $total) >= $minShare) {
                $result[$restaurantId] = $top->vibe instanceof CommunityTag ? $top->vibe->value : $top->vibe;
            }
        }

        return $result;
    }

    private function communityInfo(?string $affiliationType, ?string $university): array
    {
        if ($affiliationType === 'university' && $university !== null) {
            return ['type' => 'university', 'university' => $university, 'label' => "{$university} Community"];
        }

        return ['type' => 'public', 'university' => null, 'label' => 'Community'];
    }
}
