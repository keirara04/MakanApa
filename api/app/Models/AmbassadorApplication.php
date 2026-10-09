<?php

namespace App\Models;

use App\Observers\AmbassadorApplicationObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Become an ambassador" — a member asks to represent their own university or area. An admin
 * approves (which runs the same AdminUserService::setAmbassador as the manual "Set ambassador"
 * action) or declines with an optional note.
 */
#[Fillable(['user_id', 'university_id', 'area_id', 'reason', 'instagram_handle', 'status', 'review_note', 'reviewed_by', 'reviewed_at'])]
#[ObservedBy(AmbassadorApplicationObserver::class)]
class AmbassadorApplication extends Model
{
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** 'university' or 'area'. */
    public function communityType(): string
    {
        return $this->university_id !== null ? 'university' : 'area';
    }

    public function communityId(): ?int
    {
        return $this->university_id ?? $this->area_id;
    }

    public function communityName(): ?string
    {
        return $this->university?->short_name ?? $this->area?->short_name;
    }
}
