<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRestaurantSubmissionRequest;
use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Services\Places\GooglePlacesProvider;
use App\Services\Places\PlaceNormalizer;
use App\Services\RecommendationService;
use App\Services\RestaurantPhotoUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * User-facing half of Community Places: search-before-you-add, create/edit/cancel a
 * *submission* (never the canonical restaurant directly — see Admin\RestaurantSubmissionController
 * for the only path that turns a submission into/onto a live `restaurants` row).
 *
 * Editable states are `draft` and `changes_requested` only — a submission created via store()
 * starts as `draft` (invisible to the admin queue) specifically so photo uploads can attach to a
 * real submission ID before the admin ever sees it; `submit()` is what actually puts it in the
 * queue (draft -> pending). Once `pending`, it's locked from self-edits until an admin acts.
 */
class RestaurantSubmissionController extends Controller
{
    private const EDITABLE_STATUSES = ['draft', 'changes_requested'];

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
            'area_id' => $user->areaId(),
            'restaurant_id' => $data['restaurantId'] ?? null,
            'submission_type' => $data['submissionType'],
            'source_type' => $data['sourceType'],
            'google_place_id' => $data['googlePlaceId'] ?? null,
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'food_category' => $data['foodCategory'] ?? null,
            'price_level' => $data['priceLevel'] ?? null,
            'phone' => $data['phone'] ?? null,
            'instagram_handle' => $data['instagramHandle'] ?? null,
            'tiktok_handle' => $data['tiktokHandle'] ?? null,
            'website_url' => $data['websiteUrl'] ?? null,
            'menu_items' => $data['menuItems'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'location_source' => $data['locationSource'],
            'notes' => $data['notes'] ?? null,
            'changed_fields' => $data['changedFields'] ?? [],
            'status' => 'draft',
        ]);

        return response()->json(['submission' => $this->present($submission)], 201);
    }

    /** draft -> pending. This is what "Submit for review" actually calls, after any optional photos are attached. */
    public function submit(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        abort_if($submission->user_id !== $request->user()->id, 403, 'You can only submit your own submissions.');
        abort_if($submission->status !== 'draft', 422, 'This submission has already been submitted.');

        $submission->update(['status' => 'pending']);

        return response()->json(['submission' => $this->present($submission)]);
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
        abort_if(! in_array($submission->status, self::EDITABLE_STATUSES, true), 422, 'This submission can no longer be edited.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'foodCategory' => ['nullable', 'string', 'max:80'],
            'priceLevel' => ['nullable', 'integer', 'between:1,3'],
            'phone' => ['nullable', 'string', 'max:30'],
            'instagramHandle' => ['nullable', 'string', 'max:60'],
            'tiktokHandle' => ['nullable', 'string', 'max:60'],
            'websiteUrl' => ['nullable', 'string', 'max:255', 'url'],
            'menuItems' => ['nullable', 'array', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'changedFields' => ['nullable', 'array'],
        ]);

        $wasChangesRequested = $submission->status === 'changes_requested';

        $submission->update([
            'name' => trim($data['name']),
            'address' => isset($data['address']) ? trim($data['address']) : null,
            'food_category' => isset($data['foodCategory']) ? trim($data['foodCategory']) : null,
            'price_level' => $data['priceLevel'] ?? null,
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'instagram_handle' => isset($data['instagramHandle']) ? trim($data['instagramHandle']) : null,
            'tiktok_handle' => isset($data['tiktokHandle']) ? trim($data['tiktokHandle']) : null,
            'website_url' => isset($data['websiteUrl']) ? trim($data['websiteUrl']) : null,
            'menu_items' => $data['menuItems'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'changed_fields' => $data['changedFields'] ?? $submission->changed_fields,
            'status' => $wasChangesRequested ? 'pending' : $submission->status,
        ]);

        return response()->json(['submission' => $this->present($submission)]);
    }

    public function destroy(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        abort_if($submission->user_id !== $request->user()->id, 403, 'You can only cancel your own submissions.');
        abort_if(! in_array($submission->status, self::EDITABLE_STATUSES, true), 422, 'This submission can no longer be cancelled.');

        $submission->update(['status' => 'cancelled']);

        return response()->json(['cancelled' => true]);
    }

    public function uploadPhoto(Request $request, RestaurantSubmission $submission, RestaurantPhotoUploadService $uploader): JsonResponse
    {
        abort_if($submission->user_id !== $request->user()->id, 403, 'You can only add photos to your own submissions.');
        abort_if(! in_array($submission->status, self::EDITABLE_STATUSES, true), 422, 'Photos can no longer be added to this submission.');

        $existingCount = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->count();
        abort_if($existingCount >= Config::get('restaurant_photos.max_per_submission'), 422, 'Maximum photos reached for this submission.');

        $data = $request->validate([
            'photo' => ['required', 'image', 'max:'.Config::get('restaurant_photos.max_size_kb')],
            'photoType' => ['nullable', 'in:storefront,food,menu,other'],
        ]);

        $photo = $uploader->storePending($submission, $request->file('photo'), $data['photoType'] ?? 'other', $request->user()->id);

        return response()->json(['photo' => ['id' => $photo->id, 'photoType' => $photo->photo_type]], 201);
    }

    /**
     * One-tap "add a photo" for a restaurant that has no Google photo and no community photo
     * yet — the gap Google's Places API leaves on places it just doesn't have licensed photos
     * for. Skips the full "suggest an edit" form: an edit_place submission is created and
     * immediately submitted server-side (name/location snapshotted straight from the
     * restaurant, nothing the client needs to already know), carrying only this one photo.
     * Still goes through the same admin approval as every other community photo — this closes
     * the UX gap, not the moderation gate.
     */
    public function quickAddPhoto(Request $request, Restaurant $restaurant, RestaurantPhotoUploadService $uploader): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'image', 'max:'.Config::get('restaurant_photos.max_size_kb')],
            'photoType' => ['nullable', 'in:storefront,food,menu,other'],
        ]);

        $submission = RestaurantSubmission::create([
            'user_id' => $request->user()->id,
            'restaurant_id' => $restaurant->id,
            'submission_type' => 'edit_place',
            'source_type' => 'manual',
            'name' => $restaurant->name,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'location_source' => 'current_location',
            'changed_fields' => [],
            'status' => 'draft',
        ]);

        $photo = $uploader->storePending($submission, $request->file('photo'), $data['photoType'] ?? 'other', $request->user()->id);

        $submission->update(['status' => 'pending']);

        return response()->json(['photo' => ['id' => $photo->id, 'photoType' => $photo->photo_type]], 201);
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
            'phone' => $submission->phone,
            'instagramHandle' => $submission->instagram_handle,
            'tiktokHandle' => $submission->tiktok_handle,
            'websiteUrl' => $submission->website_url,
            'menuItems' => $submission->menu_items,
            'status' => $submission->status,
            'reviewNote' => $submission->review_note,
            'createdAt' => $submission->created_at?->toIso8601String(),
        ];
    }
}
