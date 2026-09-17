<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PresentsRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\SoloRecommendationRequest;
use App\Models\Decision;
use App\Models\DecisionPreference;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\RestaurantVibeVote;
use App\Services\Craving\CravingIntent;
use App\Services\Craving\CravingResolver;
use App\Services\Places\PlaceNormalizer;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\CommunityTag;
use App\Support\DiscoveryMode;
use App\Support\Vibe;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class RecommendationController extends Controller
{
    use PresentsRecommendation;

    public function __construct(
        private readonly PlacesService $placesService,
        private readonly RecommendationService $recommendationService,
        private readonly PlaceNormalizer $normalizer,
        private readonly CravingResolver $cravingResolver,
    ) {}

    public function solo(SoloRecommendationRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Resolved once, up front — both retrieval (drives at most one Google Text Search) and
        // scoring (drives relevance weighting) read the same CravingIntent, so they can't
        // disagree about what the user asked for.
        $cravingIntent = ! empty($data['craving']) ? $this->cravingResolver->resolve($data['craving']) : null;

        $mode = DiscoveryMode::fromRequest($data['mode'] ?? null);
        $vibe = Vibe::fromRequest($data['vibe'] ?? null);

        try {
            $restaurants = $this->placesService->nearbyRestaurants(
                $data['latitude'], $data['longitude'], $data['maxDistanceKm'], $cravingIntent, $mode, $vibe
            );
        } catch (RequestException $e) {
            Log::error('Places provider request failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not reach the places provider. Try again in a bit.'], 502);
        } catch (Throwable $e) {
            Log::error('Places lookup failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not find nearby places right now.'], 500);
        }

        $preference = array_merge([
            'moodTags' => $data['moods'] ?? [],
            'cuisines' => [],
            'cravingIntent' => $cravingIntent,
            'budgetMax' => $data['budgetMax'] ?? null,
            'maxDistanceKm' => $data['maxDistanceKm'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
        ], $this->discoveryPreferenceExtras($data['mode'] ?? null, $data['vibe'] ?? null, $data['installationId'] ?? null));

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
            'discovery_mode' => $mode->value,
            'vibe' => $vibe?->value,
            'installation_id' => $data['installationId'] ?? null,
        ]);

        foreach ($data['moods'] ?? [] as $mood) {
            DecisionPreference::create([
                'decision_id' => $decision->id,
                'preference_type' => 'mood',
                'value' => $mood,
            ]);
        }

        foreach ($result['candidates'] as $rank => $candidate) {
            $isWinner = $result['pick'] && $candidate['restaurant']['id'] === $result['pick']['restaurant']['id'];
            DecisionRecommendation::create([
                'decision_id' => $decision->id,
                'restaurant_id' => $candidate['restaurant']['id'],
                'rank' => $rank + 1,
                'score' => $candidate['score'],
                'shown_at' => $isWinner ? now() : null,
            ]);
        }

        if ($result['pick']) {
            Restaurant::whereKey($result['pick']['restaurant']['id'])->increment('impressions_count');
        }

        $response = [
            'decisionId' => $decision->id,
            'clientToken' => $clientToken,
            'algorithmVersion' => 'v1',
            'recommendation' => $result['pick']
                ? $this->presentCandidate($result['pick'], $this->enrichWinner($result['pick']['restaurant']))
                : null,
            'craving' => $result['craving'],
        ];

        if (config('recommendation.debug')) {
            $response['debug'] = $this->debugPayload($cravingIntent, $result, $preference);
        }

        return response()->json($response);
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
                Restaurant::whereKey($currentRow->restaurant_id)->increment('rejected_count');
            }

            if ($next) {
                $next['_row']->update(['shown_at' => now()]);
                Restaurant::whereKey($next['restaurant']['id'])->increment('impressions_count');
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

        // lockForUpdate + a re-check inside the transaction makes this idempotent — a retried
        // accept call (network timeout, client retry) finds accepted_at already non-null and
        // does nothing a second time, so accepted_count can't drift from decision_recommendations.
        $accepted = DB::transaction(function () use ($decision) {
            $current = $decision->recommendations()
                ->whereNotNull('shown_at')
                ->whereNull('rejected_at')
                ->whereNull('accepted_at')
                ->lockForUpdate()
                ->first();

            if ($current) {
                $current->update(['accepted_at' => now()]);
                Restaurant::whereKey($current->restaurant_id)->increment('accepted_count');
            }

            return $current;
        });

        return response()->json(['accepted' => (bool) $accepted]);
    }

    /**
     * Event-sourced, not a JSON counter — five vibes today will likely grow, and rows stay
     * auditable/re-aggregatable where a JSON blob wouldn't. Decision-token-authorized like
     * accept/reroll, and only for the restaurant this decision actually accepted (or the current
     * shown candidate, if the client fires this before/without an explicit accept).
     */
    public function vibeTag(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);

        $data = $request->validate([
            'vibe' => ['required', Rule::enum(CommunityTag::class)],
        ]);

        $target = $decision->recommendations()
            ->whereNotNull('shown_at')
            ->latest('shown_at')
            ->first();

        if (! $target) {
            return response()->json(['message' => 'No shown recommendation to tag for this decision.'], 422);
        }

        RestaurantVibeVote::create([
            'restaurant_id' => $target->restaurant_id,
            'decision_id' => $decision->id,
            'vibe' => $data['vibe'],
        ]);

        return response()->json(['tagged' => true]);
    }

    /**
     * Dev/staging only (RECOMMENDATION_DEBUG) — answers "why did this win" without guessing
     * from logs. Reuses RecommendationService::scoreBreakdown(), the same computation that
     * already ran for scoring, so this can never become a second, divergent implementation.
     */
    private function debugPayload(?CravingIntent $cravingIntent, array $result, array $preference): array
    {
        $debug = [
            'craving' => $cravingIntent === null ? null : [
                'normalized' => CravingResolver::normalize($cravingIntent->raw),
                'source' => $cravingIntent->source,
                'resolvedAs' => $cravingIntent->concept,
                'confidence' => $cravingIntent->confidence,
                'textSearchQuery' => $cravingIntent->primarySearchTerm(),
            ],
            'candidateCount' => $this->placesService->lastCandidateCounts(),
        ];

        if ($result['pick']) {
            $breakdown = $this->recommendationService->scoreBreakdown(
                $result['pick']['restaurant'], $preference, $result['pick']['distanceKm']
            );
            $debug['pickScoreBreakdown'] = array_merge($breakdown['components'], [
                'final' => $breakdown['final'],
                'relevanceTier' => $breakdown['relevanceTier'],
            ]);
        }

        return $debug;
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
