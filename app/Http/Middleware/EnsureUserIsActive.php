<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Disabling an account only stopped the next *login*: an already-signed-in user
 * kept a working session until it expired on its own, so revoking access did
 * not actually revoke access. This re-checks the flag on every request and
 * tears the session down the moment it flips.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            auth()->guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(Response::HTTP_UNAUTHORIZED, __('Your account is disabled.'));
        }

        return $next($request);
    }
}
