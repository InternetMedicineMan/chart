<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasEnabledTwoFactorAuthentication()) {
            if ($request->expectsJson()) {
                abort(403, 'Complete two-factor authentication setup to open Chart.');
            }

            return redirect()->route('profile.show');
        }

        return $next($request);
    }
}
