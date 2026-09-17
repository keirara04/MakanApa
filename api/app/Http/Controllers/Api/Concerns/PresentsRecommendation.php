<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\DecisionRecommendation;
use App\Models\RestaurantVibeVote;
use App\Services\Places\GooglePlacesProvider;
use App\Support\CommunityTag;
use App\Support\DiscoveryMode;
use App\Support\RecommendationHeadline;
use App\Support\Vibe;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Shared winner-presentation logic between RecommendationController (Decide) and
 * NearbyController (Pick one lah) — both end a decision the same way: one restaurant,
 * enriched with photos/reviews, shaped into the same wire format the iOS ResultView expects.
 */
trait PresentsRecommendation
{
    /**
     * Winner-only Google Places Details fetch (photos/reviews/closing time) — never called
     * for the full candidate list, only the one restaurant actually being shown. Transient:
     * nothing this returns is written to the database.
     */
    private function enrichWinner(array $restaurant): array
    {
        $empty = ['photos' => [], 'reviews' => [], 'placeGoogleMapsUrl' => null, 'closesAt' => null];

        if (($restaurant['provider'] ?? null) !== 'google' || empty($restaurant['provider_place_id'])) {
            return $empty;
        }

        try {
            $apiKey = Config::get('services.places.google_api_key');
            $raw = (new GooglePlacesProvider($apiKey))->fetchPresentationDetails($restaurant['provider_place_id']);

            return $this->normalizer->normalizePresentationDetails($raw);
        } catch (Throwable $e) {
            Log::warning('Presentation details fetch failed', ['error' => $e->getMessage()]);

            return $empty; // card still works, just without photo/reviews/closing time
        }
    }

    private function presentCandidate(array $candidate, array $enrichment): array
    {
        $restaurant = $candidate['restaurant'];

        return [
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'headline' => RecommendationHeadline::for($restaurant),
            'foodCategory' => $restaurant['food_category'] ?? null,
            'latitude' => $restaurant['latitude'],
            'longitude' => $restaurant['longitude'],
            'distanceKm' => $candidate['distanceKm'],
            'rating' => $restaurant['rating'],
            'priceLevel' => $restaurant['price_level'],
            'cuisines' => $restaurant['cuisines'],
            'openStatus' => $restaurant['open_status'],
            'photos' => array_map(fn (array $photo) => $this->presentPhoto($photo), $enrichment['photos']),
            'reviews' => $enrichment['reviews'],
            'placeGoogleMapsUrl' => $enrichment['placeGoogleMapsUrl'],
            'closesAt' => $enrichment['closesAt'],
            'communityTag' => $this->communityTagBadge($restaurant['id'])?->value,
        ];
    }

    /**
     * Presentation-only confidence gate — entirely separate from vibeRelevanceComponent's
     * scoring role. One tap ("Study" tagged once) shouldn't be enough to print a confident-
     * sounding "Popular for studying" badge; requires real evidence, both in absolute vote
     * count and as a real majority share of that restaurant's votes.
     */
    private function communityTagBadge(int $restaurantId): ?CommunityTag
    {
        $counts = RestaurantVibeVote::where('restaurant_id', $restaurantId)
            ->selectRaw('vibe, count(*) as votes')
            ->groupBy('vibe')
            ->orderByDesc('votes')
            ->get();

        $total = (int) $counts->sum('votes');
        if ($total === 0) {
            return null;
        }

        $top = $counts->first();
        $minVotes = (int) Config::get('recommendation.vibe_badge.min_votes', 5);
        $minShare = (float) Config::get('recommendation.vibe_badge.min_share', 0.4);

        if ($top->votes < $minVotes || ($top->votes / $total) < $minShare) {
            return null;
        }

        return $top->vibe;
    }

    /**
     * The extra preference keys RecommendationService::scoreBreakdown() reads when a request
     * opts into DiscoveryMode — kept out of scoreBreakdown() itself so it stays framework-
     * independent (no config()/DB calls), and out of each controller so mode/vibe/personal-fit
     * resolution can't drift between Decide and Nearby.
     *
     * @return array{discoveryMode: DiscoveryMode, vibe: ?Vibe, communityPrior: array, knownChains: array, installationHistory: ?array}
     */
    private function discoveryPreferenceExtras(?string $mode, ?string $vibeValue, ?string $installationId): array
    {
        return [
            'discoveryMode' => DiscoveryMode::fromRequest($mode),
            'vibe' => Vibe::fromRequest($vibeValue),
            'communityPrior' => Config::get('recommendation.community_prior', ['success_rate' => 0.5, 'weight' => 10]),
            'knownChains' => Config::get('recommendation.known_chains', []),
            'installationHistory' => $this->resolveInstallationHistory($installationId),
        ];
    }

    /**
     * Null (not an empty array) below the minimum accept count — scoreBreakdown() treats an
     * empty/missing installationHistory as "no personal signal yet," so personalFit is simply
     * absent from that candidate's active weights rather than computed as a weak/zero score.
     */
    private function resolveInstallationHistory(?string $installationId): ?array
    {
        if ($installationId === null) {
            return null;
        }

        $minAccepts = (int) Config::get('recommendation.personal_fit_min_accepts', 3);

        $accepted = DecisionRecommendation::query()
            ->whereNotNull('accepted_at')
            ->whereHas('decision', fn ($query) => $query->where('installation_id', $installationId))
            ->with('restaurant')
            ->latest('accepted_at')
            ->limit(20)
            ->get();

        if ($accepted->count() < $minAccepts) {
            return null;
        }

        return [
            'price_levels' => $accepted->pluck('restaurant.price_level')->filter()->values()->all(),
            'food_categories' => $accepted->pluck('restaurant.food_category')->filter()->values()->all(),
        ];
    }

    /**
     * Converts a photo's transient Google resource name into a signed, short-lived Laravel
     * URL — the raw name never reaches the client, and the signature stops the endpoint
     * being usable as an open proxy for arbitrary Google photo names.
     */
    private function presentPhoto(array $photo): array
    {
        return [
            'url' => URL::temporarySignedRoute('places.photo', now()->addMinutes(10), ['name' => $photo['name']]),
            'authorAttributions' => $photo['authorAttributions'],
            'googleMapsUrl' => $photo['googleMapsUrl'],
            'flagContentUrl' => $photo['flagContentUrl'],
        ];
    }
}
