<?php

namespace App\Services;

use App\Models\Cuisine;
use App\Models\PlaceSyncArea;
use App\Models\Restaurant;
use App\Models\Tag;
use App\Services\Places\FixturePlacesProvider;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * Orchestrates provider selection, area-based caching, and persistence.
 * Providers only fetch; this is the only place that decides whether to
 * hit the network and the only place that writes to the database.
 */
class PlacesService
{
    public function __construct(private readonly PlaceNormalizer $normalizer)
    {
    }

    /**
     * @return array<int, array<string, mixed>> normalized restaurant arrays, shaped for RecommendationService
     */
    public function nearbyRestaurants(float $latitude, float $longitude, float $radiusKm): array
    {
        $provider = Config::get('services.places.provider', 'fixture');

        if ($provider === 'fixture') {
            return (new FixturePlacesProvider)->nearbyRestaurants($latitude, $longitude, $radiusKm)->all();
        }

        if ($provider !== 'google') {
            throw new RuntimeException("Unknown PLACES_PROVIDER [{$provider}].");
        }

        $apiKey = Config::get('services.places.google_api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('PLACES_PROVIDER=google requires GOOGLE_PLACES_API_KEY to be set.');
        }

        if (! $this->isAreaCovered($latitude, $longitude, $radiusKm)) {
            $this->syncFromGoogle($latitude, $longitude, $radiusKm, $apiKey);
        }

        return $this->readGoogleRestaurantsNear($latitude, $longitude, $radiusKm);
    }

    private function isAreaCovered(float $latitude, float $longitude, float $radiusKm): bool
    {
        $cacheHours = (int) Config::get('services.places.cache_hours', 24);
        $cutoff = now()->subHours($cacheHours);

        return PlaceSyncArea::where('provider', 'google')
            ->where('synced_at', '>=', $cutoff)
            ->get()
            ->contains(function (PlaceSyncArea $area) use ($latitude, $longitude, $radiusKm) {
                $distanceToCenter = RecommendationService::distanceKm(
                    (float) $area->latitude, (float) $area->longitude, $latitude, $longitude
                );

                // Covered only if the requested search circle sits fully inside the already-synced one.
                return $distanceToCenter + $radiusKm <= (float) $area->radius_km;
            });
    }

    private function syncFromGoogle(float $latitude, float $longitude, float $radiusKm, string $apiKey): void
    {
        $providerPlaces = (new GooglePlacesProvider($apiKey))->nearbyRestaurants($latitude, $longitude, $radiusKm);
        $normalized = $this->normalizer->normalize($providerPlaces);

        foreach ($normalized as $data) {
            $this->upsertRestaurant($data);
        }

        PlaceSyncArea::create([
            'provider' => 'google',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
            'synced_at' => now(),
        ]);
    }

    private function upsertRestaurant(array $data): void
    {
        $restaurant = Restaurant::updateOrCreate(
            ['provider' => 'google', 'provider_place_id' => $data['provider_place_id']],
            [
                'name' => $data['name'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'price_level' => $data['price_level'],
                'rating' => $data['rating'],
                'is_active' => $data['is_active'],
                'opening_hours' => $data['opening_hours'],
                'last_synced_at' => now(),
            ]
        );

        $cuisineIds = collect($data['cuisines'])->map(
            fn (string $slug) => Cuisine::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id
        );
        $restaurant->cuisines()->sync($cuisineIds);

        $tagIds = collect($data['tags'])->map(
            fn (string $name) => Tag::firstOrCreate(['name' => $name])->id
        );
        $restaurant->tags()->sync($tagIds);
    }

    private function readGoogleRestaurantsNear(float $latitude, float $longitude, float $radiusKm): array
    {
        return Restaurant::where('provider', 'google')
            ->where('is_active', true)
            ->with(['cuisines', 'tags'])
            ->get()
            ->filter(fn (Restaurant $restaurant) => RecommendationService::distanceKm(
                $latitude, $longitude, (float) $restaurant->latitude, (float) $restaurant->longitude
            ) <= $radiusKm)
            ->map(fn (Restaurant $restaurant) => $restaurant->toRecommendationArray())
            ->values()
            ->all();
    }
}
