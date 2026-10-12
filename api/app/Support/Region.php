<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Where a point is, for the few things that read differently by country: price labels, the
 * timezone nudges run on, and whether Kakao replaces Google. Rough boxes (they take in some sea
 * and border land, harmless). Mirrored by the iOS `Region` and web `region.ts`; keep the boxes
 * and price labels in step.
 */
enum Region: string
{
    case Malaysia = 'MY';
    case Korea = 'KR';
    case UnitedStates = 'US';
    case Other = 'OTHER';

    /**
     * Peninsular Malaysia stops short of southern Thailand and Batam; Singapore, Toronto,
     * Ottawa–Montreal–Quebec and everything north of 49° (Vancouver) are cut out of their boxes.
     */
    public static function at(float $latitude, float $longitude): self
    {
        $in = fn (float $south, float $north, float $west, float $east): bool => $latitude >= $south && $latitude <= $north && $longitude >= $west && $longitude <= $east;

        return match (true) {
            $in(1.15, 1.47, 103.6, 104.1) => self::Other, // Singapore
            $in(1.2, 6.8, 99.5, 104.6), $in(0.8, 7.5, 109.5, 119.5) => self::Malaysia,
            $in(33.0, 38.7, 124.5, 131.0) => self::Korea,
            $in(43.4, 44.6, -80.6, -78.4), $in(45.0, 46.9, -76.5, -71.0) => self::Other, // Toronto, Ottawa–Montreal–Quebec
            $in(24.4, 49.0, -125.0, -66.9) => self::UnitedStates,
            default => self::Other,
        };
    }

    /** Zone for a point, or brain.timezone when there is none. */
    public static function timezoneAt(?float $latitude, ?float $longitude): string
    {
        return $latitude === null || $longitude === null
            ? (string) Config::get('brain.timezone', 'Asia/Kuala_Lumpur')
            : self::at($latitude, $longitude)->timezone($longitude);
    }

    /**
     * IANA zone for a point in this region. Malaysia uses the app's configured zone.
     * ponytail: US zone by longitude band (state lines ignored, e.g. west Kentucky reads as
     * Eastern) and elsewhere a whole-hour UTC offset from longitude (no DST, so London reads as
     * UTC in summer); swap for a tz lookup if nudges land an hour off for real users.
     */
    public function timezone(float $longitude): string
    {
        return match ($this) {
            self::Korea => 'Asia/Seoul',
            self::UnitedStates => match (true) {
                $longitude >= -87.5 => 'America/New_York',
                $longitude >= -101.0 => 'America/Chicago',
                $longitude >= -114.5 => 'America/Denver',
                default => 'America/Los_Angeles',
            },
            self::Other => sprintf('Etc/GMT%+d', -(int) round($longitude / 15)),
            default => (string) Config::get('brain.timezone', 'Asia/Kuala_Lumpur'),
        };
    }

    /** "≈ RM20/person" from Google's coarse price level — same words as the apps' PricePresentation. */
    public function approximateSpend(?int $priceLevel): ?string
    {
        $amounts = match ($this) {
            self::Malaysia => [1 => 'RM10', 2 => 'RM20', 3 => 'RM35+'],
            self::Korea => [1 => '₩10,000', 2 => '₩18,000', 3 => '₩30,000+'],
            self::UnitedStates => [1 => '$12', 2 => '$25', 3 => '$40+'],
            self::Other => [1 => 'budget', 2 => 'mid-range', 3 => 'pricey'],
        };
        $amount = $priceLevel === null ? null : ($amounts[$priceLevel] ?? null);

        return match (true) {
            $amount === null => null,
            $this === self::Other => ucfirst($amount),
            default => "≈ {$amount}/person",
        };
    }

    /** The Brain's lower-case price band ("under RM15"), levels 0–4. */
    public function priceBand(?int $priceLevel): ?string
    {
        $bands = match ($this) {
            self::Malaysia => ['under RM15', 'around RM15–30', 'around RM30–60', 'RM60 and up'],
            self::Korea => ['under ₩12,000', 'around ₩12,000–25,000', 'around ₩25,000–50,000', '₩50,000 and up'],
            self::UnitedStates => ['under $15', 'around $15–30', 'around $30–60', '$60 and up'],
            self::Other => ['a budget price', 'a mid-range price', 'a higher price', 'a high price'],
        };

        return match ($priceLevel) {
            0, 1 => $bands[0],
            2 => $bands[1],
            3 => $bands[2],
            4 => $bands[3],
            default => null,
        };
    }

    /** "500 m" / "1.2 km", or "0.3 mi" in the US — the apps' distance wording. */
    public function distance(float $meters): string
    {
        return match (true) {
            $this === self::UnitedStates => sprintf('%.1f mi', max(0.1, $meters / 1609.344)),
            $meters < 1000 => (int) (round($meters / 50) * 50).' m',
            default => sprintf('%.1f km', $meters / 1000),
        };
    }

    /** "2.5 km" / "1.6 mi" — a radius or average distance. */
    public function radius(float $km): string
    {
        return $this === self::UnitedStates ? sprintf('%.1f mi', $km * 0.621371) : sprintf('%.1f km', $km);
    }

    /** Where a Brain fact was made; old facts have no region and read as Malaysia, as they did. */
    public static function fromFact(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Malaysia;
    }

    /** ISO 4217 code menu prices are stored in. Other falls back to MYR, the column default. */
    public function currency(): string
    {
        return match ($this) {
            self::Korea => 'KRW',
            self::UnitedStates => 'USD',
            default => 'MYR',
        };
    }

    public function countryCode(): ?string
    {
        return $this === self::Other ? null : $this->value;
    }
}
