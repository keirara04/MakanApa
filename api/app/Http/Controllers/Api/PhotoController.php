<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Streams a Google Places photo server-side so the API key never reaches the client.
 * Reached only via a signed URL (routes/api.php, `signed` middleware) — the `name`
 * query param is a transient Google photo resource name, never persisted, and the
 * signature/expiry (10 min, set when the URL is built in RecommendationController)
 * stops this from being usable as an open proxy for arbitrary Google photo names.
 */
class PhotoController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $name = $request->query('name', '');

        abort_unless((bool) preg_match('#^places/[^/]+/photos/[^/]+$#', $name), 404);

        $apiKey = Config::get('services.places.google_api_key');
        abort_if(empty($apiKey), 500, 'Places photo proxy misconfigured.');

        $response = Http::withHeaders(['X-Goog-Api-Key' => $apiKey])
            ->timeout(8)
            ->get("https://places.googleapis.com/v1/{$name}/media", ['maxWidthPx' => 800])
            ->throw();

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/jpeg'),
            // Google's terms don't clearly permit caching Places content — err compliant, not convenient.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
