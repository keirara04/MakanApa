<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Brain\ContextEngine;
use App\Services\Brain\ContextSnapshot;
use App\Services\Brain\ReasonCatalog;
use App\Services\Nudges\NudgeCopyCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Settings → Plain English: Manglish stays the default everywhere; the setting only swaps the
 * Malay-only lines (context labels, reason lines, mealtime pushes) for English.
 */
class PlainEnglishTest extends TestCase
{
    use RefreshDatabase;

    /** Words that only appear in the Malay-only lines Plain English replaces. */
    private const MALAY_ONLY = ['dah', 'Hujan', 'Jumaat', 'kat mana', 'malam', 'Perut', 'tak basah', 'dekat je', 'Hujung'];

    public function test_profile_update_saves_and_returns_the_setting(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/me/profile', ['plainEnglish' => true])
            ->assertOk()
            ->assertJsonPath('user.plainEnglish', true)
            ->assertJsonPath('user.halalPreference', false);

        $this->assertTrue($user->fresh()->plain_english);
    }

    public function test_new_accounts_default_to_manglish(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.plainEnglish', false);
    }

    public function test_plain_english_nudges_never_use_malay_only_lines(): void
    {
        $place = ['name' => 'Nasi Kandar Pelita', 'distanceKm' => 0.4, 'closesAt' => null];
        $sawManglish = false;

        // Friday + rain + iftar + generic + normal, across enough users to hit every variant.
        foreach (['2026-09-23', '2026-09-25'] as $date) {
            $day = CarbonImmutable::parse($date, 'Asia/Kuala_Lumpur');
            foreach (range(1, 60) as $userId) {
                foreach ([['lunch', $place, false], ['lunch', $place, true], ['dinner', $place, false], ['dinner', $place, true],
                    ['iftar', $place, false], ['lunch', null, false], ['dinner', null, false], ['iftar', null, false]] as [$slot, $p, $rain]) {
                    $manglish = NudgeCopyCatalog::compose($slot, $p, $rain, $day, $userId);
                    $plain = NudgeCopyCatalog::compose($slot, $p, $rain, $day, $userId, true);

                    $this->assertSame($manglish['key'], $plain['key']);
                    foreach (self::MALAY_ONLY as $word) {
                        $this->assertStringNotContainsString($word, $plain['title'].' '.$plain['body']);
                        $sawManglish = $sawManglish || str_contains($manglish['title'].' '.$manglish['body'], $word);
                    }
                }
            }
        }

        $this->assertTrue($sawManglish, 'The default copy should still be Manglish.');
    }

    public function test_context_labels_follow_the_setting(): void
    {
        $snapshot = new ContextSnapshot('lunch', '12:30', [
            'rain' => ['active' => true, 'confidence' => 1.0, 'source' => 'weather', 'ageMinutes' => 5],
            'month_end' => ['active' => true, 'confidence' => 1.0, 'source' => 'rules', 'ageMinutes' => null],
        ], now()->toIso8601String(), 5, 'test');

        $this->assertSame(['Hujan', 'Hujung bulan'], array_column(ContextEngine::present($snapshot), 'label'));
        $this->assertSame(['Rainy', 'Month-end'], array_column(ContextEngine::present($snapshot, true), 'label'));
    }

    public function test_reason_lines_follow_the_setting(): void
    {
        $facts = ['reasons' => [['family' => 'moment', 'key' => 'ctx_rain', 'facts' => []]]];

        // Two rain variants picked by seed — find the Malay one, then check its English swap.
        foreach (range(1, 50) as $seed) {
            $manglish = ReasonCatalog::render($facts, $seed)['reasons'][0]['text'];
            if ($manglish === 'Hujan — kept it close ☔') {
                $this->assertSame('Raining — kept it close ☔', ReasonCatalog::render($facts, $seed, plainEnglish: true)['reasons'][0]['text']);

                return;
            }
            $this->assertSame($manglish, ReasonCatalog::render($facts, $seed, plainEnglish: true)['reasons'][0]['text']);
        }

        $this->fail('No seed picked the Hujan variant.');
    }
}
