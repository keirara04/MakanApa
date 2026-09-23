<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Resolves a Google Places photo server-side so the API key never reaches the client, then
 * redirects the client to Google's short-lived public `photoUri` — the image bytes come straight
 * from Google's CDN instead of being streamed through a PHP worker for every photo.
 * Reached only via a signed URL (routes/api.php, `signed` middleware) — the `name` query param
 * is a transient Google photo resource name, never persisted, and the signature/expiry (set when
 * the URL is built in PresentsRecommendation::presentPhoto()) stops this from being usable as an
 * open proxy for arbitrary Google photo names.
 */
class PhotoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $name = $request->query('name', '');

        abort_unless((bool) preg_match('#^places/[^/]+/photos/[^/]+$#', $name), 404);

        $apiKey = Config::get('services.places.google_api_key');
        abort_if(empty($apiKey), 500, 'Places photo proxy misconfigured.');

        try {
            $photoUri = Http::withHeaders(['X-Goog-Api-Key' => $apiKey])
                ->timeout(8)
                ->get("https://places.googleapis.com/v1/{$name}/media", ['maxWidthPx' => 800, 'skipHttpRedirect' => 'true'])
                ->throw()
                ->json('photoUri');
        } catch (ConnectionException|RequestException $exception) {
            // Google rate-limited, the photo resource expired, or the request timed out —
            // a fast, clean 502 lets the client's retry affordance kick in immediately
            // instead of the client waiting on a slow unhandled-exception response.
            abort(502, 'Could not fetch photo.');
        }

        abort_unless(is_string($photoUri) && str_starts_with($photoUri, 'https://'), 502, 'Could not fetch photo.');

        return redirect()->away($photoUri)->withHeaders([
            // Google's terms don't clearly permit caching Places content — err compliant, not convenient.
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
