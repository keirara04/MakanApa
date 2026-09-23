<?php

namespace App\Services\Brain;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * Reads the Malaysian moment: meal slot, supper, Friday prayers, Ramadan sahur/iftar, month-end
 * and rain. Rules are pure and cheap; weather comes from WeatherService's cache only. Ramadan
 * slots only apply to users who opted into halal-only — nothing is assumed about anyone else.
 * Month-end is deliberately the weakest nudge in the system and never applies when the user
 * already chose a budget or a lens.
 */
class ContextEngine
{
    public const SIGNALS = ['rain', 'supper', 'friday', 'iftar', 'sahur', 'month_end'];

    public function __construct(private readonly WeatherService $weather) {}

    /**
     * @param  string[]  $ignored  signals the user switched off in the context strip
     */
    public function resolve(?float $lat, ?float $lon, bool $halalOnly, bool $hasBudgetOrLens, array $ignored = [], ?CarbonInterface $now = null): ContextSnapshot
    {
        if (! Config::get('brain.features.context')) {
            return ContextSnapshot::neutral();
        }

        $now ??= Carbon::now();
        $local = $now->copy()->setTimezone(Config::get('brain.timezone'));
        $ramadan = $halalOnly && self::isRamadan($local);
        $slot = self::slotFor($local, $ramadan);

        $signals = [];
        $rule = fn (bool $active) => ['active' => $active, 'confidence' => $active ? 1.0 : 0.0, 'source' => 'rule', 'ageMinutes' => null];

        $signals['supper'] = $rule($slot === 'supper');
        $signals['friday'] = $rule($local->isFriday() && self::within($local, Config::get('brain.context.friday_prayer')));
        $signals['iftar'] = $rule($slot === 'iftar');
        $signals['sahur'] = $rule($slot === 'sahur');
        [$from, $to] = Config::get('brain.context.month_end_days', [18, 24]);
        $signals['month_end'] = $rule(! $hasBudgetOrLens && $local->day >= $from && $local->day <= $to);

        $weather = ($lat !== null && $lon !== null) ? $this->weather->current($lat, $lon) : null;
        $signals['rain'] = [
            'active' => (bool) ($weather['raining'] ?? false),
            'confidence' => ($weather['raining'] ?? false) ? $weather['confidence'] : 0.0,
            'source' => 'weather',
            'ageMinutes' => $weather['ageMinutes'] ?? null,
        ];

        foreach ($ignored as $key) {
            if (isset($signals[$key]) && $signals[$key]['active']) {
                // Kept (as ignored) so the strip can offer to switch it back on.
                $signals[$key] = [...$signals[$key], 'active' => false, 'confidence' => 0.0, 'ignored' => true];
            }
        }

        return new ContextSnapshot(
            mealSlot: $slot,
            localTime: $local->toIso8601String(),
            signals: $signals,
            generatedAt: $now->toIso8601String(),
            weatherAgeMinutes: $weather['ageMinutes'] ?? null,
            weatherSource: $weather !== null ? 'open-meteo' : null,
        );
    }

    /**
     * Weight additions per component, each scaled by that signal's confidence.
     *
     * @return array<string, float>
     */
    public static function overlay(ContextSnapshot $context): array
    {
        $weights = [];
        foreach (Config::get('brain.context.overlay', []) as $signal => $components) {
            $confidence = $context->confidence($signal);
            if ($confidence <= 0) {
                continue;
            }
            foreach ($components as $component => $weight) {
                $weights[$component] = ($weights[$component] ?? 0) + $weight * $confidence;
            }
        }

        return $weights;
    }

    private const LABELS = [
        'rain' => ['Hujan', '☔'],
        'supper' => ['Supper', '🌙'],
        'friday' => ['Jumaat', '🕌'],
        'iftar' => ['Iftar', '🌅'],
        'sahur' => ['Sahur', '🌙'],
        'month_end' => ['Hujung bulan', '💸'],
    ];

    /**
     * Signals for the iOS "Right now" strip — every signal that is (or would be) active,
     * including ones the user switched off, so they can switch them back on.
     *
     * @return array<int, array{key: string, label: string, icon: string, active: bool, ignored: bool, confidence: float, stale: bool}>
     */
    public static function present(ContextSnapshot $context): array
    {
        $out = [];
        foreach ($context->signals as $key => $signal) {
            $ignored = (bool) ($signal['ignored'] ?? false);
            if (! $signal['active'] && ! $ignored) {
                continue;
            }
            [$label, $icon] = self::LABELS[$key] ?? [$key, '•'];
            $out[] = [
                'key' => $key,
                'label' => $label,
                'icon' => $icon,
                'active' => $signal['active'],
                'ignored' => $ignored,
                'confidence' => (float) $signal['confidence'],
                'stale' => $signal['source'] === 'weather' && ($signal['ageMinutes'] ?? 0) >= (int) Config::get('brain.weather.fresh_minutes', 30),
            ];
        }

        return $out;
    }

    public static function slotFor(CarbonInterface $local, bool $ramadan): string
    {
        if ($ramadan) {
            foreach (Config::get('brain.context.ramadan_slots', []) as $name => $range) {
                if (self::within($local, $range)) {
                    return $name;
                }
            }
        }

        foreach (Config::get('brain.context.slots', []) as $name => $range) {
            if (self::within($local, $range)) {
                return $name;
            }
        }

        return 'anytime';
    }

    public static function isRamadan(CarbonInterface $local): bool
    {
        $date = $local->format('Y-m-d');
        foreach (Config::get('brain.context.ramadan', []) as [$start, $end]) {
            if ($date >= $start && $date <= $end) {
                return true;
            }
        }

        return false;
    }

    /** [start, end) in HH:MM local time; handles ranges that wrap past midnight. */
    private static function within(CarbonInterface $local, array $range): bool
    {
        [$start, $end] = $range;
        $time = $local->format('H:i');

        return $start <= $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);
    }
}
