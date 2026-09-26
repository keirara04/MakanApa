<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contributions (posts, submissions, photos, halal evidence) need an agreement to the current
 * Terms of Use and Community Guidelines first — Apple guideline 1.2 expects users to agree to
 * zero-tolerance terms before publishing. The stable `terms_required` code lets the app show
 * its agreement sheet instead of a generic error. Runs after `registered`, so guests get
 * `account_required` first.
 */
class EnsureTermsAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasAcceptedCurrentTerms()) {
            return response()->json([
                'message' => 'Please agree to the Terms of Use and Community Guidelines first.',
                'code' => 'terms_required',
            ], 403);
        }

        return $next($request);
    }
}
