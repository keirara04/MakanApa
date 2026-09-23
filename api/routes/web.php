<?php

use App\Http\Controllers\MarketingController;
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
});
