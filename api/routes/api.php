<?php

use App\Http\Controllers\Api\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Api\Admin\CommunityRequestController as AdminCommunityRequestController;
use App\Http\Controllers\Api\Admin\RestaurantSubmissionController as AdminRestaurantSubmissionController;
use App\Http\Controllers\Api\Admin\SubmissionPhotoController as AdminSubmissionPhotoController;
use App\Http\Controllers\Api\Admin\UniversityController as AdminUniversityController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\CommunityRequestController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NearbyController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PlaceSearchController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantSubmissionController;
use App\Http\Controllers\Api\UniversityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Private beta: every real endpoint below requires a valid Sanctum token, not just
    // auth/admin — otherwise the app-level login gate is cosmetic and the underlying Google
    // Places/OpenRouter usage stays reachable by anyone who knows the endpoints.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('universities', [UniversityController::class, 'index']);
        Route::get('areas', [AreaController::class, 'index']);

        // Local-DB-only aggregate queries, not Google-Places-backed — gets its own more
        // generous limit than the Places-protecting throttle:30,1 group below, not none at all.
        Route::middleware('throttle:120,1')->group(function () {
            Route::get('community/feed', [CommunityController::class, 'feed']);
        });

        // Identity state, not a feed read — nobody legitimately changes university dozens of
        // times a minute, so this gets a much tighter limit than community/feed above.
        Route::middleware('throttle:10,1')->group(function () {
            Route::patch('me/community', [AuthController::class, 'updateAffiliation']);
        });

        // "My university/area isn't listed" — a rare, deliberate action, same throttle class
        // as community/submissions store below.
        Route::post('community/requests', [CommunityRequestController::class, 'store'])->middleware('throttle:5,1');

        // Google Places-backed endpoints are rate limited per client/IP so a runaway client
        // can't turn this into a Google Places billing incident during the beta.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('recommendations/solo', [RecommendationController::class, 'solo']);
            Route::post('decisions/{decision}/reroll', [RecommendationController::class, 'reroll']);
            Route::get('places/nearby', [NearbyController::class, 'index']);
            Route::post('places/nearby/pick', [NearbyController::class, 'pick']);
            Route::get('restaurants/{restaurant}/details', [NearbyController::class, 'details']);
            Route::get('community/places/search', [RestaurantSubmissionController::class, 'search']);
            // Resolving a google_fallback result does one live Google Place Details call, same
            // cost class as the rest of this group — not the cheap local-DB lane below.
            Route::post('places/resolve', [PlaceSearchController::class, 'resolve']);
        });

        // Nearby's search box: local-DB `LIKE`/`whereHas` queries, cheap enough for typeahead —
        // Google is only called from inside searchPlaces() when local results are thin, and that
        // internal call is what the throttle above actually protects, not this endpoint itself.
        Route::middleware('throttle:90,1')->group(function () {
            Route::get('places/search', [PlaceSearchController::class, 'search']);
        });

        Route::post('decisions/{decision}/accept', [RecommendationController::class, 'accept']);
        Route::post('decisions/{decision}/vibe-tag', [RecommendationController::class, 'vibeTag']);
        Route::post('restaurants/{restaurant}/save', [RestaurantController::class, 'save']);
        Route::post('restaurants/{restaurant}/unsave', [RestaurantController::class, 'unsave']);
        Route::get('places/photo', PhotoController::class)->name('places.photo')->middleware('signed');

        Route::get('community/submissions/mine', [RestaurantSubmissionController::class, 'mine']);
        Route::patch('community/submissions/{submission}', [RestaurantSubmissionController::class, 'update']);
        Route::delete('community/submissions/{submission}', [RestaurantSubmissionController::class, 'destroy']);
        Route::post('community/submissions/{submission}/submit', [RestaurantSubmissionController::class, 'submit']);
        // Adding a place is normally a once-or-twice-a-session action, not repeatable — a
        // tighter limit than the general local-DB throttle above since this writes new data
        // that auto-publishes with no review step until an admin acts on it.
        Route::post('community/submissions', [RestaurantSubmissionController::class, 'store'])->middleware('throttle:5,1');
        Route::post('community/submissions/{submission}/photos', [RestaurantSubmissionController::class, 'uploadPhoto'])->middleware('throttle:5,1');
        Route::post('restaurants/{restaurant}/photos/quick-add', [RestaurantSubmissionController::class, 'quickAddPhoto'])->middleware('throttle:5,1');

        Route::prefix('admin')->middleware('superadmin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::post('users/{user}/revoke', [AdminUserController::class, 'revoke']);

            Route::get('community/submissions', [AdminRestaurantSubmissionController::class, 'index']);
            Route::get('community/submissions/{submission}/photos', [AdminRestaurantSubmissionController::class, 'photos']);
            Route::post('community/submissions/{submission}/approve', [AdminRestaurantSubmissionController::class, 'approve']);
            Route::post('community/submissions/{submission}/link', [AdminRestaurantSubmissionController::class, 'link']);
            Route::post('community/submissions/{submission}/reject', [AdminRestaurantSubmissionController::class, 'reject']);
            Route::post('community/submissions/{submission}/request-changes', [AdminRestaurantSubmissionController::class, 'requestChanges']);
            Route::delete('community/restaurants/{restaurant}/field-overrides/{field}', [AdminRestaurantSubmissionController::class, 'releaseFieldOverride']);
            Route::post('community/restaurants/{restaurant}/remove', [AdminRestaurantSubmissionController::class, 'remove']);
            Route::get('submission-photos/{photo}', AdminSubmissionPhotoController::class)
                ->name('admin.submission-photos.show')->middleware('signed');

            Route::get('community/requests', [AdminCommunityRequestController::class, 'index']);
            Route::post('community/requests/{request}/resolve', [AdminCommunityRequestController::class, 'resolve']);
            Route::post('community/requests/{request}/dismiss', [AdminCommunityRequestController::class, 'dismiss']);

            Route::get('universities', [AdminUniversityController::class, 'index']);
            Route::post('universities', [AdminUniversityController::class, 'store']);
            Route::get('areas', [AdminAreaController::class, 'index']);
            Route::post('areas', [AdminAreaController::class, 'store']);
        });
    });
});
