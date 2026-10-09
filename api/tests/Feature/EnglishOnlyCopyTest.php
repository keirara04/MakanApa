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
 * All user-facing API copy is plain English — no Malay/Manglish in nudges, reason lines or
 * context labels. Proper nouns (MakanApa, dish names) are fine; input parsing is out of scope.
 */
class EnglishOnlyCopyTest extends TestCase
{
    use RefreshDatabase;

    private const MALAY_WORDS = [
        'dah', 'hujan', 'jumaat', 'kat', 'mana', 'malam', 'perut', 'basah', 'dekat', 'hujung', 'bulan',
        'makan', 'jom', 'lah', 'tak', 'nak', 'je', 'puasa', 'buka', 'sangat', 'ni', 'apa', 'bunyi', 'lepas', 'sedap', 'aiyo',
    ];

    public function test_every_nudge_variant_is_english(): void
    {
        $place = ['name' => 'Nasi Kandar Pelita', 'distanceKm' => 0.4, 'closesAt' => '22:00'];
        $seen = [];

        // A Friday and a non-Friday, across enough users to hit every variant of every key.
        foreach (['2026-09-23', '2026-09-25'] as $date) {
            $day = CarbonImmutable::parse($date, 'Asia/Kuala_Lumpur');
            foreach (range(1, 60) as $userId) {
                foreach ([['lunch', $place, false], ['lunch', $place, true], ['dinner', $place, false], ['dinner', $place, true],
                    ['iftar', $place, false], ['lunch', null, false], ['dinner', null, false], ['iftar', null, false]] as [$slot, $p, $rain]) {
                    $copy = NudgeCopyCatalog::compose($slot, $p, $rain, $day, $userId);
                    $seen[$copy['key']] = true;
                    $this->assertEnglish($copy['title'].' '.$copy['body']);
                }
            }
        }

        $this->assertEqualsCanonicalizing(NudgeCopyCatalog::keys(), array_keys($seen));
    }

    public function test_every_reason_line_is_english(): void
    {
        $keys = ['craving_match', 'mood_match', 'selera_slot', 'pulse', 'selera_match', 'lens_cheap_today', 'lens_treat_myself',
            'lens_surprise_me', 'lens_quick_one', 'lens_community_favs', 'close', 'rating', 'budget_fit', 'community_picks',
            'hidden_gem', 'popular', 'halal_verified', 'ctx_rain', 'ctx_supper', 'ctx_friday', 'ctx_iftar', 'ctx_sahur',
            'ctx_month_end', 'novelty', 'fatigue', 'wildcard'];

        foreach ([1, 2] as $rank) {
            $facts = ['craving' => 'laksa', 'slot' => 'lunch', 'category' => 'noodles', 'distanceM' => 400, 'poolSize' => 5,
                'community' => 'UM', 'rank' => $rank, 'rating' => 4.5, 'pickers' => 3, 'streak' => 'rice', 'priceLevel' => 1];
            foreach (range(1, 40) as $seed) {
                $reasons = array_map(fn ($key) => ['family' => 'match', 'key' => $key, 'facts' => $facts], $keys);
                $rendered = ReasonCatalog::render(['reasons' => $reasons], $seed, true, 'rice');
                $this->assertCount(count($keys) + 1, $rendered['reasons']);
                foreach ($rendered['reasons'] as $reason) {
                    $this->assertEnglish($reason['text']);
                }
            }
        }
    }

    public function test_every_context_label_is_english(): void
    {
        $signals = array_fill_keys(['rain', 'supper', 'friday', 'iftar', 'sahur', 'month_end'],
            ['active' => true, 'confidence' => 1.0, 'source' => 'rules', 'ageMinutes' => null]);

        $labels = array_column(ContextEngine::present(new ContextSnapshot('lunch', '12:30', $signals, now()->toIso8601String(), null, null)), 'label');

        $this->assertCount(6, $labels);
        foreach ($labels as $label) {
            $this->assertEnglish($label);
        }
    }

    public function test_profile_update_ignores_the_removed_plain_english_setting(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->patchJson('/api/v1/me/profile', ['plainEnglish' => true])
            ->assertOk()
            ->assertJsonMissingPath('user.plainEnglish');
    }

    private function assertEnglish(string $text): void
    {
        foreach (self::MALAY_WORDS as $word) {
            $this->assertDoesNotMatchRegularExpression('/\b'.$word.'\b/iu', $text, "Malay word \"{$word}\" in: {$text}");
        }
    }
}
