<?php

namespace App\Support;

/**
 * Single source of truth for food concepts — dishes (specific, Malaysian) and categories
 * (broader, e.g. dessert/burger). Consumed by PlaceNormalizer (Google place name -> dish tag,
 * dish concepts only), CravingResolver (free-text craving -> concept), and
 * RecommendationService (concept -> relevance scoring). One definition, so none of those three
 * can silently drift from the others.
 *
 * Replaces the earlier DishCatalog, which only covered the 8 dish entries below.
 */
final class FoodTaxonomy
{
    /** @var array<string, array{kind: FoodConceptKind, label: string, aliases: string[], searchTerms: string[], placeTypes: string[], foodCategory: ?string}> */
    public const CONCEPTS = [
        // Dishes — narrow, Malaysian-specific. Also drive PlaceNormalizer's name-inference exception.
        // No foodCategory equivalent exists in PlaceNormalizer::FOOD_CATEGORY_MAP for these.
        'nasi_kandar' => ['kind' => FoodConceptKind::Dish, 'label' => 'Nasi Kandar', 'aliases' => ['nasi kandar'], 'searchTerms' => ['nasi kandar'], 'placeTypes' => [], 'foodCategory' => null],
        'ayam_gepuk' => ['kind' => FoodConceptKind::Dish, 'label' => 'Ayam Gepuk', 'aliases' => ['ayam gepuk'], 'searchTerms' => ['ayam gepuk'], 'placeTypes' => [], 'foodCategory' => null],
        'nasi_padang' => ['kind' => FoodConceptKind::Dish, 'label' => 'Nasi Padang', 'aliases' => ['nasi padang'], 'searchTerms' => ['nasi padang'], 'placeTypes' => [], 'foodCategory' => null],
        'mee_goreng' => ['kind' => FoodConceptKind::Dish, 'label' => 'Mee Goreng', 'aliases' => ['mee goreng'], 'searchTerms' => ['mee goreng'], 'placeTypes' => [], 'foodCategory' => null],
        'nasi_lemak' => ['kind' => FoodConceptKind::Dish, 'label' => 'Nasi Lemak', 'aliases' => ['nasi lemak'], 'searchTerms' => ['nasi lemak'], 'placeTypes' => [], 'foodCategory' => null],
        'char_kuey_teow' => ['kind' => FoodConceptKind::Dish, 'label' => 'Char Kuey Teow', 'aliases' => ['char kuey teow', 'char koay teow'], 'searchTerms' => ['char kuey teow'], 'placeTypes' => [], 'foodCategory' => null],
        'banana_leaf_rice' => ['kind' => FoodConceptKind::Dish, 'label' => 'Banana Leaf Rice', 'aliases' => ['banana leaf rice', 'banana leaf'], 'searchTerms' => ['banana leaf rice'], 'placeTypes' => [], 'foodCategory' => null],
        'dim_sum' => ['kind' => FoodConceptKind::Dish, 'label' => 'Dim Sum', 'aliases' => ['dim sum'], 'searchTerms' => ['dim sum'], 'placeTypes' => [], 'foodCategory' => null],

        // Categories — broader, not Malaysia-specific. Fixes the "ice cream -> burger" gap:
        // PlaceNormalizer already derives food_category from these place types (FOOD_CATEGORY_MAP);
        // craving matching just never looked at it before. foodCategory here is that same coarse
        // value — Google's ice_cream_shop only ever normalizes to 'dessert', never 'ice_cream',
        // so ice_cream's foodCategory intentionally points at the broader bucket it actually lands in.
        'dessert' => ['kind' => FoodConceptKind::Category, 'label' => 'Dessert', 'aliases' => ['dessert', 'something sweet', 'sweet'], 'searchTerms' => ['dessert'], 'placeTypes' => ['dessert_restaurant', 'bakery'], 'foodCategory' => 'dessert'],
        'ice_cream' => ['kind' => FoodConceptKind::Category, 'label' => 'Ice Cream', 'aliases' => ['ice cream', 'gelato', 'soft serve', 'frozen yogurt', 'froyo', 'aiskrim'], 'searchTerms' => ['ice cream'], 'placeTypes' => ['ice_cream_shop'], 'foodCategory' => 'dessert'],
        'burger' => ['kind' => FoodConceptKind::Category, 'label' => 'Burger', 'aliases' => ['burger', 'cheeseburger', 'hamburger'], 'searchTerms' => ['burger'], 'placeTypes' => ['hamburger_restaurant'], 'foodCategory' => 'burger'],
        'pizza' => ['kind' => FoodConceptKind::Category, 'label' => 'Pizza', 'aliases' => ['pizza'], 'searchTerms' => ['pizza'], 'placeTypes' => ['pizza_restaurant'], 'foodCategory' => 'pizza'],
        'sushi' => ['kind' => FoodConceptKind::Category, 'label' => 'Sushi', 'aliases' => ['sushi'], 'searchTerms' => ['sushi'], 'placeTypes' => ['sushi_restaurant'], 'foodCategory' => 'sushi'],
        'ramen' => ['kind' => FoodConceptKind::Category, 'label' => 'Ramen', 'aliases' => ['ramen'], 'searchTerms' => ['ramen'], 'placeTypes' => ['ramen_restaurant'], 'foodCategory' => 'ramen'],
        'seafood' => ['kind' => FoodConceptKind::Category, 'label' => 'Seafood', 'aliases' => ['seafood'], 'searchTerms' => ['seafood'], 'placeTypes' => ['seafood_restaurant'], 'foodCategory' => 'seafood'],
        'chicken' => ['kind' => FoodConceptKind::Category, 'label' => 'Chicken', 'aliases' => ['fried chicken', 'chicken'], 'searchTerms' => ['fried chicken'], 'placeTypes' => ['chicken_restaurant'], 'foodCategory' => 'chicken'],
    ];

