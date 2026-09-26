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
    Route::get('/try', [MarketingController::class, 'tryPick'])->middleware('throttle:30,1,landing-try')->name('marketing.try');
    Route::view('/support', 'support');

    // Legal pages. The app links these URLs directly (privacy is hardcoded in the iOS build), so
    // the paths must not change. Versions and "Last updated" dates live in config/legal.php.
    Route::view('/privacy', 'legal.privacy')->name('privacy');
    Route::view('/terms', 'legal.terms')->name('terms');
    Route::view('/community-guidelines', 'legal.community-guidelines')->name('community-guidelines');

    // Shared place links — the web fallback and the universal-link target for the app.
    Route::get('/p/{place}', [SharePlaceController::class, 'show'])
        ->where('place', '[0-9]+(-[A-Za-z0-9-]*)?')
        ->name('share.place');
    Route::get('/p/{place}/go/{target}', [SharePlaceController::class, 'go'])
        ->where('place', '[0-9]+(-[A-Za-z0-9-]*)?')
        ->whereIn('target', ['app', 'download']);
    Route::get('/.well-known/apple-app-site-association', [SharePlaceController::class, 'appSiteAssociation']);
});
