<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response (marketing pages, Filament admin, API).
 *
 * CSP is limited to frame-ancestors on purpose: a full script/style policy would need
 * per-page nonces for Filament/Livewire's inline scripts, and clickjacking is the real
 * risk here.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Only over TLS — trustProxies makes isSecure() honour Traefik's X-Forwarded-Proto.
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        $headers->remove('X-Powered-By');

        return $response;
    }
}
