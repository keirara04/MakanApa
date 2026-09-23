<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Decision;
use Illuminate\Http\Request;

trait AuthorizesDecisionToken
{
    /**
     * A decision's sequential integer ID is otherwise the only handle a client has — without
     * this check, any logged-in beta user could enumerate IDs and reroll/accept someone else's
     * in-progress decision. `client_token` is an opaque secret handed back once, in solo()'s
     * response, and must be echoed on every subsequent call for that decision.
     */
    private function authorizeDecision(Request $request, Decision $decision): void
    {
        $token = $request->header('X-Decision-Token');
        abort_unless(
            $decision->client_token !== null && $token !== null && hash_equals($decision->client_token, $token),
            403
        );
    }
}
