<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\Admin\AmbassadorApplicationController;
use App\Models\AmbassadorApplication;
use App\Models\Area;
use App\Models\University;
use App\Models\User;
use App\Models\UserAffiliation;
use App\Notifications\AmbassadorApplicationDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AmbassadorApplicationTest extends TestCase
{
    use RefreshDatabase;

    private University $ukm;

    private const REASON = 'I eat around campus every day and know all the good cheap spots.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ukm = University::create(['name' => 'Universiti Kebangsaan Malaysia', 'short_name' => 'UKM', 'country' => 'Malaysia', 'active' => true]);
    }

    private function member(array $attributes = []): User
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active', ...$attributes]);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'university', 'university_id' => $this->ukm->id]);

        return $user->fresh();
    }

    private function apply(User $user, array $overrides = [])
    {
        Sanctum::actingAs($user, ['*']);

        return $this->postJson('/api/v1/me/ambassador-application', ['reason' => self::REASON, ...$overrides]);
    }

    public function test_a_member_applies_for_their_own_community(): void
    {
        $user = $this->member();

        $this->apply($user, ['instagramHandle' => '@makan.ukm'])
            ->assertCreated()
            ->assertJsonPath('application.status', 'pending')
            ->assertJsonPath('application.communityType', 'university')
            ->assertJsonPath('application.communityName', 'UKM');

        $application = AmbassadorApplication::sole();
        $this->assertSame($this->ukm->id, $application->university_id);
        $this->assertSame('makan.ukm', $application->instagram_handle);
    }

    public function test_an_area_member_applies_for_their_area(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        UserAffiliation::create(['user_id' => $user->id, 'type' => 'area', 'area_id' => $kl->id]);

        $this->apply($user->fresh())->assertCreated()->assertJsonPath('application.communityName', 'KL');
    }

    public function test_show_returns_the_latest_application_or_null(): void
    {
        $user = $this->member();
        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/me/ambassador-application')->assertOk()->assertJsonPath('application', null);

        $this->apply($user);
        $this->getJson('/api/v1/me/ambassador-application')->assertOk()->assertJsonPath('application.status', 'pending');
    }

    public function test_guests_cannot_apply(): void
    {
        $guest = User::factory()->guest()->create();

        $this->apply($guest)->assertForbidden();
        $this->assertDatabaseCount('ambassador_applications', 0);
    }

    public function test_needs_a_community(): void
    {
        $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

        $this->apply($user)->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_one_pending_application_at_a_time(): void
    {
        $user = $this->member();
        $this->apply($user)->assertCreated();

        $this->apply($user)->assertUnprocessable();
        $this->assertDatabaseCount('ambassador_applications', 1);
    }

    public function test_an_ambassador_cannot_apply(): void
    {
        $user = $this->member(['ambassador_university_id' => $this->ukm->id]);

        $this->apply($user)->assertUnprocessable();
    }

    public function test_reason_must_say_something(): void
    {
        $this->apply($this->member(), ['reason' => 'pls'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_approving_makes_them_the_ambassador_and_tells_them(): void
    {
        Notification::fake();
        $user = $this->member();
        $this->apply($user)->assertCreated();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        app(AmbassadorApplicationController::class)->approve(AmbassadorApplication::sole(), $admin);

        $this->assertSame(['type' => 'university', 'name' => 'UKM'], $user->fresh()->ambassadorOf());
        $this->assertSame('approved', AmbassadorApplication::sole()->status);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'ambassador_application.approve']);
        Notification::assertSentTo($user, AmbassadorApplicationDecided::class);
    }

    public function test_declining_keeps_them_a_member_and_passes_on_the_note(): void
    {
        Notification::fake();
        $user = $this->member();
        $this->apply($user)->assertCreated();
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        app(AmbassadorApplicationController::class)->decline(AmbassadorApplication::sole(), 'We already have one for UKM.', $admin);

        $this->assertNull($user->fresh()->ambassadorOf());
        $this->assertSame('We already have one for UKM.', AmbassadorApplication::sole()->review_note);
        Notification::assertSentTo($user, AmbassadorApplicationDecided::class, function (AmbassadorApplicationDecided $notification) use ($user) {
            return $notification->toDatabase($user)['body'] === 'We already have one for UKM.';
        });

        Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/v1/me/ambassador-application')->assertJsonPath('application.reviewNote', 'We already have one for UKM.');
        $this->apply($user)->assertCreated();
    }

    public function test_approving_never_moves_someone_who_became_an_ambassador_elsewhere(): void
    {
        $user = $this->member();
        $this->apply($user)->assertCreated();
        $kl = Area::create(['name' => 'Kuala Lumpur', 'short_name' => 'KL', 'active' => true]);
        $user->update(['ambassador_area_id' => $kl->id]);
        $admin = User::factory()->create(['role' => 'superadmin', 'status' => 'active']);

        $this->expectException(\RuntimeException::class);
        try {
            app(AmbassadorApplicationController::class)->approve(AmbassadorApplication::sole(), $admin);
        } finally {
            $this->assertSame(['type' => 'area', 'name' => 'KL'], $user->fresh()->ambassadorOf());
            $this->assertSame('pending', AmbassadorApplication::sole()->status);
        }
    }
}
