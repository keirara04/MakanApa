<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category', 'title', 'body', 'version', 'message', 'app_store_url',
    'source', 'scheduled_notification_id', 'created_by', 'recipients_considered',
])]
class NotificationBroadcast extends Model
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduledNotification(): BelongsTo
    {
        return $this->belongsTo(ScheduledNotification::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
