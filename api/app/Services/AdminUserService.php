<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * All admin-facing user state changes go through here — suspend/reactivate/role changes are
 * never a raw `Select` edit on the User form, so there is exactly one path (and one audit
 * entry) per change. Also where the "don't lock yourself out of the admin panel" guards live.
 */
class AdminUserService
{
    public function __construct(private readonly AdminAuditLogger $auditLogger) {}

    public function suspend(User $target, string $reason, User $admin): void
    {
        $this->guardNotSelf($target, $admin, 'suspend');
        $this->guardNotLastActiveSuperadmin($target);

        DB::transaction(function () use ($target, $reason, $admin) {
            $target->update(['status' => 'suspended']);
            $this->auditLogger->log($admin, 'user.suspend', $target, reason: $reason);
        });
    }

    public function reactivate(User $target, User $admin): void
    {
        DB::transaction(function () use ($target, $admin) {
            $target->update(['status' => 'active']);
            $this->auditLogger->log($admin, 'user.reactivate', $target);
        });
    }

    public function changeRole(User $target, string $role, User $admin): void
    {
        $this->guardNotSelf($target, $admin, 'change the role of');

        if ($target->role === 'superadmin' && $role !== 'superadmin') {
            $this->guardNotLastActiveSuperadmin($target);
        }

        DB::transaction(function () use ($target, $role, $admin) {
            $previousRole = $target->role;
            $target->update(['role' => $role]);
            $this->auditLogger->log($admin, 'user.change_role', $target, metadata: [
                'from' => $previousRole,
                'to' => $role,
            ]);
        });
    }

    public function revokeSessions(User $target, User $admin): void
    {
        DB::transaction(function () use ($target, $admin) {
            $target->tokens()->delete();
            $this->auditLogger->log($admin, 'user.revoke_sessions', $target);
        });
    }

    /**
     * Soft delete, not a hard delete — the row (and its saved places, submissions, etc. via
     * their existing cascades) stays recoverable via restore() until a superadmin explicitly
     * force-deletes it. Distinct from suspend(): suspension is reversible-by-design routine
     * moderation; this is for "this account shouldn't exist" (spam, abuse, a mistaken signup).
     */
    public function delete(User $target, string $reason, User $admin): void
    {
        $this->guardNotSelf($target, $admin, 'delete');
        $this->guardNotLastActiveSuperadmin($target);

        DB::transaction(function () use ($target, $reason, $admin) {
            $target->tokens()->delete();
            $target->delete();
            $this->auditLogger->log($admin, 'user.delete', $target, reason: $reason);
        });
    }

    public function restore(User $target, User $admin): void
    {
        DB::transaction(function () use ($target, $admin) {
            $target->restore();
            $this->auditLogger->log($admin, 'user.restore', $target);
        });
    }

    private function guardNotSelf(User $target, User $admin, string $verb): void
    {
        if ($target->id === $admin->id) {
            throw new RuntimeException("You cannot {$verb} your own account.");
        }
    }

    /**
     * A wrong click must never lock every admin out of the panel — refuse to suspend or demote
     * the sole remaining active superadmin. Checked against current DB state, not the in-memory
     * $target, so a stale model instance can't bypass the guard.
     */
    private function guardNotLastActiveSuperadmin(User $target): void
    {
        if ($target->role !== 'superadmin' || $target->status !== 'active') {
            return;
        }

        $activeSuperadmins = User::where('role', 'superadmin')->where('status', 'active')->count();

        if ($activeSuperadmins <= 1) {
            throw new RuntimeException('Cannot suspend or demote the last active superadmin.');
        }
    }
}
