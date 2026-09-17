<?php

namespace App\Support;

/**
 * How a Nearby/Solo request wants candidates retrieved and ranked, beyond plain mood/craving
 * matching. `Normal` is the default — no extra retrieval types, just the small always-on
 * `community` overlay (see ScoreWeights::discoveryOverlay()). Composable with `Vibe`, which is
 * a separate, narrower taxonomy (see Vibe's own doc comment).
 */
enum DiscoveryMode: string
{
    case Normal = 'normal';
    case Popular = 'popular';
    case LowKey = 'low_key';
    case Cafe = 'cafe';
    case CheapEats = 'cheap_eats';
    case LateNight = 'late_night';

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Normal;
    }
}
