<?php

namespace App\Services\Nudges;

use Carbon\CarbonImmutable;

/**
 * Deterministic nudge copy — a few variants per situation, picked by user + day so nobody gets
 * the same line every time and the same nudge always renders the same way. No LLM, and never
 * any halal wording (that belongs to HalalPresenter, on screens with room to be precise).
 */
final class NudgeCopyCatalog
{
    /** @var array<string, array<int, array{0: string, 1: string}>> key => [title, body] variants */
    private const VARIANTS = [
        'normal_lunch' => [
            ['Lunch dah? 🍛', '{name} — {distance} away{closes}.'],
            ['Perut dah bunyi? 🍛', '{name} is {distance} away{closes}. Jom?'],
            ['Lunch sorted 👌', 'How about {name}? {distance} away{closes}.'],
        ],
        'normal_dinner' => [
            ['Dinner apa malam ni? 🍽️', '{name} — {distance} away{closes}.'],
            ['Makan malam jom 🌙', '{name} is {distance} away{closes}.'],
        ],
        'rain_lunch' => [
            ['Hujan ni 🌧️', '{name} is just {distance} away{closes} — tak basah sangat.'],
            ['Rainy lunch ☔', 'Stay dry: {name}, {distance} away{closes}.'],
        ],
        'rain_dinner' => [
            ['Hujan ni 🌧️', '{name} is just {distance} away{closes} — dekat je.'],
            ['Rainy dinner ☔', 'Stay dry: {name}, {distance} away{closes}.'],
        ],
        'friday_lunch' => [
            ['Lepas Jumaat, makan? 🍛', '{name} — {distance} away{closes}.'],
            ['Jumaat lunch 🍛', '{name} is {distance} away{closes}.'],
        ],
        'ramadan_iftar' => [
            ['Buka puasa kat mana? 🌙', '{name} — {distance} away{closes}.'],
            ['Iftar sorted? 🌙', 'How about {name}, {distance} away{closes}?'],
        ],
        'generic_lunch' => [
            ['Lunch time! 🍛', 'Tap for a pick near you.'],
            ['Lunch dah?', "Can't decide? MakanApa picks in seconds."],
        ],
        'generic_dinner' => [
            ['Dinner time! 🍽️', 'Tap for a pick near you.'],
            ['Makan malam apa?', 'Let MakanApa pick — tap for a spot nearby.'],
        ],
        'generic_iftar' => [
            ['Nak buka puasa kat mana? 🌙', 'Tap for an iftar pick near you.'],
        ],
    ];

    /**
     * @param  'lunch'|'dinner'|'iftar'  $slot
     * @param  array{name: string, distanceKm: float, closesAt: ?string}|null  $place
     * @return array{key: string, title: string, body: string}
     */
    public static function compose(string $slot, ?array $place, bool $raining, CarbonImmutable $localDay, int $userId): array
    {
        $key = match (true) {
            $place === null => $slot === 'iftar' ? 'generic_iftar' : "generic_{$slot}",
            $slot === 'iftar' => 'ramadan_iftar',
            $raining => "rain_{$slot}",
            $slot === 'lunch' && $localDay->isFriday() => 'friday_lunch',
            default => "normal_{$slot}",
        };

        $variants = self::VARIANTS[$key];
        [$title, $body] = $variants[crc32($userId.'|'.$localDay->toDateString()) % count($variants)];

        if ($place !== null) {
            $body = strtr($body, [
                '{name}' => $place['name'],
                '{distance}' => self::distance($place['distanceKm']),
                '{closes}' => $place['closesAt'] ? ", open till {$place['closesAt']}" : '',
            ]);
        }

        return ['key' => $key, 'title' => $title, 'body' => $body];
    }

    /** @return string[] every key, for admin filters/tests */
    public static function keys(): array
    {
        return array_keys(self::VARIANTS);
    }

    private static function distance(float $km): string
    {
        return $km < 1 ? (int) (round($km * 1000 / 50) * 50).' m' : number_format($km, 1).' km';
    }
}
