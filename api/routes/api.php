<?php

use App\Http\Controllers\Api\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Api\Admin\CommunityRequestController as AdminCommunityRequestController;
use App\Http\Controllers\Api\Admin\RestaurantSubmissionController as AdminRestaurantSubmissionController;
use App\Http\Controllers\Api\Admin\SubmissionPhotoController as AdminSubmissionPhotoController;
use App\Http\Controllers\Api\Admin\UniversityController as AdminUniversityController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AppSessionController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\CommunityPostController;
use App\Http\Controllers\Api\CommunityRequestController;
use App\Http\Controllers\Api\DecisionBrainController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\HalalController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MealNudgeController;
use App\Http\Controllers\Api\NearbyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\PlaceSearchController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RestaurantSubmissionController;
use App\Http\Controllers\Api\SeleraController;
use App\Http\Controllers\Api\UniversityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('auth/apple', [AuthController::class, 'apple'])->middleware('throttle:social');
    Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:social');
    Route::post('auth/link', [AuthController::class, 'link'])->middleware('throttle:link');

    // Bare signed URL, no Sanctum — AsyncImage/browsers/img-tag loaders fetch this directly
    // and can't attach an Authorization header. The signature itself is the auth: it's
    // generated server-side only for an authenticated user's own recommendation response,
    // short-lived (30 min), and scoped to one transient Google photo resource name.
    Route::get('places/photo', PhotoController::class)->name('places.photo')->middleware('signed');

    // Public: installs register a device token before ever logging in (or without ever logging
    // in at all) — this never sets user_id, only claim()/unclaim() below do that.
    Route::post('device-tokens', [DeviceTokenController::class, 'register'])->middleware('throttle:60,1,device-tokens');

    // Every numeric throttle below carries a third `prefix` argument (its bucket name). Without
    // it Laravel keys the limiter by user id alone, so every `throttle:N,1` route shared ONE
    // per-user counter — a burst of typeahead searches could 429 Decide or a profile save.
    // Routes that share a prefix share a budget on purpose (e.g. the Google-backed `places`).
    // Private beta: every real endpoint below requires a valid Sanctum token, not just
    // auth/admin — otherwise the app-level login gate is cosmetic and the underlying Google
    // Places/OpenRouter usage stays reachable by anyone who knows the endpoints.
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('me/device-tokens/claim', [DeviceTokenController::class, 'claim']);
        Route::delete('me/device-tokens/claim', [DeviceTokenController::class, 'unclaim']);
        Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::patch('me/notification-preferences', [NotificationPreferenceController::class, 'update']);
        Route::get('me/notifications', [NotificationController::class, 'index']);
        Route::post('me/nudges/{nudge}/events', [MealNudgeController::class, 'event'])->middleware('throttle:30,1,nudge-events');
        Route::post('me/notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('me/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::delete('auth/me', [AuthController::class, 'destroy'])->middleware('throttle:delete-account');
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::get('universities', [UniversityController::class, 'index']);
        Route::get('areas', [AreaController::class, 'index']);

        // App open/close tracking — one pair of calls per scenePhase transition, never a hot path.
        Route::middleware('throttle:60,1,app-sessions')->group(function () {
            Route::post('app-sessions/start', [AppSessionController::class, 'start']);
            Route::post('app-sessions/{session}/end', [AppSessionController::class, 'end']);
        });

        // Local-DB-only aggregate queries, not Google-Places-backed — gets its own more
        // generous limit than the Places-protecting throttle:30,1 group below, not none at all.
        Route::middleware('throttle:120,1,community-feed')->group(function () {
            Route::get('community/feed', [CommunityController::class, 'feed']);
        });

        // Identity state, not a feed read — nobody legitimately changes university dozens of
        // times a minute, so this gets a much tighter limit than community/feed above.
        Route::middleware('throttle:10,1,profile')->group(function () {
            Route::patch('me/community', [AuthController::class, 'updateAffiliation']);
            Route::patch('me/profile', [AuthController::class, 'updateProfile']);
        });

        // Community posts ("What KU is saying"). Reads share the feed's local-DB budget; writes
        // publish instantly with no review step, so they get tight per-action limits instead.
        Route::middleware('throttle:120,1,community-read')->group(function () {
            Route::get('community/posts', [CommunityPostController::class, 'index']);
            Route::get('community/posts/{post}', [CommunityPostController::class, 'show']);
            Route::get('me/blocks', [CommunityPostController::class, 'blocks']);
        });
        Route::post('community/posts', [CommunityPostController::class, 'store'])->middleware(['throttle:10,1,community-write', 'throttle:community-posts']);
        Route::delete('community/posts/{post}', [CommunityPostController::class, 'destroy'])->middleware('throttle:30,1,community-delete');
        Route::post('community/posts/{post}/react', [CommunityPostController::class, 'react'])->middleware('throttle:60,1,community-react');
        Route::post('community/posts/{post}/report', [CommunityPostController::class, 'report'])->middleware('throttle:10,1,community-report');
        Route::post('users/{user}/block', [CommunityPostController::class, 'block'])->middleware('throttle:10,1,blocks');
        Route::delete('users/{user}/block', [CommunityPostController::class, 'unblock'])->middleware('throttle:10,1,blocks');

        // "My university/area isn't listed" — a rare, deliberate action, same throttle class
        // as community/submissions store below.
        Route::post('community/requests', [CommunityRequestController::class, 'store'])->middleware('throttle:5,1,community-requests');

        // Google Places-backed endpoints are rate limited per client/IP so a runaway client
        // can't turn this into a Google Places billing incident during the beta.
        Route::middleware('throttle:30,1,places')->group(function () {
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
        Route::middleware('throttle:90,1,place-search')->group(function () {
            Route::get('places/search', [PlaceSearchController::class, 'search']);
        });

        Route::post('decisions/{decision}/accept', [RecommendationController::class, 'accept']);
        Route::post('decisions/{decision}/vibe-tag', [RecommendationController::class, 'vibeTag']);
        // Makan Brain — per-decision actions work only off the stored pool (no Places calls), so
        // they share the cheap-write throttle class; all decision-token authorized.
        Route::middleware('throttle:30,1,decision-brain')->group(function () {
            Route::post('decisions/{decision}/tune', [DecisionBrainController::class, 'tune']);
            Route::get('decisions/{decision}/what-if', [DecisionBrainController::class, 'whatIf']);
            Route::post('decisions/{decision}/choose', [DecisionBrainController::class, 'choose']);
            Route::post('decisions/{decision}/why-not', [DecisionBrainController::class, 'whyNot']);
        });
        Route::post('decisions/{decision}/interactions', [DecisionBrainController::class, 'interaction'])->middleware('throttle:60,1,decision-interactions');
        Route::middleware('throttle:60,1,selera-read')->group(function () {
            Route::get('context', [SeleraController::class, 'context']);
            Route::get('me/selera', [SeleraController::class, 'show']);
        });
        Route::middleware('throttle:20,1,selera-write')->group(function () {
            Route::post('me/selera/traits/{trait}/feedback', [SeleraController::class, 'feedback']);
            Route::delete('me/selera/traits/{trait}', [SeleraController::class, 'mute']);
            Route::post('me/selera/reset', [SeleraController::class, 'reset']);
        });

        Route::post('restaurants/{restaurant}/save', [RestaurantController::class, 'save']);
        Route::post('restaurants/{restaurant}/unsave', [RestaurantController::class, 'unsave']);
        // "Makan sini" from search — writes an accepted decision; idempotent on clientChoiceId.
        Route::post('restaurants/{restaurant}/choose', [RestaurantController::class, 'choose'])->middleware('throttle:30,1,search-choose');
        Route::post('restaurants/{restaurant}/share-events', [RestaurantController::class, 'shareStarted'])->middleware('throttle:30,1,share-events');

        Route::get('community/submissions/mine', [RestaurantSubmissionController::class, 'mine']);
        Route::patch('community/submissions/{submission}', [RestaurantSubmissionController::class, 'update']);
        Route::delete('community/submissions/{submission}', [RestaurantSubmissionController::class, 'destroy']);
        Route::post('community/submissions/{submission}/submit', [RestaurantSubmissionController::class, 'submit']);
        // Adding a place is normally a once-or-twice-a-session action, not repeatable — a
        // tighter limit than the general local-DB throttle above since this writes new data
        // that auto-publishes with no review step until an admin acts on it.
        Route::post('community/submissions', [RestaurantSubmissionController::class, 'store'])->middleware('throttle:5,1,submissions');
        Route::post('community/submissions/{submission}/photos', [RestaurantSubmissionController::class, 'uploadPhoto'])->middleware('throttle:5,1,submission-photos');
        Route::post('restaurants/{restaurant}/photos/quick-add', [RestaurantSubmissionController::class, 'quickAddPhoto'])->middleware('throttle:5,1,quick-add-photos');

        // Halal trust: evidence reports + ownership claims enter moderation like any submission.
        // Burst throttle plus a daily cap (`halal-reports`) against report spam.
        Route::post('restaurants/{restaurant}/halal-reports', [HalalController::class, 'storeReport'])->middleware(['throttle:5,1,halal-reports-burst', 'throttle:halal-reports']);
        Route::post('restaurants/{restaurant}/owner-claim', [HalalController::class, 'storeOwnerClaim'])->middleware('throttle:3,1,owner-claims');
        Route::get('restaurants/{restaurant}/halal/history', [HalalController::class, 'history'])->middleware('throttle:60,1,halal-history');

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
