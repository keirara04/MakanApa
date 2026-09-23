<?php

namespace App\Services\Brain;

use App\Models\TasteEvent;
use App\Models\TasteProfile;
use App\Models\User;
use App\Services\Craving\CravingIntent;
use App\Support\Lens;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Builds the one DecisionBrainState a request uses. Hot-path cost: one taste_profiles row, one
 * `LIMIT 30` taste_events read (Moment Pulse), one latest-decision read (session) and a cache
 * read for weather — no network, no LLM.
 */
class BrainStateFactory
{
    public function __construct(
        private readonly ContextEngine $context,
        private readonly SessionResolver $sessions,
        private readonly TasteProfileBuilder $builder,
    ) {}

    public static function enabled(): bool
    {
        return (bool) Config::get('brain.enabled');
    }

    /**
     * @param  string[]  $moods
     * @param  string[]  $ignoreContext
     */
    public function make(
        ?User $user,
        ?string $installationId,
        ?float $lat,
        ?float $lon,
        bool $halalOnly,
        ?int $budgetMax,
        ?string $lens,
        array $ignoreContext,
        ?CravingIntent $craving,
        array $moods,
    ): DecisionBrainState {
        $now = Carbon::now();
        $owner = TasteOwner::resolve($user, $installationId);
        $lensEnum = Lens::fromRequest($lens);

        $profile = $owner && Config::get('brain.features.selera') ? $this->profileFor($owner, $user, $installationId) : null;
        $sessionId = $this->sessions->resolve($owner);

        $pulse = MomentPulse::none();
        if ($owner && Config::get('brain.features.pulse')) {
            $events = $owner->scope(TasteEvent::query())
                ->orderByDesc('id')
                ->limit((int) Config::get('brain.pulse.event_window', 30))
                ->get(['id', 'session_id', 'decision_recommendation_id', 'signal', 'dimension', 'dimension_key', 'value', 'scope', 'created_at'])
                ->map(fn ($e) => $e->getAttributes());
            $pulse = MomentPulse::fromEvents($events, $sessionId, $now);
        }

        $context = $this->context->resolve($lat, $lon, $halalOnly, $budgetMax !== null || $lensEnum !== null, $ignoreContext, $now);

        $extraWeights = ContextEngine::overlay($context);
        $disabled = [];
        if ($lensEnum) {
            $lensConfig = Config::get("brain.lenses.{$lensEnum->value}", []);
            foreach ($lensConfig['weights'] ?? [] as $key => $weight) {
                $extraWeights[$key] = ($extraWeights[$key] ?? 0) + $weight;
            }
            $disabled = $lensConfig['disable'] ?? [];
        }

        [$intentType, $cravingStrength] = match (true) {
            $craving !== null && trim($craving->raw) !== '' => ['craving', $craving->isRecognized() ? 0.9 : 0.5],
            $moods !== [] => ['mood', 0.3],
            default => ['anything', 0.0],
        };

        $signals = (int) ($profile?->signal_count ?? 0);
        $stage = DecisionBrainState::stageFor($signals);
        $fatigue = $pulse->sessionActions >= (int) Config::get('brain.fatigue_threshold', 5);

        $state = new DecisionBrainState(
            owner: $owner,
            taste: $profile,
            tasteMature: $signals >= (int) Config::get('brain.min_signals', 3),
            pulse: $pulse,
            context: $context,
            lens: $lensEnum,
            tunes: [],
            extraWeights: $extraWeights,
            disabledComponents: $disabled,
            baseExploration: 0.0,
            fatigueMode: $fatigue,
            ignoredContext: array_values($ignoreContext),
            sessionId: $sessionId,
            intentType: $intentType,
            cravingStrength: $cravingStrength,
            seleraStage: $stage,
            now: $now->toDateTimeImmutable(),
        );

        $base = ExplorationPolicy::baseNeed($cravingStrength, $stage, SeleraScorer::streakCategory($state) !== null, $pulse->noveltyDrive, $pulse->sessionActions);

        return new DecisionBrainState(
            $state->owner, $state->taste, $state->tasteMature, $state->pulse, $state->context, $state->lens,
            $state->tunes, $state->extraWeights, $state->disabledComponents, $base, $state->fatigueMode,
            $state->ignoredContext, $state->sessionId, $state->intentType, $state->cravingStrength,
            $state->seleraStage, $state->now,
        );
    }

    /**
     * The owner's profile. The first time a signed-in user shows up with an installation that
     * already learned things anonymously, those events are re-attributed and replayed into the
     * user's profile — so nothing learned before sign-in is lost.
     */
    private function profileFor(TasteOwner $owner, ?User $user, ?string $installationId): ?TasteProfile
    {
        $profile = TasteProfile::query()->where($owner->profileKey())->first();

        if ($profile === null && $user !== null && $installationId) {
            $anonymous = TasteProfile::query()->whereNull('user_id')->where('installation_id', $installationId)->exists();
            if ($anonymous) {
                DB::transaction(function () use ($user, $installationId) {
                    TasteEvent::query()->whereNull('user_id')->where('installation_id', $installationId)->update(['user_id' => $user->id]);
                    TasteProfile::query()->whereNull('user_id')->where('installation_id', $installationId)->delete();
                });
                $profile = $this->builder->rebuild($owner);
            }
        }

        return $profile;
    }
}
