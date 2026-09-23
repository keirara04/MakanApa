<?php

namespace App\Services\Nudges;

use App\Models\Decision;
use App\Models\User;
use App\Services\Brain\ContextEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;

/**
 * When a user's next nudge should go out: the minute they usually decide in that slot (median of
 * their own decisions, minus a small lead), else a sensible default — shifted after Jumaat on
 * Fridays, and replaced by a pre-iftar nudge during Ramadan for halal-only users. Never inside
 * quiet hours. All times are Malaysia local (brain.timezone); results are returned in UTC.
 */
class MealNudgeTiming
{
    private const SLOTS = ['lunch', 'dinner'];

    /**
     * The earliest send time strictly after $now, skipping $skipLocalDate (a day that already
     * had its nudge).
     *
     * @return array{at: CarbonImmutable, slot: string}|null
     */
    public function next(User $user, CarbonImmutable $now, ?string $skipLocalDate = null): ?array
    {
        $timezone = (string) Config::get('brain.timezone', 'Asia/Kuala_Lumpur');
        $localNow = $now->setTimezone($timezone);
        $typical = $this->typicalMinutes($user, $now, $timezone);

        for ($dayOffset = 0; $dayOffset <= 2; $dayOffset++) {
            $day = $localNow->startOfDay()->addDays($dayOffset);
            if ($day->toDateString() === $skipLocalDate) {
                continue;
            }

            foreach ($this->candidatesFor($day, $user, $typical) as $slot => $at) {
                if ($at->greaterThan($localNow) && ! $this->inQuietHours($at)) {
                    return ['at' => $at->utc(), 'slot' => $slot];
                }
            }
        }

        return null;
    }

    /** @return array<string, CarbonImmutable> slot => local send time, in day order */
    private function candidatesFor(CarbonImmutable $day, User $user, array $typical): array
    {
        $ramadan = (bool) $user->halal_preference && ContextEngine::isRamadan($day);
        $candidates = [];

        foreach (self::SLOTS as $slot) {
            if ($ramadan && $slot === 'lunch') {
                continue;
            }

            if ($ramadan && $slot === 'dinner') {
                [$iftarStart] = Config::get('brain.context.ramadan_slots.iftar', ['18:30', '20:30']);
                $candidates['iftar'] = $this->at($day, $iftarStart)->subMinutes((int) Config::get('nudges.ramadan_iftar_lead_minutes', 45));

                continue;
            }

            if ($slot === 'lunch' && $day->isFriday()) {
                $candidates[$slot] = $this->at($day, (string) Config::get('nudges.friday_lunch_time', '14:15'));

                continue;
            }

            $candidates[$slot] = isset($typical[$slot])
                ? $day->addMinutes($typical[$slot] - (int) Config::get('nudges.lead_minutes', 15))
                : $this->at($day, (string) Config::get("nudges.default_times.{$slot}"));
        }

        return $candidates;
    }

    /**
     * Median local minute-of-day of the user's own decisions per slot — only for slots with
     * enough history to mean something.
     *
     * @return array<string, int>
     */
    private function typicalMinutes(User $user, CarbonImmutable $now, string $timezone): array
    {
        $bySlot = [];
        Decision::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $now->subDays((int) Config::get('nudges.history_days', 60)))
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$bySlot, $timezone) {
                $local = CarbonImmutable::parse($createdAt)->setTimezone($timezone);
                $slot = ContextEngine::slotFor($local, false);
                if (in_array($slot, self::SLOTS, true)) {
                    $bySlot[$slot][] = $local->hour * 60 + $local->minute;
                }
            });

        $minSamples = (int) Config::get('nudges.min_history_samples', 3);
        $typical = [];
        foreach ($bySlot as $slot => $minutes) {
            if (count($minutes) >= $minSamples) {
                sort($minutes);
                $typical[$slot] = $minutes[intdiv(count($minutes), 2)];
            }
        }

        return $typical;
    }

    private function at(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $day->setTime($hour, $minute);
    }

    public function inQuietHours(CarbonImmutable $local): bool
    {
        [$start, $end] = Config::get('nudges.quiet_hours', ['22:00', '08:00']);
        $time = $local->format('H:i');

        return $start <= $end ? ($time >= $start && $time < $end) : ($time >= $start || $time < $end);
    }
}
