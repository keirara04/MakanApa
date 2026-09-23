<?php

namespace Tests\Feature\Halal;

use App\Notifications\HalalCertificateLapsing;
use App\Services\Halal\HalalPresenter;
use App\Services\Halal\HalalVerificationService;
use App\Support\Halal\CertificateStatus;
use App\Support\Halal\CertificationAuthority;
use App\Support\Halal\HalalReviewState;
use App\Support\Halal\HalalStatus;
use App\Support\Halal\HalalVerificationState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class HalalLifecycleTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    public function test_expiring_then_expired_lifecycle(): void
    {
        Notification::fake();
        $restaurant = $this->makeRestaurant();
        $contributor = $this->makeUser();
        $verification = app(HalalVerificationService::class)->recordModeratorDecision(
            $this->makeHalalReport($restaurant, $contributor, HalalStatus::Certified, 'approved'),
            HalalStatus::Certified, $this->makeAdmin(), $this->jakimCert('J-1', today()->addDays(40)->toDateString())
        );

        $this->travelTo(today()->addDays(10)); // 30 days out
        $this->artisan('halal:lifecycle')->assertSuccessful();
        $this->assertSame(HalalReviewState::Expiring, $restaurant->fresh()->halal_review_state);
        Notification::assertSentTo($contributor, HalalCertificateLapsing::class);

        $this->travelTo(today()->addDays(31)); // past expiry
        $presenter = app(HalalPresenter::class);
        // Read-time: already expired before the job runs.
        $this->assertSame('Cert expired · Help re-verify', $presenter->display($restaurant->fresh())['shortLabel']);

        $this->artisan('halal:lifecycle')->assertSuccessful();
        $fresh = $restaurant->fresh();
        $this->assertSame(CertificateStatus::Expired, $verification->certificate->fresh()->status);
        $this->assertSame(HalalVerificationState::Expired, $verification->fresh()->state);
        $this->assertSame(HalalStatus::Unknown, $fresh->halal_status);
        $this->assertSame(HalalReviewState::ReverifyRequired, $fresh->halal_review_state);
        $this->assertSame('help_reverify', $presenter->display($fresh)['action']);
    }

    public function test_abandoned_drafts_are_cancelled(): void
    {
        $restaurant = $this->makeRestaurant();
        $old = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::MuslimFriendly, 'draft');
        $old->forceFill(['updated_at' => now()->subDays(8)])->saveQuietly();
        $fresh = $this->makeHalalReport($this->makeRestaurant(), $this->makeUser(), HalalStatus::MuslimFriendly, 'draft');

        $this->artisan('community:prune-drafts')->assertSuccessful();

        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('draft', $fresh->fresh()->status);
    }

    public function test_classify_dry_run_writes_nothing_and_real_run_skips_human_rows(): void
    {
        $flagged = $this->makeRestaurant(['name' => 'Restoran Bak Kut Teh Klang']);
        $human = $this->makeRestaurant(['name' => 'Siew Yoke King']);
        app(HalalVerificationService::class)->recordAdminOverride($human, HalalStatus::MuslimFriendly, $this->makeAdmin(), 'Rebranded, now pork-free');

        $this->artisan('halal:classify --dry-run')
            ->expectsOutputToContain('Restoran Bak Kut Teh Klang — name: "bak kut teh" (strong) → non_halal')
            ->assertSuccessful();
        $this->assertSame(HalalStatus::Unknown, $flagged->fresh()->halal_status);

        $this->artisan('halal:classify')->assertSuccessful();
        $this->assertSame(HalalStatus::NonHalal, $flagged->fresh()->halal_status);
        $this->assertSame(HalalStatus::MuslimFriendly, $human->fresh()->halal_status);
    }

    public function test_rebuild_snapshots_converges(): void
    {
        $restaurant = $this->makeRestaurant();
        app(HalalVerificationService::class)->recordAdminOverride($restaurant, HalalStatus::MuslimFriendly, $this->makeAdmin(), 'Visited');
        $restaurant->forceFill(['halal_status' => 'non_halal'])->save(); // simulate drift

        $this->artisan('halal:rebuild-snapshots')->assertSuccessful();

        $this->assertSame(HalalStatus::MuslimFriendly, $restaurant->fresh()->halal_status);
    }

    public function test_labels_for_every_status_authority_combination(): void
    {
        $p = app(HalalPresenter::class);
        $cases = [
            [HalalStatus::Certified, 'jakim', false, 'Halal (JAKIM)', 'certified'],
            [HalalStatus::Certified, 'muis', false, 'Halal certified', 'certified'],
            [HalalStatus::Certified, null, false, 'Halal certified', 'certified'],
            [HalalStatus::MuslimFriendly, null, false, 'Muslim-friendly', 'friendly'],
            [HalalStatus::NonHalal, null, false, 'Non-halal', 'non_halal'],
            [HalalStatus::Unknown, null, false, 'Not verified · Help verify', 'neutral'],
            [HalalStatus::Unknown, 'jakim', true, 'Cert expired · Help re-verify', 'warning'],
        ];
        foreach ($cases as [$status, $authority, $expired, $short, $tone]) {
            $labels = $p->labels($status, CertificationAuthority::tryFrom((string) $authority), $expired);
            $this->assertSame($short, $labels['shortLabel'], "{$status->value}/{$authority}");
            $this->assertSame($tone, $labels['tone']);
            // Wording rule: only certified may say "Halal".
            if ($status !== HalalStatus::Certified) {
                $this->assertStringNotContainsString('Halal (', $labels['shortLabel']);
                $this->assertNotSame('Halal certified', $labels['shortLabel']);
            }
        }
    }
}
