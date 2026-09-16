<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\SoloRecommendationRequest;
use App\Models\Decision;
use App\Models\DecisionPreference;
use App\Models\DecisionRecommendation;
use App\Services\Places\PlaceNormalizer;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RecommendationController extends Controller
{
    use PresentsRecommendation;

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

        $clientToken = Str::random(40);

        $decision = Decision::create([
            'mode' => 'solo',
            'client_token' => $clientToken,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'budget_max' => $data['budgetMax'] ?? null,
            'max_distance' => $data['maxDistanceKm'],
            'selected_restaurant_id' => $result['pick']['restaurant']['id'] ?? null,
        ]);

        foreach ($data['moods'] ?? [] as $mood) {
            DecisionPreference::create([
                'decision_id' => $decision->id,
                'preference_type' => 'mood',
                'value' => $mood,
            ]);
        }

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
            'clientToken' => $clientToken,
            'algorithmVersion' => 'v1',
            'recommendation' => $result['pick']
                ? $this->presentCandidate($result['pick'], $this->enrichWinner($result['pick']['restaurant']))
                : null,
        ]);
    }

    public function reroll(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);

        // lockForUpdate serializes concurrent reroll calls for the same decision — without it,
        // two near-simultaneous requests can both read the same "current" row, each pick a
        // different "next" row, and both mark their own pick shown_at, leaving two rows
        // simultaneously "current" (one permanently orphaned). The Google enrichment call is
        // deliberately kept outside the transaction so a slow network call doesn't hold the lock.
        $next = DB::transaction(function () use ($decision) {
            $rows = $decision->recommendations()->with('restaurant.cuisines', 'restaurant.tags')->lockForUpdate()->get();

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

            return $next;
        });

        return response()->json([
            'recommendation' => $next
                ? $this->presentCandidate($next, $this->enrichWinner($next['restaurant']))
                : null,
        ]);
    }

    public function accept(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);

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
     * The app has no login, so a decision's sequential integer ID is the only handle a client
     * has — without this check, anyone can enumerate IDs and reroll/accept someone else's
     * in-progress decision. `client_token` is an opaque secret handed back once, in solo()'s
     * response, and must be echoed on every subsequent call for that decision.
     */
    private function authorizeDecision(Request $request, Decision $decision): void
    {
        $token = $request->header('X-Decision-Token');
        abort_unless(
            $decision->client_token !== null && $token !== null && hash_equals($decision->client_token, $token),
            403
        );
    }
}
