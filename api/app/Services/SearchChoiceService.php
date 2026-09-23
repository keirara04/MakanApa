<?php

namespace App\Services;

use App\Models\Decision;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Brain\BrainStateFactory;
use App\Services\Brain\TasteEventRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * "Makan sini" from search — the user looked a place up and deliberately picked it. Recorded as
 * a Decision (mode=search) with one accepted recommendation, so the choice flows into everything
 * an accepted pick already feeds: Recent (client side), community trending, restaurant
 * accept/impression counts and — with Makan Brain on — Selera, as a `search_choose` signal that
 * outranks a passive accept. Every side effect lives here, in one transaction.
 *
 * Idempotent on the client's `clientChoiceId`: a retried request returns the original decision
 * instead of writing a second accepted one.
 */
class SearchChoiceService
{
    public function __construct(private readonly TasteEventRecorder $recorder) {}

    /**
     * @param  array{query?: ?string, radiusKm?: ?float, source?: ?string, latitude?: ?float, longitude?: ?float, installationId?: ?string}  $context
     * @return array{decision: Decision, created: bool}
     */
    public function choose(?User $user, Restaurant $restaurant, string $clientChoiceId, array $context): array
    {
        if ($existing = Decision::where('client_choice_id', $clientChoiceId)->first()) {
            return $this->replay($existing, $user, $restaurant);
        }

        $latitude = $context['latitude'] ?? (float) $restaurant->latitude;
        $longitude = $context['longitude'] ?? (float) $restaurant->longitude;
        $distanceKm = isset($context['latitude'], $context['longitude'])
            ? round(RecommendationService::distanceKm($latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude), 3)
            : null;

        try {
            [$decision, $row] = DB::transaction(function () use ($user, $restaurant, $clientChoiceId, $context, $latitude, $longitude, $distanceKm) {
                $decision = Decision::create([
                    'user_id' => $user?->id,
                    'university_id' => $user?->universityId(),
                    'area_id' => $user?->areaId(),
                    'mode' => 'search',
                    'client_token' => Str::random(40),
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'max_distance' => $context['radiusKm'] ?? null,
                    'installation_id' => $context['installationId'] ?? null,
                    'halal_only' => (bool) $user?->halal_preference,
                    'selected_restaurant_id' => $restaurant->id,
                    'client_choice_id' => $clientChoiceId,
                    'search_context' => array_filter([
                        'query' => $context['query'] ?? null,
                        'radiusKm' => $context['radiusKm'] ?? null,
                        'source' => $context['source'] ?? null,
                    ], fn ($value) => $value !== null),
                ]);

                $now = now();
                $row = DecisionRecommendation::create([
                    'decision_id' => $decision->id,
                    'restaurant_id' => $restaurant->id,
                    'rank' => 1,
                    'score_rank' => 1,
                    'score' => 0,
                    'selected' => true,
                    'shown_at' => $now,
                    'accepted_at' => $now,
                    'breakdown' => ['facts' => array_filter(['distanceKm' => $distanceKm, 'category' => $restaurant->food_category], fn ($value) => $value !== null)],
                ]);

                // Both counters: an accept with no impression would push the community success
                // rate above 1 for search-heavy places.
                Restaurant::whereKey($restaurant->id)->update([
                    'impressions_count' => DB::raw('impressions_count + 1'),
                    'accepted_count' => DB::raw('accepted_count + 1'),
                ]);

                return [$decision, $row];
            });
        } catch (UniqueConstraintViolationException) {
            // Two concurrent retries of the same choice — the other one won; return its decision.
            return $this->replay(Decision::where('client_choice_id', $clientChoiceId)->firstOrFail(), $user, $restaurant);
        }

        if (BrainStateFactory::enabled()) {
            $this->recorder->searchChoose($decision, $row, $user);
        }

        return ['decision' => $decision, 'created' => true];
    }

    /** @return array{decision: Decision, created: bool} */
    private function replay(Decision $existing, ?User $user, Restaurant $restaurant): array
    {
        // A reused key for a different person or place is a client bug, never a silent success.
        if ($existing->user_id !== $user?->id || $existing->selected_restaurant_id !== $restaurant->id) {
            throw new ConflictHttpException('This choice id was already used for a different choice.');
        }

        return ['decision' => $existing, 'created' => false];
    }
}
