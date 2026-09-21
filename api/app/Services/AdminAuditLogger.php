<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Every call site is expected to sit inside the same DB::transaction() as the mutation it's
 * describing — an admin action must never happen without its audit row, or vice versa.
 */
class AdminAuditLogger
{
    public function log(User $admin, string $action, Model $subject, ?string $reason = null, array $metadata = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
