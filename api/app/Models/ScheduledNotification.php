<?php

namespace App\Models;

use App\Notifications\AccountAdminNotice;
use App\Notifications\ReleaseAnnouncement;
use App\Support\NotificationCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notification;

#[Fillable(['category', 'title', 'body', 'version', 'message', 'app_store_url', 'send_at', 'status', 'created_by'])]
class ScheduledNotification extends Model
{
    protected function casts(): array
    {
        return [
            'send_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Builds the actual Notification instance from whichever fields this category uses — the single place that mapping happens. */
    public function toNotification(): Notification
    {
        return $this->category === NotificationCategory::RELEASE_ANNOUNCEMENTS
            ? new ReleaseAnnouncement($this->version, $this->message, $this->app_store_url ?: null)
            : new AccountAdminNotice($this->title, $this->body);
    }
}
