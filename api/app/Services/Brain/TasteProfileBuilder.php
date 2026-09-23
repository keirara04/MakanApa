<?php

namespace App\Services\Brain;

use App\Models\TasteEvent;
use App\Models\TasteProfile;
use Illuminate\Support\Facades\DB;

/**
 * Folds taste_events into taste_profiles. `catchUp()` applies only events newer than the
 * profile's last_event_id (the incremental path, one locked row); `rebuild()` replays from
 * scratch. Both go through TasteMemory::apply(), so they always agree. A `reset` event is a
 * boundary: replay starts after the latest one, and history is never deleted.
 */
class TasteProfileBuilder
{
    public function catchUp(TasteOwner $owner): TasteProfile
    {
        return DB::transaction(function () use ($owner) {
            $profile = $this->lockedProfile($owner);

            $events = $owner->scope(TasteEvent::query())
                ->when($profile->last_event_id, fn ($q) => $q->where('id', '>', $profile->last_event_id))
                ->orderBy('id')
                ->get();

            if ($events->isEmpty()) {
                return $profile;
            }

            $state = $this->stateOf($profile);
            foreach ($events as $event) {
                $state = TasteMemory::apply($state, $event->getAttributes());
                if ($event->signal === 'reset') {
                    $profile->reset_at_event_id = $event->id;
                }
            }

            $this->write($profile, $state, $events->last()->id);

            return $profile;
        });
    }

    public function rebuild(TasteOwner $owner): TasteProfile
    {
        return DB::transaction(function () use ($owner) {
            $profile = $this->lockedProfile($owner);

            $resetId = $owner->scope(TasteEvent::query())->where('signal', 'reset')->max('id');
            $state = TasteMemory::empty();
            $lastId = $resetId;

            $owner->scope(TasteEvent::query())
                ->when($resetId, fn ($q) => $q->where('id', '>', $resetId))
                ->orderBy('id')
                ->chunk(500, function ($events) use (&$state, &$lastId) {
                    foreach ($events as $event) {
                        $state = TasteMemory::apply($state, $event->getAttributes());
                        $lastId = $event->id;
                    }
                });

            $profile->reset_at_event_id = $resetId;
            $this->write($profile, $state, $lastId);

            return $profile;
        });
    }

    private function lockedProfile(TasteOwner $owner): TasteProfile
    {
        $empty = TasteMemory::empty();
        TasteProfile::query()->insertOrIgnore([
            ...$owner->profileKey(),
            'memory' => json_encode($empty['memory']),
            'muted' => '[]',
            'corrections' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TasteProfile::query()->where($owner->profileKey())->lockForUpdate()->firstOrFail();
    }

    private function stateOf(TasteProfile $profile): array
    {
        return [
            'memory' => $profile->memory ?? TasteMemory::empty()['memory'],
            'muted' => $profile->muted ?? [],
            'corrections' => $profile->corrections ?? [],
            'signal_count' => (int) $profile->signal_count,
        ];
    }

    private function write(TasteProfile $profile, array $state, ?int $lastEventId): void
    {
        $profile->forceFill([
            'memory' => $state['memory'],
            'muted' => $state['muted'],
            'corrections' => (object) $state['corrections'],
            'signal_count' => $state['signal_count'],
            'version' => $profile->version + 1,
            'last_event_id' => $lastEventId,
        ])->save();
    }
}
