<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A short "thought" posted to a university/area community, optionally tagged to a restaurant,
 * or a one-level reply to one (parent_id set). All writes go through CommunityPostService
 * (user side) or CommunityPostModerationService (admin side) — never a raw update().
 */
#[Fillable(['user_id', 'parent_id', 'university_id', 'area_id', 'restaurant_id', 'body', 'status', 'hidden_reason', 'reaction_count', 'reply_count', 'report_count'])]
class CommunityPost extends Model
{
    use SoftDeletes;

    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_REMOVED = 'removed';

    protected function casts(): array
    {
        return [
            'reaction_count' => 'integer',
            'reply_count' => 'integer',
            'report_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CommunityPostReaction::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(CommunityPostReport::class);
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function isVisible(): bool
    {
        return $this->status === self::STATUS_VISIBLE;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('community_posts.status', self::STATUS_VISIBLE);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('community_posts.parent_id');
    }

    /** Same community as $user's current affiliation. Callers must have already rejected unaffiliated users. */
    public function scopeInCommunityOf(Builder $query, User $user): Builder
    {
        return $user->universityId() !== null
            ? $query->where('community_posts.university_id', $user->universityId())
            : $query->where('community_posts.area_id', $user->areaId());
    }

    /** Hides both directions of a block: people $user blocked, and people who blocked $user. */
    public function scopeNotBlockedFor(Builder $query, User $user): Builder
    {
        return $query->whereNotIn('community_posts.user_id', UserBlock::hiddenUserIdsFor($user));
    }

    public function belongsToCommunityOf(User $user): bool
    {
        return ($user->universityId() !== null && $this->university_id === $user->universityId())
            || ($user->areaId() !== null && $this->area_id === $user->areaId());
    }
}
