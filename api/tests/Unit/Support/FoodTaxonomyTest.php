<?php

namespace Tests\Unit\Support;

use App\Support\FoodConceptKind;
use App\Support\FoodTaxonomy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FoodTaxonomyTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> input => expected concept */
    public static function resolvableTextProvider(): array
    {
        return [
            'dish: nasi kandar' => ['nasi kandar', 'nasi_kandar'],
            'dish: ayam gepuk' => ['ayam gepuk', 'ayam_gepuk'],
            'category: ice cream' => ['ice cream', 'ice_cream'],
            'category: gelato' => ['gelato', 'ice_cream'],
            'category: froyo' => ['froyo', 'ice_cream'],
            'category: aiskrim (Manglish)' => ['aiskrim', 'ice_cream'],
            'category: cheeseburger' => ['cheeseburger', 'burger'],
            'sentence containing a concept' => ['i want some ice cream please', 'ice_cream'],
        ];
    }

    #[DataProvider('resolvableTextProvider')]
    public function test_resolves_known_text_to_expected_concept(string $input, string $expectedConcept): void
    {
        $result = FoodTaxonomy::resolve($input);

        $this->assertNotNull($result);
        $this->assertSame($expectedConcept, $result['concept']);
        $this->assertSame(1.0, $result['confidence']);
    }

    public function test_longest_alias_wins_over_shorter_shadowing_alias(): void
    {
        // "ice cream" (9 chars) must win over "dessert" (7 chars) when both appear.
        $result = FoodTaxonomy::resolve('ice cream dessert combo');

        $this->assertSame('ice_cream', $result['concept']);
    }

    public function test_unrelated_text_resolves_to_null(): void
    {
        $this->assertNull(FoodTaxonomy::resolve('roti john cheese banjir'));
    }

    public function test_short_alias_does_not_false_positive_inside_an_unrelated_word(): void
    {
        // "sweet" is an alias for dessert — must not match merely because it's a substring of
        // "unsweetened" (no word boundary there).
        $this->assertNull(FoodTaxonomy::resolve('something unsweetened please'));
    }

    public function test_short_alias_matches_as_a_standalone_word(): void
    {
        $result = FoodTaxonomy::resolve('i want something sweet');

        $this->assertSame('dessert', $result['concept']);
    }

    public function test_dish_concept_kind_is_dish(): void
    {
        $result = FoodTaxonomy::resolve('nasi lemak');

        $this->assertSame(FoodConceptKind::Dish, $result['kind']);
    }

    public function test_category_concept_kind_is_category(): void
    {
        $result = FoodTaxonomy::resolve('pizza');

        $this->assertSame(FoodConceptKind::Category, $result['kind']);
    }

    public function test_search_terms_for_returns_trusted_query_terms(): void
    {
        $this->assertSame(['ice cream'], FoodTaxonomy::searchTermsFor('ice_cream'));
        $this->assertSame([], FoodTaxonomy::searchTermsFor('not_a_real_concept'));
    }

    public function test_food_category_for_ice_cream_points_at_broader_dessert_bucket(): void
    {
        // Google's ice_cream_shop only ever normalizes to the coarse 'dessert' food_category,
        // never 'ice_cream' — the taxonomy's mapping must match that reality.
        $this->assertSame('dessert', FoodTaxonomy::foodCategoryFor('ice_cream'));
        $this->assertNull(FoodTaxonomy::foodCategoryFor('nasi_kandar'));
    }

    public function test_alias_patterns_only_include_dish_concepts(): void
    {
        $patterns = FoodTaxonomy::aliasPatterns();

        $this->assertArrayHasKey('nasi_kandar', $patterns);
        $this->assertArrayNotHasKey('burger', $patterns);
        $this->assertArrayNotHasKey('ice_cream', $patterns);
    }

    /** @return array<string, array{0: string}> */
    public static function nasiKandarNameVariantProvider(): array
    {
        return [
            'single space' => ['Nasi Kandar Pelita'],
            'no space (trailing token)' => ['NasiKandar'],
            'double space' => ['Nasi  Kandar Express'],
            'different case' => ['NASI KANDAR BERATUR PANJANG'],
        ];
    }

    #[DataProvider('nasiKandarNameVariantProvider')]
    public function test_alias_patterns_tolerate_spacing_variance_in_multi_word_aliases(string $name): void
    {
        $patterns = FoodTaxonomy::aliasPatterns()['nasi_kandar'];

        $matched = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, "Expected a nasi_kandar alias pattern to match \"{$name}\"");
    }

    public function test_exists_reflects_concept_membership(): void
    {
        $this->assertTrue(FoodTaxonomy::exists('ice_cream'));
        $this->assertFalse(FoodTaxonomy::exists('not_a_real_concept'));
    }
}
