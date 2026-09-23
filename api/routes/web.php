<?php

use App\Http\Controllers\MarketingController;
use App\Http\Controllers\SharePlaceController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Static marketing pages: no forms, no login — skip the session and CSRF middleware so
// visitors don't get makanapa-session / XSRF-TOKEN cookies for nothing.
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
])->group(function () {
    Route::get('/', [MarketingController::class, 'home']);
    Route::get('/go/testflight', [MarketingController::class, 'download'])->name('marketing.download');
    Route::view('/support', 'support');
    Route::view('/privacy', 'privacy');

    // Shared place links — the web fallback and the universal-link target for the app.
    Route::get('/p/{place}', [SharePlaceController::class, 'show'])
        ->where('place', '[0-9]+(-[A-Za-z0-9-]*)?')
        ->name('share.place');
    Route::get('/p/{place}/go/{target}', [SharePlaceController::class, 'go'])
        ->where('place', '[0-9]+(-[A-Za-z0-9-]*)?')
        ->whereIn('target', ['app', 'download']);
    Route::get('/.well-known/apple-app-site-association', [SharePlaceController::class, 'appSiteAssociation']);
});
