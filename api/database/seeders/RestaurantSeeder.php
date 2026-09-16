<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use App\Models\Restaurant;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('../fixtures/restaurants.json');
        $entries = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($entries as $entry) {
            $restaurant = Restaurant::create([
                'name' => $entry['name'],
                'signature_dish' => $entry['signatureDish'] ?? null,
                'food_category' => $entry['foodCategory'] ?? null,
                'latitude' => $entry['latitude'],
                'longitude' => $entry['longitude'],
                'address' => $entry['address'] ?? null,
                'price_level' => $entry['priceLevel'] ?? null,
                'rating' => $entry['rating'] ?? null,
                'opening_hours' => $entry['openingHours'] ?? null,
                'is_active' => $entry['isActive'] ?? true,
                'provider' => $entry['provider'] ?? 'fixture',
                'provider_place_id' => $entry['providerPlaceId'] ?? null,
            ]);

            $cuisineIds = collect($entry['cuisines'] ?? [])->map(
                fn (string $slug) => Cuisine::firstOrCreate(
                    ['slug' => $slug],
                    ['name' => Str::headline($slug)]
                )->id
            );
            $restaurant->cuisines()->sync($cuisineIds);

            $tagIds = collect($entry['tags'] ?? [])->map(
                fn (string $name) => Tag::firstOrCreate(['name' => $name])->id
            );
            $restaurant->tags()->sync($tagIds);
        }
    }
}
