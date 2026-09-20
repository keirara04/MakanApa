<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantFieldOverride;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPhoto;
use App\Models\RestaurantSubmission;
use App\Services\RecommendationService;
use App\Services\RestaurantPhotoPromotionService;
use App\Support\RestaurantField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Moderation queue. This is the ONLY place a `restaurant_submissions` row is ever turned
 * into or applied onto a canonical `restaurants` row — user-facing endpoints
 * (RestaurantSubmissionController) never write to `restaurants` directly. `draft` submissions
 * never appear here — they're not real submissions yet, just in-progress client state.
 */
class RestaurantSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'pending');

        $submissions = RestaurantSubmission::where('status', $status)
            ->with(['user', 'university'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'submissions' => $submissions->map(fn (RestaurantSubmission $s) => $this->presentForAdmin($s)),
        ]);
    }

    public function photos(RestaurantSubmission $submission): JsonResponse
    {
        $photos = RestaurantPhoto::where('restaurant_submission_id', $submission->id)->get();

        return response()->json([
            'photos' => $photos->map(fn (RestaurantPhoto $photo) => [
                'id' => $photo->id,
                'photoType' => $photo->photo_type,
                'url' => URL::temporarySignedRoute('admin.submission-photos.show', now()->addMinutes(10), ['photo' => $photo->id]),
            ]),
        ]);
    }

    public function approve(Request $request, RestaurantSubmission $submission, RestaurantPhotoPromotionService $photoPromotion): JsonResponse
    {
        $restaurant = DB::transaction(function () use ($request, $submission) {
            /** @var RestaurantSubmission $locked */
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $restaurant = match ($locked->submission_type) {
                'new_place' => $this->approveNewPlace($locked, $request->user()->id),
                'edit_place' => $this->approveEdit($locked, $request->user()->id),
                'closure' => $this->approveClosure($locked, $request->user()->id),
                'reopen' => $this->approveReopen($locked, $request->boolean('releaseToGoogle'), $request->user()->id),
                default => abort(422, 'Unknown submission type.'),
            };

            $locked->update([
                'status' => 'approved',
                'restaurant_id' => $restaurant->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $restaurant;
        });

        // Filesystem work happens outside the DB transaction on purpose — see
        // RestaurantPhotoPromotionService's doc comment for why.
        $photoPromotion->promote($submission->fresh(), $restaurant);

        return response()->json(['approved' => true, 'restaurantId' => $restaurant->id]);
    }

    /** Explicit "this is the same place" action — for the Google-sync-race case and the manual duplicate hint alike. */
    public function link(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['restaurantId' => ['required', 'integer', 'exists:restaurants,id']]);

        DB::transaction(function () use ($request, $submission, $data) {
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $locked->update([
                'status' => 'approved',
                'restaurant_id' => $data['restaurantId'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['linked' => true]);
    }

    public function reject(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['reviewNote' => ['required', 'string', 'max:500']]);
        abort_unless($submission->status === 'pending', 422, 'Submission is no longer pending.');

        $submission->update([
            'status' => 'rejected',
            'review_note' => $data['reviewNote'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['rejected' => true]);
    }

    public function requestChanges(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $data = $request->validate(['reviewNote' => ['required', 'string', 'max:500']]);
        abort_unless($submission->status === 'pending', 422, 'Submission is no longer pending.');

        $submission->update([
            'status' => 'changes_requested',
            'review_note' => $data['reviewNote'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['changesRequested' => true]);
    }

    /** "Use Google again" — removes the override; the field stops being protected on the next sync. Admin-only, not tied to any submission. */
    public function releaseFieldOverride(Restaurant $restaurant, string $field): JsonResponse
    {
        RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', $field)->delete();

        return response()->json(['released' => true]);
    }

    /**
     * `new_place`/`manual` creates a fresh community restaurant — no baseline exists, no override
     * bookkeeping needed (nothing else will ever sync over it). `new_place`/`google` re-checks
     * for a sync race first — the background Google sync may have already pulled this exact
     * place in between submission and approval — and links to it instead of duplicating; only
     * fields the submitter deliberately edited away from the Google-sourced prefill become
     * overrides on top of that baseline.
     */
    private function approveNewPlace(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        if ($submission->source_type === 'google') {
            $existing = Restaurant::where('provider', 'google')
                ->where('provider_place_id', $submission->google_place_id)
                ->first();

            if ($existing) {
                return $existing;
            }

            $restaurant = Restaurant::create([
                'provider' => 'google',
                'provider_place_id' => $submission->google_place_id,
                'name' => $submission->name,
                'address' => $submission->address,
                'food_category' => $submission->food_category,
                'price_level' => $submission->price_level,
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'is_active' => true,
                'source_submission_id' => $submission->id,
            ]);

            $this->materializeFields($restaurant, $submission, $adminId);
            $this->materializeMenu($restaurant, $submission);

            return $restaurant;
        }

        $restaurant = Restaurant::create([
            'provider' => 'user_submitted',
            'provider_place_id' => null,
            'name' => $submission->name,
            'address' => $submission->address,
            'food_category' => $submission->food_category,
            'price_level' => $submission->price_level,
            'phone' => $submission->phone,
            'instagram_handle' => $submission->instagram_handle,
            'tiktok_handle' => $submission->tiktok_handle,
            'website_url' => $submission->website_url,
            'latitude' => $submission->latitude,
            'longitude' => $submission->longitude,
            'is_active' => true,
            'source_submission_id' => $submission->id,
        ]);

        $this->materializeMenu($restaurant, $submission);

        return $restaurant;
    }

    private function approveEdit(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id);
        $this->materializeFields($restaurant, $submission, $adminId);
        $this->materializeMenu($restaurant, $submission);

        return $restaurant;
    }

    /**
     * Writes an override for the synthetic `is_active` field so a future Google sync can't
     * silently reopen a confirmed closure — the gap flagged as unresolved in the previous plan.
     */
    private function approveClosure(RestaurantSubmission $submission, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id);
        $restaurant->update(['is_active' => false]);

        if ($restaurant->provider === 'google') {
            RestaurantFieldOverride::updateOrCreate(
                ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                [
                    'restaurant_submission_id' => $submission->id,
                    'value' => false,
                    'authority' => 'admin',
                    'verified_by' => $adminId,
                    'verified_at' => now(),
                ]
            );
        }

        return $restaurant;
    }

    /**
     * `releaseToGoogle=false` (default): MakanApa keeps deciding open/closed (override set to true).
     * `releaseToGoogle=true`: the override is removed entirely, handing control back to Google's
     * own open/closed signal on the next sync.
     */
    private function approveReopen(RestaurantSubmission $submission, bool $releaseToGoogle, int $adminId): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id);
        $restaurant->update(['is_active' => true]);

        if ($restaurant->provider === 'google') {
            if ($releaseToGoogle) {
                RestaurantFieldOverride::where('restaurant_id', $restaurant->id)->where('field', 'is_active')->delete();
            } else {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'field' => 'is_active'],
                    [
                        'restaurant_submission_id' => $submission->id,
                        'value' => true,
                        'authority' => 'admin',
                        'verified_by' => $adminId,
                        'verified_at' => now(),
                    ]
                );
            }
        }

        return $restaurant;
    }

    /**
     * Only fields listed in `changed_fields` are applied/overridden — the submission's snapshot
     * carries every field for moderation context, but applying all of them would silently freeze
     * fields the submitter never intended to touch, blocking all future Google updates to them.
     */
    private function materializeFields(Restaurant $restaurant, RestaurantSubmission $submission, int $adminId): void
    {
        $changed = array_values(array_intersect($submission->changed_fields ?? [], RestaurantField::OVERRIDABLE));
        if (empty($changed)) {
            return;
        }

        $columnMap = [
            'name' => 'name', 'address' => 'address', 'food_category' => 'food_category',
            'price_level' => 'price_level', 'latitude' => 'latitude', 'longitude' => 'longitude',
            'opening_hours' => 'opening_hours', 'phone' => 'phone', 'instagram_handle' => 'instagram_handle',
            'tiktok_handle' => 'tiktok_handle', 'website_url' => 'website_url',
        ];

        $updates = [];
        foreach ($changed as $field) {
            $value = $submission->{$columnMap[$field]};
            $updates[$field] = $value;

            if ($restaurant->provider === 'google') {
                RestaurantFieldOverride::updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'field' => $field],
                    [
                        'restaurant_submission_id' => $submission->id,
                        'value' => $value,
                        'authority' => 'community_verified',
                        'verified_by' => $adminId,
                        'verified_at' => now(),
                    ]
                );
            }
            // provider='user_submitted': no override bookkeeping — nothing else will ever sync
            // over it, so a direct column update is all that's needed.
        }

        $restaurant->update($updates);
    }

    /** Full replacement, not a per-item diff — matches the submission's full-snapshot design. */
    private function materializeMenu(Restaurant $restaurant, RestaurantSubmission $submission): void
    {
        if (! in_array('menu_items', $submission->changed_fields ?? [], true) || empty($submission->menu_items)) {
            return;
        }

        RestaurantMenuItem::where('restaurant_id', $restaurant->id)->delete();

        foreach (array_values($submission->menu_items) as $index => $item) {
            RestaurantMenuItem::create([
                'restaurant_id' => $restaurant->id,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'price' => $item['price'] ?? null,
                'category' => $item['category'] ?? null,
                'sort_order' => $index,
                'source_submission_id' => $submission->id,
            ]);
        }
    }

    /**
     * Warning only, never an automatic block — any active restaurant within ~100m whose
     * normalized name loosely overlaps the submission's. O(n) over active restaurants is fine
     * at beta scale; not a concern worth optimizing prematurely.
     */
    private function duplicateHint(RestaurantSubmission $submission): ?array
    {
        $normalized = (string) Str::of($submission->name)->lower()->squish();

        foreach (Restaurant::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude']) as $candidate) {
            $distanceKm = RecommendationService::distanceKm(
                (float) $submission->latitude, (float) $submission->longitude,
                (float) $candidate->latitude, (float) $candidate->longitude
            );

            if ($distanceKm > 0.1) {
                continue;
            }

            $candidateNormalized = (string) Str::of($candidate->name)->lower()->squish();
            if (str_contains($candidateNormalized, $normalized) || str_contains($normalized, $candidateNormalized)) {
                return ['id' => $candidate->id, 'name' => $candidate->name, 'distanceMeters' => (int) round($distanceKm * 1000)];
            }
        }

        return null;
    }

    private function presentForAdmin(RestaurantSubmission $submission): array
    {
        return [
            'id' => $submission->id,
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
            'changedFields' => $submission->changed_fields ?? [],
            'latitude' => (float) $submission->latitude,
            'longitude' => (float) $submission->longitude,
            'notes' => $submission->notes,
            'status' => $submission->status,
            'restaurantId' => $submission->restaurant_id,
            'submitter' => [
                'email' => $submission->user?->email,
                'affiliationType' => $submission->university_id ? 'university' : 'public',
                'university' => $submission->university?->short_name,
            ],
            'possibleDuplicate' => $this->duplicateHint($submission),
            'createdAt' => $submission->created_at?->toIso8601String(),
        ];
    }
}
