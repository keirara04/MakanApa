<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * A suspended or revoked account is locked out of the whole API, not just posting — this is
 * what makes "abusive users are removed" in the Terms true. The token used is deleted too, so
 * the app's next call gets a 401 and signs out. Suspending already revokes every token
 * (AdminUserService::suspend); this also catches a status set any other way.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return self::suspendedResponse();
        }

        return $next($request);
    }

    /** Shared with the sign-in endpoints so a suspended account gets the same answer everywhere. */
    public static function suspendedResponse(): JsonResponse
    {
        $message = 'This account is suspended.';

        if ($supportEmail = config('marketing.support_email')) {
            $message .= " Contact {$supportEmail} if you think this is a mistake.";
        }

        return response()->json(['message' => $message, 'code' => 'account_suspended'], 403);
    }
}
