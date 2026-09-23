<?php

namespace App\Support\Halal;

enum CertificationAuthority: string
{
    case Jakim = 'jakim';
    case StateIslamicCouncil = 'state_islamic_council';
    case Muis = 'muis';
    case Bpjph = 'bpjph';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Jakim => 'JAKIM',
            self::StateIslamicCouncil => 'State Islamic council (JAIN/MAIN)',
            self::Muis => 'MUIS',
            self::Bpjph => 'BPJPH',
            self::Other => 'Other',
        };
    }

    /** Short name used inside public badges — only JAKIM is named, everything else is generic. */
    public function badgeName(): ?string
    {
        return $this === self::Jakim ? 'JAKIM' : null;
    }
}
