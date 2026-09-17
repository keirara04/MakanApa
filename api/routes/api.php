<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NearbyController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RestaurantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
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
});
