<?php

namespace App\Services\Nudges;

use App\Models\MealNudge;
use App\Models\MealNudgeState;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\MealtimeNudge;
use App\Support\NotificationCategory;
use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the nudges that are due. Only touches users whose stored `next_nudge_at` has passed, so
 * a run costs O(due users), not O(opted-in users). Eligibility, timing, pick and copy each live
 * in their own service; this just sequences them and keeps the per-user state honest.
 */
class MealNudgeDispatcher
{
    private const BATCH = 500;

    public function __construct(
        private readonly MealNudgeTiming $timing,
        private readonly MealNudgeEligibility $eligibility,
        private readonly NudgePickFinder $pickFinder,
    ) {}

    /** @return array<int, array{user_id: int, outcome: string, detail: string}> */
    public function dispatch(CarbonImmutable $now, bool $dryRun = false): array
    {
        $this->scheduleNewlyOptedIn($now, $dryRun);

        return MealNudgeState::query()
            ->whereNull('stopped_at')
            ->whereNotNull('next_nudge_at')
            ->where('next_nudge_at', '<=', $now)
            ->with('user')
            ->orderBy('next_nudge_at')
            ->limit(self::BATCH)
            ->get()
            ->filter(fn (MealNudgeState $state) => $state->user !== null)
            ->map(fn (MealNudgeState $state) => ['user_id' => $state->user_id, ...$this->handle($state, $now, $dryRun)])
            ->values()
            ->all();
    }

    /** The user just turned mealtime picks on (again): clean slate, next slot scheduled. */
    public function optIn(User $user, CarbonImmutable $now): void
    {
        $next = $this->timing->next($user, $now);
        MealNudgeState::updateOrCreate(['user_id' => $user->id], [
            'next_nudge_at' => $next['at'] ?? null,
            'next_slot' => $next['slot'] ?? null,
            'unopened_streak' => 0,
            'pause_count' => 0,
            'paused_until' => null,
            'stopped_at' => null,
        ]);
    }

    public function optOut(User $user): void
    {
        MealNudgeState::where('user_id', $user->id)->update(['next_nudge_at' => null, 'next_slot' => null]);
    }

