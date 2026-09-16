<?php

use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\RecommendationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::post('recommendations/solo', [RecommendationController::class, 'solo']);
    Route::post('decisions/{decision}/reroll', [RecommendationController::class, 'reroll']);
    Route::post('decisions/{decision}/accept', [RecommendationController::class, 'accept']);
    Route::get('places/photo', PhotoController::class)->name('places.photo')->middleware('signed');
});
