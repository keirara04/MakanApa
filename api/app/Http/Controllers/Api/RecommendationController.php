<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SoloRecommendationRequest;
use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;

class RecommendationController extends Controller
{
    public function __construct(
        private readonly PlacesService $placesService,
        private readonly RecommendationService $recommendationService,
    ) {}

    public function solo(SoloRecommendationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $restaurants = $this->placesService->nearbyRestaurants(
            $data['latitude'], $data['longitude'], $data['maxDistanceKm']
        );

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
            'budget_max' => $data['budgetMax'],
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
            'recommendation' => $result['pick'] ? $this->presentCandidate($result['pick']) : null,
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
            'recommendation' => $next ? $this->presentCandidate($next) : null,
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

    private function presentCandidate(array $candidate): array
    {
        $restaurant = $candidate['restaurant'];

        return [
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'headline' => $this->headlineFor($restaurant),
            'latitude' => $restaurant['latitude'],
            'longitude' => $restaurant['longitude'],
            'distanceKm' => $candidate['distanceKm'],
            'rating' => $restaurant['rating'],
            'priceLevel' => $restaurant['price_level'],
            'cuisines' => $restaurant['cuisines'],
            'openStatus' => $restaurant['open_status'],
        ];
    }

    private function headlineFor(array $restaurant): string
    {
        if (! empty($restaurant['signature_dish'])) {
            return strtoupper($restaurant['signature_dish']).'.';
        }

        return ! empty($restaurant['cuisines'][0]) ? strtoupper($restaurant['cuisines'][0]).'.' : 'MAKAN.';
    }
}
