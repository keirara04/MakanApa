<?php

namespace Tests\Feature\Halal;

use App\Filament\Resources\Halal\Certificates\Pages\ListHalalCertificates;
use App\Filament\Resources\Halal\Reports\Pages\ListHalalReports;
use App\Filament\Resources\Halal\Restaurants\Pages\ListHalalRestaurants;
use App\Filament\Resources\Restaurants\Pages\EditRestaurant;
use App\Filament\Resources\Restaurants\RelationManagers\HalalCertificatesRelationManager;
use App\Filament\Resources\Restaurants\RelationManagers\HalalVerificationsRelationManager;
use App\Filament\Resources\RestaurantSubmissions\Pages\ViewRestaurantSubmission;
use App\Services\Halal\HalalVerificationService;
use App\Services\RestaurantPhotoUploadService;
use App\Support\Halal\HalalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\BuildsHalalFixtures;
use Tests\TestCase;

class HalalAdminPanelTest extends TestCase
{
    use BuildsHalalFixtures, RefreshDatabase;

    public function test_halal_trust_pages_render_with_data(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'web');
        $restaurant = $this->makeRestaurant(['name' => 'Bak Kut Teh House']);
        app(HalalVerificationService::class)->recordModeratorDecision(
            $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'approved'), HalalStatus::Certified, $admin, $this->jakimCert()
        );
        $pending = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::NonHalal);

        foreach (['queue', 'conflicting', 'owners', 'all'] as $tab) {
            Livewire::test(ListHalalReports::class, ['activeTab' => $tab])->assertOk();
        }
        foreach (['expiring', 'expired', 'revoked', 'all'] as $tab) {
            Livewire::test(ListHalalCertificates::class, ['activeTab' => $tab])->assertOk();
        }
        foreach (['needs', 'reverify', 'conflicting', 'recent', 'heuristic'] as $tab) {
            Livewire::test(ListHalalRestaurants::class, ['activeTab' => $tab])->assertOk();
        }
        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])->assertOk();
        Livewire::test(HalalVerificationsRelationManager::class, ['ownerRecord' => $restaurant, 'pageClass' => EditRestaurant::class])->assertOk();
        Livewire::test(HalalCertificatesRelationManager::class, ['ownerRecord' => $restaurant, 'pageClass' => EditRestaurant::class])->assertOk();
        Livewire::test(ViewRestaurantSubmission::class, ['record' => $pending->id])->assertOk();
        $this->get('/admin')->assertOk();
    }

    public function test_filament_halal_approval_requires_certificate_checklist_and_records_decision(): void
    {
        Notification::fake();
        Storage::fake(config('restaurant_photos.pending_disk'));
        Storage::fake(config('restaurant_photos.public_disk'));
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'web');
        $restaurant = $this->makeRestaurant();
        $report = $this->makeHalalReport($restaurant, $this->makeUser(), HalalStatus::Certified, 'pending', [
            'certification_authority' => 'jakim', 'certificate_number' => 'JAKIM-9', 'certificate_expires_at' => now()->addYear()->toDateString(),
        ]);
        app(RestaurantPhotoUploadService::class)->storePending($report, UploadedFile::fake()->image('c.jpg'), 'halal_cert', $report->user_id);

        // Confirmation unticked -> validation error, nothing recorded.
        Livewire::test(ViewRestaurantSubmission::class, ['record' => $report->id])
            ->callAction('approveHalal', data: ['resolved_status' => 'certified'])
            ->assertHasActionErrors(['confirmed']);
        $this->assertSame('pending', $report->fresh()->status);

        // Confirmed; the reporter's prefilled details are kept (they're optional, not required).
        Livewire::test(ViewRestaurantSubmission::class, ['record' => $report->id])
            ->callAction('approveHalal', data: ['resolved_status' => 'certified', 'confirmed' => true])
            ->assertHasNoActionErrors();

        $this->assertSame('approved', $report->fresh()->status);
        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Certified, $fresh->halal_status);
        $this->assertSame('JAKIM-9', $fresh->activeHalalCertificate->certificate_number);
        $this->assertNotNull($report->photos()->first()->restaurant_id); // evidence promoted
    }

    public function test_admin_override_from_restaurant_page(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');
        $restaurant = $this->makeRestaurant();

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('halalOverride', data: ['status' => 'non_halal', 'reason' => ''])
            ->assertHasActionErrors(['reason']);

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('halalOverride', data: ['status' => 'non_halal', 'reason' => 'Visited — pork on menu'])
            ->assertHasNoActionErrors();

        $this->assertSame(HalalStatus::NonHalal, $restaurant->fresh()->halal_status);
    }

    public function test_admin_can_certify_from_restaurant_page_without_certificate_details(): void
    {
        $this->actingAs($this->makeAdmin(), 'web');
        $restaurant = $this->makeRestaurant();

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('halalOverride', data: ['status' => 'certified', 'reason' => 'Known certified, no copy of cert', 'verification_method' => 'admin_attestation'])
            ->assertHasActionErrors(['confirmed']);

        Livewire::test(EditRestaurant::class, ['record' => $restaurant->id])
            ->callAction('halalOverride', data: ['status' => 'certified', 'confirmed' => true, 'reason' => 'Known certified, no copy of cert', 'verification_method' => 'admin_attestation'])
            ->assertHasNoActionErrors();

        $fresh = $restaurant->fresh();
        $this->assertSame(HalalStatus::Certified, $fresh->halal_status);
        $this->assertNull($fresh->halal_active_certificate_id);
    }
}
