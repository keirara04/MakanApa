<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesDecisionToken;
use App\Http\Controllers\Api\Concerns\PresentsRecommendation;
use App\Http\Controllers\Controller;
use App\Http\Requests\SoloRecommendationRequest;
use App\Models\Decision;
use App\Models\DecisionPreference;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\RestaurantVibeVote;
use App\Services\Brain\BrainStateFactory;
use App\Services\Brain\ExplorationPolicy;
use App\Services\Brain\MakanBrain;
use App\Services\Brain\TasteEventRecorder;
use App\Services\Craving\CravingIntent;
use App\Services\Craving\CravingResolver;
use App\Services\Places\PlaceNormalizer;
use App\Services\PlacesService;
use App\Services\RecommendationService;
use App\Support\CommunityTag;
use App\Support\DiscoveryMode;
use App\Support\Halal\HalalStatus;
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
    use AuthorizesDecisionToken, PresentsRecommendation;

    public function __construct(
        private readonly PlacesService $placesService,
        private readonly RecommendationService $recommendationService,
        private readonly PlaceNormalizer $normalizer,
        private readonly CravingResolver $cravingResolver,
        private readonly BrainStateFactory $brainStates,
        private readonly MakanBrain $brain,
        private readonly TasteEventRecorder $recorder,
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
        $halalOnly = $this->resolveHalalOnly($request, $data);

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
            'halalOnly' => $halalOnly,
        ], $this->discoveryPreferenceExtras($data['mode'] ?? null, $data['vibe'] ?? null, $data['installationId'] ?? null));

        $clientToken = Str::random(40);
        $attributes = [
            'user_id' => $request->user()?->id,
            'university_id' => $request->user()?->universityId(),
            'area_id' => $request->user()?->areaId(),
            'mode' => 'solo',
            'client_token' => $clientToken,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'budget_max' => $data['budgetMax'] ?? null,
            'max_distance' => $data['maxDistanceKm'],
            'discovery_mode' => $mode->value,
            'vibe' => $vibe?->value,
            'installation_id' => $data['installationId'] ?? null,
            'halal_only' => $halalOnly,
        ];

        if (BrainStateFactory::enabled()) {
            return $this->soloWithBrain($request, $data, $restaurants, $preference, $attributes, $cravingIntent);
        }

        $result = $this->recommendationService->recommend($restaurants, $preference);

        $decision = Decision::create([
            ...$attributes,
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

    /**
     * Makan Brain v2: Selera Memory + Moment Pulse + Context, diversity, adaptive exploration,
     * and a persisted Decision Trace. Same response shape as v1 plus optional brain keys.
     */
    private function soloWithBrain(SoloRecommendationRequest $request, array $data, array $restaurants, array $preference, array $attributes, ?CravingIntent $cravingIntent): JsonResponse
    {
        $brain = $this->brainStates->make(
            $request->user(), $data['installationId'] ?? null, (float) $data['latitude'], (float) $data['longitude'],
            $preference['halalOnly'], $data['budgetMax'] ?? null, $data['lens'] ?? null, $data['ignoreContext'] ?? [],
            $cravingIntent, $data['moods'] ?? [],
        );
        $preference['brain'] = $brain;

        $result = $this->brain->decide($restaurants, $preference, $attributes, $request->user());
        $decision = $result['decision'];

        foreach ($data['moods'] ?? [] as $mood) {
            DecisionPreference::create(['decision_id' => $decision->id, 'preference_type' => 'mood', 'value' => $mood]);
        }

        $response = [
            'decisionId' => $decision->id,
            'clientToken' => $attributes['client_token'],
            'algorithmVersion' => $decision->algorithm_version,
            'recommendation' => $result['winner'] ? [
                ...$this->presentCandidate($result['winner'], $this->enrichWinner($result['winner']['restaurant'])),
                ...$this->brainPayload($decision, $result['winnerRow'], withTrace: true),
            ] : null,
            'craving' => $result['craving'],
        ];

        if (config('recommendation.debug')) {
            $response['debug'] = [
                'candidateCount' => $this->placesService->lastCandidateCounts(),
                'funnel' => $result['funnel'],
                'explorationLevel' => $decision->exploration_level,
                'weights' => $decision->weight_snapshot,
                'context' => $decision->context_snapshot,
                'pickScoreBreakdown' => $result['winnerRow']?->breakdown,
                'reasonFacts' => $result['winnerRow']?->reason_facts,
            ];
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
        // Re-applied at reroll time against each restaurant's CURRENT status: the stored pool may
        // predate the user switching halal-only on, or a restaurant being verified non-halal
        // mid-session — neither may leak a non-halal place back out.
        $halalOnly = $decision->halal_only || (bool) $request->user()?->halal_preference;

        if ($decision->isBrainDecision()) {
            return $this->rerollWithBrain($request, $decision, $halalOnly);
        }

        $next = DB::transaction(function () use ($decision, $halalOnly) {
            $rows = $decision->recommendations()->with('restaurant.cuisines', 'restaurant.tags')->lockForUpdate()->get();

            // Excludes rows already rejected by an earlier reroll — pick()'s own exclusion only
            // covers the single "current" candidate, so without this filter a restaurant
            // rejected two rerolls ago stays eligible and can resurface once the pool thins out.
            $eligibleRows = $rows->reject(fn (DecisionRecommendation $row) => $row->rejected_at !== null
                || ($halalOnly && $row->restaurant->effectiveHalalStatus() === HalalStatus::NonHalal));

            $candidates = $eligibleRows->map(function (DecisionRecommendation $row) use ($decision) {
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

    /**
     * v2 reroll: next pick from the stored pool (softmax at the decision's exploration level, or
     * the safest option once decision fatigue kicks in), stored reason facts plus a "not feeling
     * X?" acknowledgement, and a weak Moment Pulse signal against what was rejected.
     */
    private function rerollWithBrain(Request $request, Decision $decision, bool $halalOnly): JsonResponse
    {
        $fatigue = $this->brain->sessionActions($decision) + 1 >= (int) config('brain.fatigue_threshold', 5);

        [$next, $current] = DB::transaction(function () use ($decision, $halalOnly, $fatigue) {
            $rows = $decision->recommendations()->with('restaurant.cuisines', 'restaurant.tags')->orderBy('score_rank')->lockForUpdate()->get();
            $current = $rows->first(fn (DecisionRecommendation $row) => $row->shown_at !== null && $row->rejected_at === null && $row->accepted_at === null);

            $eligible = $rows->reject(fn (DecisionRecommendation $row) => $row->rejected_at !== null
                || $row->id === $current?->id
                || ($halalOnly && $row->restaurant->effectiveHalalStatus() === HalalStatus::NonHalal))->values();

            $pool = $eligible->map(fn (DecisionRecommendation $row) => [
                'restaurant' => $row->restaurant->toRecommendationArray(),
                'score' => (float) $row->score,
                'relevanceTier' => (int) ($row->breakdown['tier'] ?? 2),
            ])->all();

            $pick = ExplorationPolicy::pick($pool, (float) ($decision->exploration_level ?? 0), $fatigue);
            $next = $pick['index'] !== null ? $eligible[$pick['index']] : null;

            if ($current) {
                $current->update(['rejected_at' => now()]);
                Restaurant::whereKey($current->restaurant_id)->increment('rejected_count');
            }
            if ($next) {
                $next->update(['shown_at' => now()]);
                Restaurant::whereKey($next->restaurant_id)->increment('impressions_count');
            }
            if ($fatigue && ! $decision->fatigue_mode) {
                $decision->update(['fatigue_mode' => true]);
            }

            return [$next, $current];
        });

        if ($current) {
            $this->recorder->reroll($decision, $current, $request->user());
        }

        if (! $next) {
            return response()->json(['recommendation' => null]);
        }

        $candidate = [
            'restaurant' => $next->restaurant->toRecommendationArray(),
            'distanceKm' => (float) ($next->breakdown['facts']['distanceKm'] ?? RecommendationService::distanceKm(
                (float) $decision->latitude, (float) $decision->longitude, (float) $next->restaurant->latitude, (float) $next->restaurant->longitude
            )),
        ];

        return response()->json([
            'recommendation' => [
                ...$this->presentCandidate($candidate, $this->enrichWinner($candidate['restaurant'])),
                ...$this->brainPayload(
                    $decision, $next, withTrace: false,
                    rejected: ['category' => $current?->breakdown['facts']['category'] ?? null],
                    lead: $fatigue ? 'Okay lah, enough choosing 😭 — this is the safest bet' : null,
                ),
                'fatigue' => $fatigue,
            ],
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

        if ($accepted && BrainStateFactory::enabled()) {
            $this->recorder->accept($decision, $accepted, $request->user());
        }

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
            'university_id' => $request->user()?->universityId(),
            'area_id' => $request->user()?->areaId(),
            'vibe' => $data['vibe'],
        ]);

        if (BrainStateFactory::enabled()) {
            $this->recorder->vibeTag($decision, $target, $request->user(), $data['vibe'] instanceof CommunityTag ? $data['vibe']->value : (string) $data['vibe']);
        }

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
}
