<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TermsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private const TERMS_REQUIRED = [
        'message' => 'Please agree to the Terms of Use and Community Guidelines first.',
        'code' => 'terms_required',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('legal.terms_version', '2026-09-26');
        Config::set('legal.guidelines_version', '2026-09-26');
        Config::set('legal.privacy_version', '2026-09-26');
    }

    private function signInWithoutAgreement(): User
    {
        $user = User::factory()->unacceptedTerms()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function currentVersions(): array
    {
        return ['termsVersion' => '2026-09-26', 'guidelinesVersion' => '2026-09-26', 'privacyVersion' => '2026-09-26'];
    }

    public function test_me_reports_current_versions_and_that_agreement_is_needed(): void
    {
        $this->signInWithoutAgreement();

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.legal', [
                'termsVersion' => '2026-09-26',
                'guidelinesVersion' => '2026-09-26',
                'privacyVersion' => '2026-09-26',
                'needsAcceptance' => true,
            ]);
    }

    public function test_agreeing_to_current_terms_records_it_and_clears_needs_acceptance(): void
    {
        $user = $this->signInWithoutAgreement();

        $this->postJson('/api/v1/me/terms-acceptance', [...$this->currentVersions(), 'appVersion' => '1.0 (12)'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.legal.needsAcceptance', false);

        $this->assertDatabaseHas('terms_acceptances', [
            'user_id' => $user->id,
            'terms_version' => '2026-09-26',
            'guidelines_version' => '2026-09-26',
            'privacy_version' => '2026-09-26',
            'context' => 'contribution',
            'app_version' => '1.0 (12)',
        ]);
    }

    public function test_agreeing_to_a_stale_version_is_refused(): void
    {
        $user = $this->signInWithoutAgreement();

        $this->postJson('/api/v1/me/terms-acceptance', [...$this->currentVersions(), 'guidelinesVersion' => '2026-01-01'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The terms were updated. Please review them again.']);

        $this->assertSame(0, $user->termsAcceptances()->count());
    }

    public function test_agreement_requires_all_three_versions(): void
    {
        $this->signInWithoutAgreement();

        $this->postJson('/api/v1/me/terms-acceptance', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['termsVersion', 'guidelinesVersion', 'privacyVersion']);
    }

    public function test_guest_cannot_agree_to_terms(): void
    {
        Sanctum::actingAs(User::factory()->guest()->unacceptedTerms()->create(), ['*']);

        $this->postJson('/api/v1/me/terms-acceptance', $this->currentVersions())
            ->assertForbidden()
            ->assertJsonPath('code', 'account_required');

        $this->assertSame(0, TermsAcceptance::count());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function contributionRoutes(): array
    {
        return [
            'publish community post' => ['post', '/api/v1/community/posts'],
            'request a community' => ['post', '/api/v1/community/requests'],
            'add a place' => ['post', '/api/v1/community/submissions'],
            'edit a submission' => ['patch', '/api/v1/community/submissions/{submission}'],
            'submit a submission' => ['post', '/api/v1/community/submissions/{submission}/submit'],
            'submission photo' => ['post', '/api/v1/community/submissions/{submission}/photos'],
            'quick-add photo' => ['post', '/api/v1/restaurants/{restaurant}/photos/quick-add'],
            'halal report' => ['post', '/api/v1/restaurants/{restaurant}/halal-reports'],
            'owner claim' => ['post', '/api/v1/restaurants/{restaurant}/owner-claim'],
        ];
    }

    #[DataProvider('contributionRoutes')]
    public function test_contributions_need_an_agreement_first(string $method, string $uri): void
    {
        $user = $this->signInWithoutAgreement();
        $restaurant = Restaurant::create(['name' => 'Kedai Kopi', 'latitude' => 2.9, 'longitude' => 101.7, 'is_active' => true]);
        $submission = RestaurantSubmission::create([
            'user_id' => $user->id, 'submission_type' => 'new_place', 'source_type' => 'manual', 'status' => 'draft',
            'name' => 'Kedai Baru', 'latitude' => 2.9, 'longitude' => 101.7, 'location_source' => 'map_pin',
        ]);

        $this->json($method, str_replace(['{restaurant}', '{submission}'], [$restaurant->id, $submission->id], $uri))
            ->assertForbidden()
            ->assertExactJson(self::TERMS_REQUIRED);
    }

    public function test_contribution_goes_through_after_agreeing(): void
    {
        $this->signInWithoutAgreement();
        $this->postJson('/api/v1/me/terms-acceptance', $this->currentVersions())->assertOk();

        $this->postJson('/api/v1/community/requests', ['type' => 'university', 'name' => 'UiTM Shah Alam'])
            ->assertOk()
            ->assertJson(['requested' => true]);
    }

    public function test_reading_own_submissions_does_not_need_an_agreement(): void
    {
        $this->signInWithoutAgreement();

        $this->getJson('/api/v1/community/submissions/mine')->assertOk();
    }

    public function test_new_terms_version_requires_agreeing_again(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.legal.needsAcceptance', false);

        Config::set('legal.terms_version', '2026-12-01');

        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.legal.needsAcceptance', true);
        $this->postJson('/api/v1/community/requests', ['type' => 'university', 'name' => 'UiTM Shah Alam'])
            ->assertForbidden()
            ->assertExactJson(self::TERMS_REQUIRED);
    }

    public function test_new_privacy_version_alone_does_not_block_contributing(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        Config::set('legal.privacy_version', '2026-12-01');

        $this->getJson('/api/v1/auth/me')->assertJsonPath('user.legal.needsAcceptance', false);
        $this->postJson('/api/v1/community/requests', ['type' => 'university', 'name' => 'UiTM Shah Alam'])->assertOk();
    }
}
