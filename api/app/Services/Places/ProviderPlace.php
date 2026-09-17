<?php

namespace App\Services\Places;

/**
 * Raw place data from an external provider (Google's shape today), before
 * PlaceNormalizer converts it into MakanApa's own restaurant semantics.
 */
final class ProviderPlace
{
    public function __construct(
        public readonly string $providerPlaceId,
        public readonly string $name,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly array $types,
        public readonly ?float $rating,
        public readonly ?int $priceLevel,
        public readonly ?bool $openNow,
        public readonly ?int $userRatingCount = null,
    ) {}
}
