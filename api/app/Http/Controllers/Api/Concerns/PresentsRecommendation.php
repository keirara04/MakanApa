<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Services\Places\GooglePlacesProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Shared winner-presentation logic between RecommendationController (Decide) and
 * NearbyController (Pick one lah) — both end a decision the same way: one restaurant,
 * enriched with photos/reviews, shaped into the same wire format the iOS ResultView expects.
 */
trait PresentsRecommendation
{
    /**
     * Winner-only Google Places Details fetch (photos/reviews/closing time) — never called
     * for the full candidate list, only the one restaurant actually being shown. Transient:
     * nothing this returns is written to the database.
     */
    private function enrichWinner(array $restaurant): array
    {
        $empty = ['photos' => [], 'reviews' => [], 'placeGoogleMapsUrl' => null, 'closesAt' => null];

        if (($restaurant['provider'] ?? null) !== 'google' || empty($restaurant['provider_place_id'])) {
            return $empty;
        }

        try {
            $apiKey = Config::get('services.places.google_api_key');
            $raw = (new GooglePlacesProvider($apiKey))->fetchPresentationDetails($restaurant['provider_place_id']);

            return $this->normalizer->normalizePresentationDetails($raw);
        } catch (Throwable $e) {
            Log::warning('Presentation details fetch failed', ['error' => $e->getMessage()]);

            return $empty; // card still works, just without photo/reviews/closing time
        }
    }

    private function presentCandidate(array $candidate, array $enrichment): array
    {
        $restaurant = $candidate['restaurant'];

        return [
            'id' => $restaurant['id'],
            'name' => $restaurant['name'],
            'headline' => \App\Support\RecommendationHeadline::for($restaurant),
            'foodCategory' => $restaurant['food_category'] ?? null,
            'latitude' => $restaurant['latitude'],
            'longitude' => $restaurant['longitude'],
            'distanceKm' => $candidate['distanceKm'],
            'rating' => $restaurant['rating'],
            'priceLevel' => $restaurant['price_level'],
            'cuisines' => $restaurant['cuisines'],
            'openStatus' => $restaurant['open_status'],
            'photos' => array_map(fn (array $photo) => $this->presentPhoto($photo), $enrichment['photos']),
            'reviews' => $enrichment['reviews'],
            'placeGoogleMapsUrl' => $enrichment['placeGoogleMapsUrl'],
            'closesAt' => $enrichment['closesAt'],
        ];
    }

    /**
     * Converts a photo's transient Google resource name into a signed, short-lived Laravel
     * URL — the raw name never reaches the client, and the signature stops the endpoint
     * being usable as an open proxy for arbitrary Google photo names.
     */
    private function presentPhoto(array $photo): array
    {
        return [
            'url' => URL::temporarySignedRoute('places.photo', now()->addMinutes(10), ['name' => $photo['name']]),
            'authorAttributions' => $photo['authorAttributions'],
            'googleMapsUrl' => $photo['googleMapsUrl'],
            'flagContentUrl' => $photo['flagContentUrl'],
        ];
    }
}
