<?php

namespace Tests\Unit\Support;

use App\Support\AreaPersonality;
use Tests\TestCase;

class AreaPersonalityTest extends TestCase
{
    public function test_zero_places_returns_no_tags(): void
    {
        $this->assertSame([], AreaPersonality::forSummary(0, 0, []));
    }

    public function test_budget_tag_requires_minimum_sample_size_even_at_100_percent_ratio(): void
    {
        $tags = AreaPersonality::forSummary(placeCount: 2, budgetFriendlyCount: 2, topCategories: []);

        $this->assertEmpty(array_filter($tags, fn (array $t) => $t['key'] === 'budget_friendly'));
    }

    public function test_budget_tag_appears_once_minimum_sample_and_ratio_are_met(): void
    {
        $tags = AreaPersonality::forSummary(placeCount: 5, budgetFriendlyCount: 3, topCategories: []);

        $this->assertSame(['key' => 'budget_friendly', 'label' => 'Budget-friendly'], $tags[0]);
    }

    public function test_budget_tag_absent_below_the_ratio_threshold(): void
    {
        $tags = AreaPersonality::forSummary(placeCount: 10, budgetFriendlyCount: 4, topCategories: []);

        $this->assertEmpty(array_filter($tags, fn (array $t) => $t['key'] === 'budget_friendly'));
    }

    public function test_category_tag_requires_minimum_category_count(): void
    {
        $tags = AreaPersonality::forSummary(placeCount: 10, budgetFriendlyCount: 0, topCategories: [
            ['label' => 'Mamak', 'count' => 2],
        ]);

        $this->assertEmpty(array_filter($tags, fn (array $t) => $t['key'] === 'category_heavy'));
    }

    public function test_category_tag_appears_once_minimum_count_is_met(): void
    {
        $tags = AreaPersonality::forSummary(placeCount: 10, budgetFriendlyCount: 0, topCategories: [
            ['label' => 'Mamak', 'count' => 3],
        ]);

        $this->assertContains(['key' => 'category_heavy', 'label' => 'Mamak-heavy'], $tags);
    }
}
