<?php

namespace App\Models;

use App\Support\CommunityReportReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['community_post_id', 'reporter_id', 'reason', 'note', 'resolved_at', 'resolved_by', 'resolution'])]
class CommunityPostReport extends Model
{
    protected function casts(): array
    {
        return [
            'reason' => CommunityReportReason::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommunityPost::class, 'community_post_id')->withTrashed();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
