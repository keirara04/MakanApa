<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRestaurantSubmissionRequest;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * User-facing half of Community Places: search-before-you-add, create/edit/cancel a
 * *submission* (never the canonical restaurant directly — see Admin\RestaurantSubmissionController
 * for the only path that turns a submission into/onto a live `restaurants` row).
 */
class RestaurantSubmissionController extends Controller
{
    /**
     * Two parallel lookups so iOS can render "Already on MakanApa" vs "Found on Google" vs,
     * if neither matches, the manual-add path. Google failures degrade to an empty `google`
     * array rather than failing the whole search — the local half still works standalone.
     */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $existing = Restaurant::query()
            ->where('is_active', true)
            ->where('name', 'like', '%'.$data['query'].'%')
            ->limit(10)
            ->get(['id', 'name', 'address', 'food_category', 'price_level', 'latitude', 'longitude']);

        $existingPlaceIds = Restaurant::where('provider', 'google')->pluck('provider_place_id')->all();

        $google = [];
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lon = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $apiKey = Config::get('services.places.google_api_key');

        if ($lat !== null && $lon !== null && ! empty($apiKey)) {
            try {
                $places = (new GooglePlacesProvider($apiKey))->searchText($data['query'], $lat, $lon, 20, null);
                $normalized = (new PlaceNormalizer)->normalize($places);
                $google = $normalized
                    ->reject(fn (array $place) => in_array($place['provider_place_id'], $existingPlaceIds, true))
                    ->take(10)
                    ->map(fn (array $place) => [
                        'googlePlaceId' => $place['provider_place_id'],
                        'name' => $place['name'],
                        'foodCategory' => $place['food_category'],
                        'priceLevel' => $place['price_level'],
                        'rating' => $place['rating'],
                        'latitude' => $place['latitude'],
                        'longitude' => $place['longitude'],
                    ])
                    ->values()
                    ->all();
            } catch (Throwable $e) {
                Log::warning('Community place search: Google text search failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'existing' => $existing->map(fn (Restaurant $restaurant) => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'address' => $restaurant->address,
                'foodCategory' => $restaurant->food_category,
                'priceLevel' => $restaurant->price_level,
                'distanceKm' => ($lat !== null && $lon !== null)
                    ? RecommendationService::distanceKm($lat, $lon, (float) $restaurant->latitude, (float) $restaurant->longitude)
                    : null,
            ]),
            'google' => $google,
        ]);
    }

    public function store(StoreRestaurantSubmissionRequest $request): JsonResponse
    {
        $data = $request->trimmed();
        $user = $request->user();

        $submission = RestaurantSubmission::create([
            'user_id' => $user->id,
            'university_id' => $user->universityId(),
            'restaurant_id' => $data['restaurantId'] ?? null,
            'submission_type' => $data['submissionType'],
            'source_type' => $data['sourceType'],
            'google_place_id' => $data['googlePlaceId'] ?? null,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'food_category' => $data['foodCategory'] ?? null,
            'price_level' => $data['priceLevel'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_source' => $data['locationSource'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['submission' => $this->present($submission)], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $submissions = RestaurantSubmission::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['submissions' => $submissions->map(fn ($s) => $this->present($s))]);
    }

    public function update(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        abort_if($submission->user_id !== $request->user()->id, 403, 'You can only edit your own submissions.');
        abort_if(
            ! in_array($submission->status, ['pending', 'changes_requested'], true),
            422,
            'This submission can no longer be edited.'
        );

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'foodCategory' => ['nullable', 'string', 'max:80'],
            'priceLevel' => ['nullable', 'integer', 'between:1,3'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $submission->update([
            'name' => trim($data['name']),
            'address' => isset($data['address']) ? trim($data['address']) : null,
            'food_category' => isset($data['foodCategory']) ? trim($data['foodCategory']) : null,
            'price_level' => $data['priceLevel'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'status' => 'pending',
        ]);

        return response()->json(['submission' => $this->present($submission)]);
    }

    public function destroy(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        abort_if($submission->user_id !== $request->user()->id, 403, 'You can only cancel your own submissions.');
        abort_if(
            ! in_array($submission->status, ['pending', 'changes_requested'], true),
            422,
            'This submission can no longer be cancelled.'
        );

        $submission->update(['status' => 'cancelled']);

        return response()->json(['cancelled' => true]);
    }

    private function present(RestaurantSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'restaurantId' => $submission->restaurant_id,
            'submissionType' => $submission->submission_type,
            'sourceType' => $submission->source_type,
            'name' => $submission->name,
            'address' => $submission->address,
            'foodCategory' => $submission->food_category,
            'priceLevel' => $submission->price_level,
            'status' => $submission->status,
            'reviewNote' => $submission->review_note,
            'createdAt' => $submission->created_at?->toIso8601String(),
        ];
    }
}
