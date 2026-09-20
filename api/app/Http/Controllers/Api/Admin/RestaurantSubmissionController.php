<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantSubmission;
use App\Services\RecommendationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moderation queue. This is the ONLY place a `restaurant_submissions` row is ever turned
 * into or applied onto a canonical `restaurants` row — user-facing endpoints
 * (RestaurantSubmissionController) never write to `restaurants` directly.
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

    public function approve(Request $request, RestaurantSubmission $submission): JsonResponse
    {
        $restaurant = DB::transaction(function () use ($request, $submission) {
            /** @var RestaurantSubmission $locked */
            $locked = RestaurantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 422, 'Submission is no longer pending.');

            $restaurant = match ($locked->submission_type) {
                'new_place' => $this->approveNewPlace($locked),
                'edit_place' => $this->approveEdit($locked),
                'closure' => $this->approveClosure($locked),
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

    /**
     * `new_place`/`manual` creates a fresh community restaurant. `new_place`/`google` re-checks
     * for a sync race first — the background Google sync may have already pulled this exact
     * place in between submission and approval — and links to it instead of duplicating.
     * `provider`/`provider_place_id` describe where the restaurant's FACTS come from;
     * `source_submission_id` separately records HOW it entered MakanApa — the two are never
     * conflated. A freshly created Google-sourced row is deliberately missing rating/opening
     * hours/google_types (unknown, not guessed) — the next natural PlacesService background
     * sync fills those in via its own provider_place_id-keyed upsert.
     */
    private function approveNewPlace(RestaurantSubmission $submission): Restaurant
    {
        if ($submission->source_type === 'google') {
            $existing = Restaurant::where('provider', 'google')
                ->where('provider_place_id', $submission->google_place_id)
                ->first();

            if ($existing) {
                return $existing;
            }

            return Restaurant::create([
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
        }

        return Restaurant::create([
            'provider' => 'user_submitted',
            'provider_place_id' => null,
            'name' => $submission->name,
            'address' => $submission->address,
            'food_category' => $submission->food_category,
            'price_level' => $submission->price_level,
            'latitude' => $submission->latitude,
            'longitude' => $submission->longitude,
            'is_active' => true,
            'source_submission_id' => $submission->id,
        ]);
    }

    private function approveEdit(RestaurantSubmission $submission): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id);
        $restaurant->update([
            'name' => $submission->name,
            'address' => $submission->address,
            'food_category' => $submission->food_category,
            'price_level' => $submission->price_level,
        ]);

        return $restaurant;
    }

    private function approveClosure(RestaurantSubmission $submission): Restaurant
    {
        $restaurant = Restaurant::findOrFail($submission->restaurant_id);
        $restaurant->update(['is_active' => false]);

        return $restaurant;
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
            'latitude' => (float) $submission->latitude,
            'longitude' => (float) $submission->longitude,
            'notes' => $submission->notes,
            'status' => $submission->status,
            'restaurantId' => $submission->restaurant_id,
            'submitter' => $submission->user ? [
                'email' => $submission->user->email,
                'affiliationType' => $submission->university_id ? 'university' : 'public',
                'university' => $submission->university?->short_name,
            ] : [
                'email' => null,
                'affiliationType' => $submission->university_id ? 'university' : 'public',
                'university' => $submission->university?->short_name,
            ],
            'possibleDuplicate' => $this->duplicateHint($submission),
            'createdAt' => $submission->created_at?->toIso8601String(),
        ];
    }
}
