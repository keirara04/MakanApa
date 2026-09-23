<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use App\Models\RestaurantHalalCertificate;
use App\Notifications\HalalCertificateLapsing;
use App\Services\Halal\HalalCertificateAudience;
use App\Services\Halal\HalalReviewStateResolver;
use App\Services\Halal\HalalSnapshotService;
use App\Services\Halal\HalalVerificationService;
use App\Support\Halal\CertificateStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily certificate lifecycle. Correctness never depends on this running (expiry is also
 * applied at read time — Restaurant::effectiveHalalStatus()); this powers operations: the
 * ledger/cert state, admin queues (review state), and owner/contributor notices.
 */
#[Signature('halal:lifecycle')]
#[Description('Expire lapsed halal certificates, flag expiring ones, and notify owners/contributors')]
class RunHalalLifecycle extends Command
{
    public function handle(HalalVerificationService $verifications, HalalSnapshotService $snapshots, HalalCertificateAudience $audience): int
    {
        $expired = 0;
        RestaurantHalalCertificate::where('status', CertificateStatus::Valid)
            ->whereDate('expires_at', '<', today())
            ->with('restaurant')
            ->each(function (RestaurantHalalCertificate $certificate) use ($verifications, &$expired) {
                $verifications->expireCertificate($certificate); // fires HalalCertificateLapsed -> notices
                $expired++;
            });

        $noticed = 0;
        foreach (config('halal.expiry_notice_days', [30, 7]) as $days) {
            RestaurantHalalCertificate::where('status', CertificateStatus::Valid)
                ->whereDate('expires_at', today()->addDays($days))
                ->with('restaurant')
                ->each(function (RestaurantHalalCertificate $certificate) use ($audience, $days, &$noticed) {
                    foreach ($audience->for($certificate) as $user) {
                        $user->notify(new HalalCertificateLapsing($certificate, $certificate->restaurant->name, "expiring_{$days}"));
                        $noticed++;
                    }
                });
        }

        // Move restaurants whose active cert just entered the expiring window into that review state.
        $refreshed = 0;
        Restaurant::whereNotNull('halal_active_certificate_id')
            ->whereDate('halal_expires_at', '<=', today()->addDays(HalalReviewStateResolver::EXPIRING_WINDOW_DAYS))
            ->each(function (Restaurant $restaurant) use ($snapshots, &$refreshed) {
                $snapshots->rebuild($restaurant);
                $refreshed++;
            });

        $this->info("Expired {$expired} certificate(s), sent {$noticed} expiry notice(s), refreshed {$refreshed} snapshot(s).");

        return self::SUCCESS;
    }
}
