<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmbassadorApplication;
use App\Models\User;
use App\Notifications\AmbassadorApplicationDecided;
use App\Services\AdminAuditLogger;
use App\Services\AdminUserService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Approve / decline, used by the Filament resource. Approving runs the same
 * AdminUserService::setAmbassador as the manual "Set ambassador" user action, so its
 * `user.set_ambassador` audit row fires too.
 */
class AmbassadorApplicationController extends Controller
{
    public function __construct(
        private readonly AdminUserService $users,
        private readonly AdminAuditLogger $auditLogger,
    ) {}

    public function approve(AmbassadorApplication $application, User $admin): void
    {
        if ($application->status !== 'pending') {
            throw new RuntimeException('This application was already reviewed.');
        }
        if ($application->communityId() === null) {
            throw new RuntimeException('That community no longer exists.');
        }

        DB::transaction(function () use ($application, $admin) {
            $this->users->setAmbassador($application->user, $application->communityType(), $application->communityId(), $admin);
            $application->update(['status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $this->auditLogger->log($admin, 'ambassador_application.approve', $application);
        });

        $application->user->notify(new AmbassadorApplicationDecided($application, 'approved'));
    }

    public function decline(AmbassadorApplication $application, ?string $note, User $admin): void
    {
        if ($application->status !== 'pending') {
            throw new RuntimeException('This application was already reviewed.');
        }
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;

        DB::transaction(function () use ($application, $note, $admin) {
            $application->update(['status' => 'declined', 'review_note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $this->auditLogger->log($admin, 'ambassador_application.decline', $application);
        });

        $application->user->notify(new AmbassadorApplicationDecided($application, 'declined', $note));
    }
}
