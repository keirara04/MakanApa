<?php

namespace App\Services\Brain;

use App\Models\TasteProfile;
use App\Support\Lens;

/**
 * Everything Makan Brain knows about one request, resolved exactly once by BrainStateFactory
 * and then only read — by RecommendationService (scoring), ExplorationPolicy (sampling),
 * ReasonComposer (explanations) and DecisionTraceWriter (fingerprint). Framework-free data,
 * so RecommendationService stays free of DB/config calls.
 */
final readonly class DecisionBrainState
{
    /**
     * @param  array<string, float>  $extraWeights  context·confidence + lens + tune weight additions, per component
     * @param  string[]  $disabledComponents  components a lens switched off (e.g. treat_myself → cheapEatsFit)
     * @param  string[]  $ignoredContext
     * @param  string[]  $tunes
     */
    public function __construct(
        public ?TasteOwner $owner,
        public ?TasteProfile $taste,
        public bool $tasteMature,
        public MomentPulse $pulse,
        public ContextSnapshot $context,
        public ?Lens $lens,
        public array $tunes,
        public array $extraWeights,
        public array $disabledComponents,
        public float $baseExploration,
        public bool $fatigueMode,
        public array $ignoredContext,
        public string $sessionId,
        public string $intentType,
        public float $cravingStrength,
        public string $seleraStage,
        public \DateTimeImmutable $now,
    ) {}

    public function memory(): array
    {
        return $this->taste?->memory ?? TasteMemory::empty()['memory'];
    }

    /** Muted traits and "not really" corrections are both treated as neutral. */
    public function isSilenced(string $traitKey): bool
    {
        return in_array($traitKey, $this->taste?->muted ?? [], true)
            || (($this->taste?->corrections[$traitKey] ?? null) === 'not_really');
    }

    public function hasPersonalSignal(): bool
    {
        return $this->tasteMature || ! $this->pulse->isEmpty();
    }

    public function withTunes(array $tunes, array $extraWeights): self
    {
        return new self(
            $this->owner, $this->taste, $this->tasteMature, $this->pulse, $this->context, $this->lens,
            $tunes, $extraWeights, $this->disabledComponents, $this->baseExploration, $this->fatigueMode,
            $this->ignoredContext, $this->sessionId, $this->intentType, $this->cravingStrength,
            $this->seleraStage, $this->now,
        );
    }

    public static function stageFor(int $signals): string
    {
        return match (true) {
            $signals >= 30 => 'strong',
            $signals >= 10 => 'knowing',
            $signals >= 3 => 'learning',
            default => 'starting',
        };
    }
}
