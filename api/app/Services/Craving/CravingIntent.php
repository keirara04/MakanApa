<?php

namespace App\Services\Craving;

use App\Support\FoodConceptKind;
use App\Support\FoodTaxonomy;

/**
 * Structured result of resolving a free-text craving. Carried through retrieval (drives at
 * most one Google Text Search — see PlacesService) and scoring (drives relevance weighting —
 * see RecommendationService), so both stay in sync on what the user actually asked for.
 */
final class CravingIntent
{
    public function __construct(
        public readonly string $raw,
        public readonly ?string $concept,
        public readonly ?FoodConceptKind $kind,
        /** @var string[] */
        public readonly array $searchTerms,
        /** @var string[] */
        public readonly array $placeTypes,
        public readonly float $confidence,
        public readonly string $source,
    ) {}

    public static function fromTaxonomy(string $raw, array $resolved): self
    {
        return new self(
            raw: $raw,
            concept: $resolved['concept'],
            kind: $resolved['kind'],
            searchTerms: $resolved['searchTerms'],
            placeTypes: $resolved['placeTypes'],
            confidence: $resolved['confidence'],
            source: 'taxonomy',
        );
    }

    public static function fromAi(string $raw, string $concept, float $confidence): ?self
    {
        if (! FoodTaxonomy::exists($concept)) {
            return null;
        }

        $entry = FoodTaxonomy::CONCEPTS[$concept];

        return new self(
            raw: $raw,
            concept: $concept,
            kind: $entry['kind'],
            searchTerms: FoodTaxonomy::searchTermsFor($concept),
            placeTypes: $entry['placeTypes'],
            confidence: $confidence,
            source: 'ai',
        );
    }

    public static function none(string $raw): self
    {
        return new self(
            raw: $raw,
            concept: null,
            kind: null,
            searchTerms: [],
            placeTypes: [],
            confidence: 0.0,
            source: 'none',
        );
    }

    public function isRecognized(): bool
    {
        return $this->confidence >= 0.6 && $this->concept !== null;
    }

    /**
     * At most one Google Text Search call is allowed per craving (see PlacesService) — this is
     * the single term that call uses. Falls back to the raw text itself when nothing resolved,
     * so an unrecognized craving still gets one honest attempt rather than none at all.
     */
    public function primarySearchTerm(): ?string
    {
        if (! empty($this->searchTerms)) {
            return $this->searchTerms[0];
        }

        $trimmed = trim($this->raw);

        return $trimmed === '' ? null : $trimmed;
    }
}
