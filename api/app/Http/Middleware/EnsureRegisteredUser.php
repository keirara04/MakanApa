<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account-based features (publishing community content, submissions, halal evidence) stay
 * behind a real account; guests get a stable `account_required` code the app turns into a
 * sign-in prompt instead of a generic error.
 */
class EnsureRegisteredUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isGuest()) {
            return response()->json([
                'message' => 'Create an account or sign in to use this.',
                'code' => 'account_required',
            ], 403);
        }

        return $next($request);
    }
}