    /** @return array{outcome: string, detail: string} */
    private function handle(MealNudgeState $state, CarbonImmutable $now, bool $dryRun): array
    {
        $user = $state->user;
        $localDay = $now->setTimezone((string) Config::get('brain.timezone', 'Asia/Kuala_Lumpur'))->startOfDay();
        $slot = $state->next_slot ?? 'lunch';

        if ($reason = $this->eligibility->reasonToSkip($user, $state, $now)) {
            if (! $dryRun) {
                $this->rescheduleAfterSkip($state, $user, $now, $reason, $localDay);
            }

            return ['outcome' => 'skipped', 'detail' => $reason];
        }

        // Back-off from stored state: the previous nudge going unopened extends the streak.
        $streak = $state->unopened_streak + ($this->previousWentUnopened($user) ? 1 : 0);
        if ($streak >= (int) Config::get('nudges.unopened_limit', 3)) {
            if (! $dryRun) {
                $this->backOff($state, $user, $now);
            }

            return ['outcome' => 'skipped', 'detail' => 'backoff'];
        }

        $found = $this->pickFinder->find($user, $now);
        $place = $this->placeForCopy($found['pick']);
        $copy = NudgeCopyCatalog::compose($slot, $place, $found['raining'], $localDay, $user->id);
        $detail = $copy['key'].($place ? ' · '.$place['name'] : '');

        if ($dryRun) {
            return ['outcome' => 'would_send', 'detail' => $detail];
        }

        try {
            $nudge = MealNudge::create([
                'user_id' => $user->id,
                'local_date' => $localDay->toDateString(),
                'slot' => $slot,
                'restaurant_id' => $found['pick']['restaurant']['id'] ?? null,
                'copy_key' => $copy['key'],
                'status' => MealNudge::STATUS_SENT,
                'sent_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another run already nudged this user today.
            $this->scheduleNext($state, $user, $now, $localDay->toDateString(), $state->unopened_streak);

            return ['outcome' => 'skipped', 'detail' => 'already_today'];
        }

        try {
            $user->notify(new MealtimeNudge($nudge, $copy['title'], $copy['body']));
        } catch (Throwable $e) {
            $nudge->update(['status' => MealNudge::STATUS_FAILED]);
            Log::warning('Mealtime nudge failed to send', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $this->scheduleNext($state, $user, $now, $localDay->toDateString(), $streak);

        return ['outcome' => 'sent', 'detail' => $detail];
    }

    /** @return array{name: string, distanceKm: float, closesAt: ?string}|null */
    private function placeForCopy(?array $pick): ?array
    {
        if ($pick === null) {
            return null;
        }

        return [
            'name' => $pick['restaurant']['name'],
            'distanceKm' => $pick['distanceKm'],
            'closesAt' => OpeningHours::closesAt(Restaurant::find($pick['restaurant']['id'])?->opening_hours, now()),
        ];
    }

    private function previousWentUnopened(User $user): bool
    {
        $previous = MealNudge::where('user_id', $user->id)->where('status', MealNudge::STATUS_SENT)->latest('sent_at')->first(['opened_at']);

        return $previous !== null && $previous->opened_at === null;
    }

    private function backOff(MealNudgeState $state, User $user, CarbonImmutable $now): void
    {
        $pauses = $state->pause_count + 1;
        if ($pauses >= (int) Config::get('nudges.max_pauses', 3)) {
            $state->update(['unopened_streak' => 0, 'pause_count' => $pauses, 'paused_until' => null, 'stopped_at' => $now, 'next_nudge_at' => null, 'next_slot' => null]);

            return;
        }

        $pausedUntil = $now->addDays((int) Config::get('nudges.pause_days', 7));
        $next = $this->timing->next($user, $pausedUntil);
        $state->update([
            'unopened_streak' => 0,
            'pause_count' => $pauses,
            'paused_until' => $pausedUntil,
            'next_nudge_at' => $next['at'] ?? null,
            'next_slot' => $next['slot'] ?? null,
        ]);
    }

    private function rescheduleAfterSkip(MealNudgeState $state, User $user, CarbonImmutable $now, string $reason, CarbonImmutable $localDay): void
    {
        match ($reason) {
            // Nothing to schedule until the user turns it back on (optIn reschedules).
            'opted_out', 'stopped' => $state->update(['next_nudge_at' => null, 'next_slot' => null]),
            'paused' => $this->scheduleFrom($state, $user, CarbonImmutable::parse($state->paused_until)),
            'already_today' => $this->scheduleNext($state, $user, $now, $localDay->toDateString(), $state->unopened_streak),
            // Recently active / quiet hours / no device: try the next slot.
            default => $this->scheduleNext($state, $user, $now, null, $state->unopened_streak),
        };
    }

    private function scheduleNext(MealNudgeState $state, User $user, CarbonImmutable $now, ?string $skipLocalDate, int $streak): void
    {
        $next = $this->timing->next($user, $now, $skipLocalDate);
        $state->update([
            'unopened_streak' => $streak,
            'next_nudge_at' => $next['at'] ?? null,
            'next_slot' => $next['slot'] ?? null,
        ]);
    }

    private function scheduleFrom(MealNudgeState $state, User $user, CarbonImmutable $from): void
    {
        $next = $this->timing->next($user, $from);
        $state->update(['next_nudge_at' => $next['at'] ?? null, 'next_slot' => $next['slot'] ?? null]);
    }

    /** Opted in (e.g. before this feature shipped, or via the API directly) but never scheduled. */
    private function scheduleNewlyOptedIn(CarbonImmutable $now, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        User::query()
            ->where('notification_preferences->'.NotificationCategory::MEALTIME_NUDGES, true)
            ->whereDoesntHave('mealNudgeState')
            ->limit(self::BATCH)
            ->get()
            ->each(fn (User $user) => $this->optIn($user, $now));
    }
}