    /**
     * Deterministic alias match against arbitrary free text, on word boundaries — plain
     * substring containment would let a short alias like "sweet" false-positive inside
     * "unsweetened". Longest matched alias wins when several concepts match (e.g. "ice cream"
     * beats "dessert" inside "ice cream dessert") so a more specific concept isn't shadowed by
     * a broader one. Any hit is treated as fully confident (1.0) — this is exact matching
     * against our own controlled alias list, not a guess.
     *
     * @return array{concept: string, kind: FoodConceptKind, confidence: float, searchTerms: string[], placeTypes: string[]}|null
     */
    public static function resolve(string $normalizedText): ?array
    {
        $best = null;
        $bestAliasLength = -1;

        foreach (self::CONCEPTS as $concept => $entry) {
            foreach ($entry['aliases'] as $alias) {
                $pattern = '/\b'.preg_quote($alias, '/').'\b/i';
                if (preg_match($pattern, $normalizedText) === 1 && strlen($alias) > $bestAliasLength) {
                    $best = $concept;
                    $bestAliasLength = strlen($alias);
                }
            }
        }

        if ($best === null) {
            return null;
        }

        $entry = self::CONCEPTS[$best];

        return [
            'concept' => $best,
            'kind' => $entry['kind'],
            'confidence' => 1.0,
            'searchTerms' => $entry['searchTerms'],
            'placeTypes' => $entry['placeTypes'],
        ];
    }

    /**
     * The only function allowed to turn a concept into Google query text — both the
     * deterministic path and the AI path (OpenRouterIntentParser) call this rather than ever
     * sending free-form text to Google directly.
     *
     * @return string[]
     */
    public static function searchTermsFor(string $concept): array
    {
        return self::CONCEPTS[$concept]['searchTerms'] ?? [];
    }

    public static function label(string $concept): ?string
    {
        return self::CONCEPTS[$concept]['label'] ?? null;
    }

    public static function foodCategoryFor(string $concept): ?string
    {
        return self::CONCEPTS[$concept]['foodCategory'] ?? null;
    }

    public static function exists(string $concept): bool
    {
        return isset(self::CONCEPTS[$concept]);
    }

    /**
     * Word/phrase-boundary regexes derived from Dish-kind aliases only, for
     * PlaceNormalizer's narrow name-inference exception. Category concepts are deliberately
     * excluded — Google's own place types already cover "burger"/"pizza"/etc., so inferring
     * those from a restaurant's name too would be redundant scope creep for a method whose
     * contract is "dish names only."
     *
     * @return array<string, string[]> concept => list of regex patterns
     */
    public static function aliasPatterns(): array
    {
        $patterns = [];
        foreach (self::CONCEPTS as $concept => $entry) {
            if ($entry['kind'] !== FoodConceptKind::Dish) {
                continue;
            }
            $patterns[$concept] = array_map(
                fn (string $alias) => '/\b'.implode(
                    '\s*',
                    array_map(fn (string $word) => preg_quote($word, '/'), preg_split('/\s+/', $alias))
                ).'\b/i',
                $entry['aliases']
            );
        }

        return $patterns;
    }
}
