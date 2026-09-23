<?php

namespace App\Services\Community;

use App\Models\DecisionRecommendation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * "Who in my community actually picked this" — the one scoping rule shared by the Community
 * trending feed and Makan Brain's community_picks reason, so the two can never disagree about
 * what counts as your community: registered users only, university / area by snapshot id, and
 * Public = a bounding box around the caller that excludes every affiliated decision.
 */
class CommunityPickStats
{
    /** Accepted recommendations in the caller's community since $since. Null = no scope possible (Public without a location). */
    public function acceptedQuery(User $user, CarbonInterface $since, ?float $lat, ?float $lon): ?Builder
    {
        $affiliation = $user->affiliation?->type;

        $query = DecisionRecommendation::query()
            ->join('decisions', 'decisions.id', '=', 'decision_recommendations.decision_id')
            ->whereNotNull('decision_recommendations.accepted_at')
            ->where('decision_recommendations.accepted_at', '>=', $since)
            ->whereNotNull('decisions.user_id');

        if ($affiliation === 'university') {
            return $query->where('decisions.university_id', $user->universityId());
        }
        if ($affiliation === 'area') {
            return $query->where('decisions.area_id', $user->areaId());
        }
        if ($lat === null || $lon === null) {
            return null;
        }

        $radiusKm = (float) Config::get('recommendation.community_feed.public_radius_km', 10);
        $latDelta = $radiusKm / 111.0;
        $lonDelta = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.01));

        // whereNull both — an area- or university-affiliated decision must never leak into
        // the Public radius feed just because it happens to be geographically close.
        return $query->whereNull('decisions.university_id')
            ->whereNull('decisions.area_id')
            ->join('restaurants', 'restaurants.id', '=', 'decision_recommendations.restaurant_id')
            ->whereBetween('restaurants.latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('restaurants.longitude', [$lon - $lonDelta, $lon + $lonDelta]);
    }

    /**
     * Distinct community pickers per restaurant over the last N days — one grouped query for the
     * whole candidate pool, cached per community.
     *
     * @param  int[]  $restaurantIds
     * @return array<int, int>
     */
    public function pickerCounts(?User $user, array $restaurantIds, ?float $lat, ?float $lon): array
    {
        if ($user === null || $restaurantIds === []) {
            return [];
        }

        $days = (int) Config::get('brain.community_evidence.window_days', 7);
        $ids = array_values(array_unique($restaurantIds));
        sort($ids);
        $scopeKey = $user->universityId() ? "u{$user->universityId()}" : ($user->areaId() ? "a{$user->areaId()}" : sprintf('p%.2f:%.2f', $lat, $lon));

        return Cache::remember(
            'brain:community-picks:'.$scopeKey.':'.md5(implode(',', $ids)),
            now()->addMinutes((int) Config::get('brain.community_evidence.cache_minutes', 10)),
            function () use ($user, $ids, $lat, $lon, $days) {
                $query = $this->acceptedQuery($user, now()->subDays($days), $lat, $lon);
                if ($query === null) {
                    return [];
                }

                return $query
                    ->whereIn('decision_recommendations.restaurant_id', $ids)
                    ->selectRaw('decision_recommendations.restaurant_id as restaurant_id, count(distinct decisions.user_id) as pickers')
                    ->groupBy('decision_recommendations.restaurant_id')
                    ->pluck('pickers', 'restaurant_id')
                    ->map(fn ($n) => (int) $n)
                    ->all();
            },
        );
    }

    /** "KU" / "KL" / null (Public) — how the community is named in copy. */
    public static function label(?User $user): ?string
    {
        return $user?->universityShortName() ?? $user?->areaShortName();
    }
}
