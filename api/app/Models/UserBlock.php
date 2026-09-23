<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['blocker_id', 'blocked_id'])]
class UserBlock extends Model
{
    public const UPDATED_AT = null;

    /** @return array<int, int> user ids whose content $user must not see (either side of a block) */
    public static function hiddenUserIdsFor(User $user): array
    {
        return static::query()
            ->where('blocker_id', $user->id)->pluck('blocked_id')
            ->merge(static::query()->where('blocked_id', $user->id)->pluck('blocker_id'))
            ->unique()->values()->all();
    }

    public static function existsBetween(int $a, int $b): bool
    {
        return static::query()
            ->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }
}
