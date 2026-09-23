<?php

use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dokploy's Traefik proxy terminates TLS and forwards plain HTTP to the container —
        // without trusting it, Laravel thinks every request is HTTP and generates http:// URLs
        // (e.g. signed photo URLs), which iOS App Transport Security then blocks.
        $middleware->trustProxies(at: '*');

        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'superadmin' => EnsureSuperadmin::class,
        ]);

        // API-only app — there's no named 'login' route for the web guest-redirect to point
        // at. Without this, an unauthenticated request without an explicit Accept: application/json
        // header (curl, some HTTP clients) hits Authenticate::redirectTo()'s route('login') call
        // and 500s with RouteNotFoundException instead of a clean 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
