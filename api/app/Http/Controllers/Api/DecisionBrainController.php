<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesDecisionToken;
use App\Http\Controllers\Api\Concerns\PresentsRecommendation;
use App\Http\Controllers\Controller;
use App\Models\Decision;
use App\Models\DecisionInteraction;
use App\Models\DecisionRecommendation;
use App\Models\Restaurant;
use App\Services\Brain\Counterfactual;
use App\Services\Brain\TasteEventRecorder;
use App\Services\Brain\TuneService;
use App\Services\Places\PlaceNormalizer;
use App\Services\RecommendationService;
use App\Support\TuneDirection;
use App\Support\WhyNotReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Makan Brain's per-decision actions: Tune, What-if (+ choose a what-if winner), why-not
 * feedback and passive interaction logging. All decision-token authorized like reroll/accept,
 * and all work off the pool persisted at decide time — no Places calls anywhere here.
 */
class DecisionBrainController extends Controller
{
    use AuthorizesDecisionToken, PresentsRecommendation;

    private const WHAT_IF_LABELS = [
        'distance' => "If distance didn't matter",
        'rating' => "If ratings didn't matter",
        'cheapEatsFit' => "If price didn't matter",
        'budget' => "If budget didn't matter",
        'community' => "If community picks didn't matter",
        'personalFit' => "If your Selera didn't matter",
        'novelty' => "If variety didn't matter",
        'relevance' => "If your craving didn't matter",
        'mood' => "If your mood didn't matter",
        'openCertainty' => "If opening hours didn't matter",
        'lateNightFit' => "If late hours didn't matter",
        'popularityBonus' => "If popularity didn't matter",
        'reviewVolumeBonus' => "If hidden-gem-ness didn't matter",
        'halalConfidence' => "If halal certainty didn't matter",
    ];

    public function __construct(
        private readonly TuneService $tunes,
        private readonly TasteEventRecorder $recorder,
        private readonly PlaceNormalizer $normalizer,
    ) {}

    public function tune(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);
        abort_unless($decision->isBrainDecision() && config('brain.features.tune'), 404);

        $data = $request->validate(['direction' => ['required', Rule::enum(TuneDirection::class)]]);
        $halalOnly = $decision->halal_only || (bool) $request->user()?->halal_preference;

        $result = $this->tunes->tune($decision, TuneDirection::from($data['direction']), $request->user(), $halalOnly);

        if ($result['row'] === null) {
            return response()->json(['recommendation' => null, ...$result['searchWider']]);
        }

        return response()->json([
            'recommendation' => $this->present($decision->fresh(), $result['row'], lead: $result['lead']),
            'canSearchWider' => false,
        ]);
    }

    public function whatIf(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);
        abort_unless($decision->isBrainDecision() && config('brain.features.what_if'), 404);

        $rows = $decision->recommendations()->orderBy('score_rank')->get()->filter(fn ($r) => $r->breakdown)->values();
        $shown = $rows->first(fn ($r) => $r->shown_at !== null && $r->rejected_at === null) ?? $rows->first(fn ($r) => $r->selected);
        if (! $shown) {
            return response()->json(['whatIf' => []]);
        }

        $pool = $rows->map(fn (DecisionRecommendation $row) => [
            'id' => $row->restaurant_id,
            'name' => $row->breakdown['facts']['name'] ?? null,
            'tier' => (int) ($row->breakdown['tier'] ?? 2),
            'components' => $row->breakdown['components'] ?? [],
            'weights' => $row->breakdown['weights'] ?? [],
        ])->all();
        $index = $rows->search(fn ($r) => $r->id === $shown->id);

        $entries = [];
        foreach (Counterfactual::flips($pool, $index, Counterfactual::lifts($pool, $index)) as $flip) {
            $entries[] = [
                'component' => $flip['component'],
                'label' => self::WHAT_IF_LABELS[$flip['component']] ?? "If {$flip['component']} didn't matter",
                'winner' => ['id' => $pool[$flip['winnerIndex']]['id'], 'name' => $pool[$flip['winnerIndex']]['name']],
            ];
            if (count($entries) === 3) {
                break;
            }
        }

        DecisionInteraction::query()->insertOrIgnore(['decision_id' => $decision->id, 'type' => 'what_if_opened', 'created_at' => now()]);

        return response()->json(['whatIf' => $entries]);
    }

    /** Swap to a what-if winner — it's already in the stored pool, so this is just a re-show. */
    public function choose(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);
        abort_unless($decision->isBrainDecision(), 404);
        $data = $request->validate(['restaurantId' => ['required', 'integer']]);

        $row = DB::transaction(function () use ($decision, $data) {
            $rows = $decision->recommendations()->lockForUpdate()->get();
            $target = $rows->first(fn ($r) => $r->restaurant_id === (int) $data['restaurantId'] && $r->rejected_at === null && $r->accepted_at === null);
            abort_if($target === null, 422, 'That option is no longer available.');

            $current = $rows->first(fn ($r) => $r->shown_at !== null && $r->rejected_at === null && $r->accepted_at === null);
            if ($current && $current->id !== $target->id) {
                $current->update(['rejected_at' => now(), 'reject_reason' => 'what_if']);
                Restaurant::whereKey($current->restaurant_id)->increment('rejected_count');
            }
            $target->update(['shown_at' => now()]);
            Restaurant::whereKey($target->restaurant_id)->increment('impressions_count');

            return $target;
        });

        return response()->json(['recommendation' => $this->present($decision, $row)]);
    }

    public function whyNot(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);

        $data = $request->validate([
            'reason' => ['required', Rule::enum(WhyNotReason::class)],
            'detail' => ['nullable', 'string', Rule::in(WhyNotReason::DETAILS)],
        ]);
        $reason = WhyNotReason::from($data['reason']);
        $detail = $reason === WhyNotReason::NotFeelingIt ? ($data['detail'] ?? null) : null;

        $rejected = $decision->recommendations()->whereNotNull('rejected_at')->latest('rejected_at')->first();
        abort_if($rejected === null, 422, 'Nothing was rerolled on this decision yet.');

        $this->recorder->whyNot($decision, $rejected, $request->user(), $reason, $detail);

        return response()->json(['recorded' => true]);
    }

    public function interaction(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($request, $decision);
        $data = $request->validate(['type' => ['required', Rule::in(DecisionInteraction::TYPES)]]);

        DecisionInteraction::query()->insertOrIgnore(['decision_id' => $decision->id, 'type' => $data['type'], 'created_at' => now()]);

        return response()->json(['recorded' => true]);
    }

    private function present(Decision $decision, DecisionRecommendation $row, ?string $lead = null): array
    {
        $row->loadMissing('restaurant.cuisines', 'restaurant.tags');
        $restaurant = $row->restaurant->toRecommendationArray();
        $candidate = [
            'restaurant' => $restaurant,
            'distanceKm' => (float) ($row->breakdown['facts']['distanceKm'] ?? RecommendationService::distanceKm(
                (float) $decision->latitude, (float) $decision->longitude, $restaurant['latitude'], $restaurant['longitude']
            )),
        ];

        return [
            ...$this->presentCandidate($candidate, $this->enrichWinner($restaurant)),
            ...$this->brainPayload($decision, $row, withTrace: false, lead: $lead),
        ];
    }
}
