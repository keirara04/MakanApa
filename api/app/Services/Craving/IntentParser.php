<?php

namespace App\Services\Craving;

use App\Support\FoodConceptKind;

interface IntentParser
{
    /**
     * Resolve a normalized craving into structured intent. $localHint carries the taxonomy's
     * own (possibly weak) guess, if any, so an implementation can confirm/refine it instead of
     * resolving blind — and can fall back to it if its own resolution doesn't check out.
     *
     * Returning null means "couldn't parse" — never throw. A broken parser must degrade the
     * caller to the taxonomy/none fallback, never break the request.
     *
     * @param  array{concept: string, kind: FoodConceptKind, confidence: float, searchTerms: string[], placeTypes: string[]}|null  $localHint
     */
    public function parse(string $normalizedText, ?array $localHint): ?CravingIntent;
}
