<?php

namespace App\Support;

/**
 * What kind of thing a resolved craving concept is. Only Dish/Category are populated today;
 * Attribute (spicy/cheap/halal-style modifiers) and Cuisine (explicit cuisine picks) are
 * reserved so future taxonomy growth doesn't need another field rename.
 */
enum FoodConceptKind: string
{
    case Dish = 'dish';
    case Category = 'category';
    case Attribute = 'attribute';
    case Cuisine = 'cuisine';
}
