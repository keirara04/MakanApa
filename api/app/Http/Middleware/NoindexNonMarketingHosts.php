<?php

namespace App\Http\Middleware;

use App\Support\MarketingUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The web routes answer on every host the container serves (the API host, the admin host), not
 * just the marketing domain. Their pages already point their canonical at the marketing domain;
 * this keeps the duplicates out of search results altogether. Off when no domain is configured.
 */
class NoindexNonMarketingHosts
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (MarketingUrl::isForeignHost($request->getHost())) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
