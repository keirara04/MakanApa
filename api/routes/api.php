<?php

use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NearbyController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RestaurantController;
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

        // Local-DB-only aggregate queries, not Google-Places-backed — gets its own more
        // generous limit than the Places-protecting throttle:30,1 group below, not none at all.
        Route::middleware('throttle:120,1')->group(function () {
            Route::get('community/feed', [CommunityController::class, 'feed']);
        });

        // Google Places-backed endpoints are rate limited per client/IP so a runaway client
        // can't turn this into a Google Places billing incident during the beta.
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('recommendations/solo', [RecommendationController::class, 'solo']);
            Route::post('decisions/{decision}/reroll', [RecommendationController::class, 'reroll']);
            Route::get('places/nearby', [NearbyController::class, 'index']);
            Route::post('places/nearby/pick', [NearbyController::class, 'pick']);
            Route::get('restaurants/{restaurant}/details', [NearbyController::class, 'details']);
        });

        Route::post('decisions/{decision}/accept', [RecommendationController::class, 'accept']);
        Route::post('decisions/{decision}/vibe-tag', [RecommendationController::class, 'vibeTag']);
        Route::post('restaurants/{restaurant}/save', [RestaurantController::class, 'save']);
        Route::post('restaurants/{restaurant}/unsave', [RestaurantController::class, 'unsave']);
        Route::get('places/photo', PhotoController::class)->name('places.photo')->middleware('signed');

        Route::prefix('admin')->middleware('superadmin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::post('users/{user}/revoke', [AdminUserController::class, 'revoke']);
        });
    });
});
