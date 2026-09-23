<?php

namespace Tests\Unit\Support;

use App\Support\Halal\HalalHeuristic;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HalalHeuristicTest extends TestCase
{
    public static function flagged(): array
    {
        return [
            'bak kut teh in name' => [['name' => 'Restoran Bak Kut Teh Klang'], 'name', 'bak kut teh'],
            'pork' => [['name' => 'Ah Seng Pork Noodle'], 'name', 'pork'],
            'non-halal hyphenated' => [['name' => 'Kopitiam (Non-Halal)'], 'name', 'non halal'],
            'char siu dish' => [['name' => 'Wong Kee', 'signature_dish' => 'Char Siu Rice'], 'signature_dish', 'char siu'],
            'night club type' => [['name' => 'Zouk', 'google_types' => ['night_club']], 'google_types', 'night_club'],
        ];
    }

    #[DataProvider('flagged')]
    public function test_strong_signals_flag_non_halal(array $restaurant, string $field, string $term): void
    {
        $result = HalalHeuristic::evaluate($restaurant);

        $this->assertTrue($result->likelyNonHalal);
        $this->assertContains(['field' => $field, 'term' => $term, 'strength' => 'strong'], $result->matches);
    }

    public static function notFlagged(): array
    {
        return [
            'barbeque is not bar' => [['name' => 'Barbeque Nation']],
            'nasi kandar' => [['name' => 'Nasi Kandar Pelita']],
            'weak wine only' => [['name' => 'Wine & Dine Café']],
            'weak pub only' => [['name' => 'The Pub Grill']],
            'babylon is not babi' => [['name' => 'Babylon Kebab']],
            'empty' => [['name' => '']],
        ];
    }

    #[DataProvider('notFlagged')]
    public function test_weak_or_substring_matches_do_not_classify(array $restaurant): void
    {
        $this->assertFalse(HalalHeuristic::evaluate($restaurant)->likelyNonHalal);
    }

    public function test_weak_matches_are_still_reported_for_admins(): void
    {
        $result = HalalHeuristic::evaluate(['name' => 'Wine & Dine Café']);

        $this->assertSame([['field' => 'name', 'term' => 'wine', 'strength' => 'weak']], $result->matches);
    }
}
