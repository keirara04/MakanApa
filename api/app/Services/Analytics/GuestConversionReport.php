<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * How guests (POST auth/guest) turn into real accounts, and which app surface converted them
 * (users.signup_source, set by the app on sign-up). A guest upgrades in place, so its row keeps
 * the guest's created_at and gains upgraded_from_guest_at.
 */
class GuestConversionReport
{
    private const CACHE_MINUTES = 5;

    /**
     * @return array{guests: int, converted: int, rate: ?float, medianHoursToConvert: ?float, conversionsBySource: list<array{source: string, total: int, medianHours: ?float}>, freshSignupsBySource: list<array{source: string, total: int}>}
     */
    public function summary(int $days): array
    {
        return Cache::remember("admin:guest-conversion:summary:{$days}", now()->addMinutes(self::CACHE_MINUTES), function () use ($days) {
            $since = now()->subDays($days);

            // Cohort = guests created in the window, whether or not they've upgraded since.
            $cohort = User::query()
                ->where('created_at', '>=', $since)
                ->where(fn ($query) => $query->where('is_guest', true)->orWhereNotNull('upgraded_from_guest_at'));
            $guests = (clone $cohort)->count();
            $converted = (clone $cohort)->whereNotNull('upgraded_from_guest_at')->count();

            // Conversions that happened in the window (the guest may be older than it).
            $conversions = User::query()->where('upgraded_from_guest_at', '>=', $since);
            $hours = 'extract(epoch from (upgraded_from_guest_at - created_at)) / 3600';

            return [
                'guests' => $guests,
                'converted' => $converted,
                'rate' => $guests > 0 ? (float) $converted / $guests : null,
                'medianHoursToConvert' => $this->nullableFloat((clone $conversions)
                    ->selectRaw("percentile_cont(0.5) within group (order by {$hours}) as median")
                    ->value('median')),
                'conversionsBySource' => (clone $conversions)
                    ->selectRaw("coalesce(signup_source, 'unknown') as source, count(*) as total")
                    ->selectRaw("percentile_cont(0.5) within group (order by {$hours}) as median_hours")
                    ->groupBy(DB::raw("coalesce(signup_source, 'unknown')"))
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn ($row) => ['source' => $row->source, 'total' => (int) $row->total, 'medianHours' => $this->nullableFloat($row->median_hours)])
                    ->all(),
                // Straight-to-account sign-ups that never went through a guest session.
                'freshSignupsBySource' => User::query()
                    ->where('created_at', '>=', $since)
                    ->where('is_guest', false)
                    ->whereNull('upgraded_from_guest_at')
                    ->selectRaw("coalesce(signup_source, 'unknown') as source, count(*) as total")
                    ->groupBy(DB::raw("coalesce(signup_source, 'unknown')"))
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn ($row) => ['source' => $row->source, 'total' => (int) $row->total])
                    ->all(),
            ];
        });
    }

    /**
     * Guests created vs guests upgraded, per day, zero-filled.
     *
     * @return array{labels: list<string>, created: list<int>, upgraded: list<int>}
     */
    public function daily(int $days): array
    {
        return Cache::remember("admin:guest-conversion:daily:{$days}", now()->addMinutes(self::CACHE_MINUTES), function () use ($days) {
            $since = today()->subDays($days - 1);

            $created = User::query()
                ->where('created_at', '>=', $since)
                ->where(fn ($query) => $query->where('is_guest', true)->orWhereNotNull('upgraded_from_guest_at'))
                ->selectRaw('date(created_at) as day, count(*) as total')
                ->groupBy(DB::raw('date(created_at)'))
                ->pluck('total', 'day');
            $upgraded = User::query()
                ->where('upgraded_from_guest_at', '>=', $since)
                ->selectRaw('date(upgraded_from_guest_at) as day, count(*) as total')
                ->groupBy(DB::raw('date(upgraded_from_guest_at)'))
                ->pluck('total', 'day');

            $dates = collect(range(0, $days - 1))->map(fn (int $i) => $since->copy()->addDays($i));

            return [
                'labels' => $dates->map(fn ($d) => $d->format('M j'))->all(),
                'created' => $dates->map(fn ($d) => (int) ($created[$d->toDateString()] ?? 0))->all(),
                'upgraded' => $dates->map(fn ($d) => (int) ($upgraded[$d->toDateString()] ?? 0))->all(),
            ];
        });
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }
}
