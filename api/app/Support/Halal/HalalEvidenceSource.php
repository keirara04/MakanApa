<?php

namespace App\Support\Halal;

/** Where the evidence behind a verification came from — not who decided (see HalalDecisionMethod). */
enum HalalEvidenceSource: string
{
    case Heuristic = 'heuristic';
    case Community = 'community';
    case RestaurantOwner = 'restaurant_owner';
    case OfficialRegistry = 'official_registry';
    case AdminObservation = 'admin_observation';

    public function label(): string
    {
        return match ($this) {
            self::Heuristic => 'Automatic check',
            self::Community => 'Community evidence',
            self::RestaurantOwner => 'Restaurant owner',
            self::OfficialRegistry => 'Official registry',
            self::AdminObservation => 'MakanApa team',
        };
    }
}
