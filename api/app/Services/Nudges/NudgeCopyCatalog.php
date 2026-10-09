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
            ['Had lunch yet?', '{name}, {distance} away{closes}.'],
            ['Tummy rumbling?', '{name} is {distance} away{closes}. Shall we?'],
            ['Lunch sorted', 'How about {name}? {distance} away{closes}.'],
        ],
        'normal_dinner' => [
            ['Dinner plans tonight?', '{name}, {distance} away{closes}.'],
            ['Dinner time, let\'s go', '{name} is {distance} away{closes}.'],
        ],
        'rain_lunch' => [
            ['Raining out', '{name} is just {distance} away{closes}. You won\'t get too wet.'],
            ['Rainy lunch', 'Stay dry: {name}, {distance} away{closes}.'],
        ],
        'rain_dinner' => [
            ['Raining out', '{name} is just {distance} away{closes}. Nice and close.'],
            ['Rainy dinner', 'Stay dry: {name}, {distance} away{closes}.'],
        ],
        'friday_lunch' => [
            ['Lunch after Friday prayers?', '{name}, {distance} away{closes}.'],
            ['Friday lunch', '{name} is {distance} away{closes}.'],
        ],
        'ramadan_iftar' => [
            ['Where to break your fast?', '{name}, {distance} away{closes}.'],
            ['Iftar sorted?', 'How about {name}, {distance} away{closes}?'],
        ],
        'generic_lunch' => [
            ['Lunch time!', 'Tap for a pick near you.'],
            ['Had lunch yet?', "Can't decide? MakanApa picks in seconds."],
        ],
        'generic_dinner' => [
            ['Dinner time!', 'Tap for a pick near you.'],
            ["What's for dinner?", 'Let MakanApa pick. Tap for a spot nearby.'],
        ],
        'generic_iftar' => [
            ['Where to break your fast?', 'Tap for an iftar pick near you.'],
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
