<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SoloRecommendationRequest;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\RecommendationHeadline;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly PlacesService $placesService,
        private readonly RecommendationService $recommendationService,
        private readonly PlaceNormalizer $normalizer,
    ) {}

    public function solo(SoloRecommendationRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $restaurants = $this->placesService->nearbyRestaurants(
                $data['latitude'], $data['longitude'], $data['maxDistanceKm']
            );
        } catch (RequestException $e) {
            Log::error('Places provider request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not reach the places provider. Try again in a bit.'], 502);
        } catch (Throwable $e) {
            Log::error('Places lookup failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not find nearby places right now.'], 500);
        }

        $preference = [
            'moodTags' => $data['moods'] ?? [],
            'cuisines' => [],
            'budgetMax' => $data['budgetMax'] ?? null,
            'maxDistanceKm' => $data['maxDistanceKm'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ];

        $result = $this->recommendationService->recommend($restaurants, $preference);

        $decision = Decision::create([
            'mode' => 'solo',
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'budget_max' => $data['budgetMax'] ?? null,
            'max_distance' => $data['maxDistanceKm'],
            'selected_restaurant_id' => $result['pick']['restaurant']['id'] ?? null,
        ]);

        foreach ($result['candidates'] as $rank => $candidate) {
            DecisionRecommendation::create([
                'decision_id' => $decision->id,
                'restaurant_id' => $candidate['restaurant']['id'],
                'rank' => $rank + 1,
                'score' => $candidate['score'],
                'shown_at' => $result['pick'] && $candidate['restaurant']['id'] === $result['pick']['restaurant']['id']
                    ? now()
                    : null,
            ]);
        }

        return response()->json([
            'decisionId' => $decision->id,
            'algorithmVersion' => 'v1',
            'recommendation' => $result['pick']
                ? $this->presentCandidate($result['pick'], $this->enrichWinner($result['pick']['restaurant']))
                : null,
        ]);
    }

    public function reroll(Decision $decision): JsonResponse
    {
        $rows = $decision->recommendations()->with('restaurant.cuisines', 'restaurant.tags')->get();

        $candidates = $rows->map(function (DecisionRecommendation $row) use ($decision) {
            $restaurantData = $row->restaurant->toRecommendationArray();

            return [
                'restaurant' => $restaurantData,
                'score' => (float) $row->score,
                'distanceKm' => RecommendationService::distanceKm(
                    (float) $decision->latitude, (float) $decision->longitude,
                    $restaurantData['latitude'], $restaurantData['longitude']
                ),
                '_row' => $row,
            ];
        })->all();

        $currentRow = $rows->first(fn (DecisionRecommendation $row) => $row->shown_at !== null && $row->rejected_at === null && $row->accepted_at === null);
        $currentCandidate = $currentRow
            ? collect($candidates)->first(fn ($c) => $c['_row']->id === $currentRow->id)
            : null;

        $next = $this->recommendationService->pick($candidates, $currentCandidate);

        if ($currentRow) {
            $currentRow->update(['rejected_at' => now()]);
        }

        if ($next) {
            $next['_row']->update(['shown_at' => now()]);
        }

        return response()->json([
            'recommendation' => $next
                ? $this->presentCandidate($next, $this->enrichWinner($next['restaurant']))
                : null,
        ]);
    }

    public function accept(Decision $decision): JsonResponse
    {
        $current = $decision->recommendations()
            ->whereNotNull('shown_at')
            ->whereNull('rejected_at')
            ->whereNull('accepted_at')
            ->first();

        if ($current) {
            $current->update(['accepted_at' => now()]);
        }

        return response()->json(['accepted' => (bool) $current]);
    }

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
