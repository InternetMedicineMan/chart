<?php

namespace App\Http\Middleware;

use App\Models\CaptureToken;
use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCapture
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $throttled = fn (Request $request) => app(ThrottleRequests::class)->handle($request, $next, 'capture-device');
        if ($request->hasHeader('Authorization')) {
            $plain = $request->bearerToken();
            abort_unless(is_string($plain) && preg_match('/\Act_[a-zA-Z0-9]{64}\z/', $plain), 401, 'A valid capture token is required.');
            $token = CaptureToken::where('token_hash', hash('sha256', $plain))->whereNull('revoked_at')->first();
            abort_unless($token && in_array('capture:write', $token->scopes ?? [], true), 401, 'A valid capture token is required.');
            $owner = $token->user;
            abort_unless($owner && config('chart.owner_id') && (string) $owner->id === (string) config('chart.owner_id') && $owner->hasEnabledTwoFactorAuthentication(), 403, 'Capture access is unavailable.');
            $request->setUserResolver(fn () => $owner);
            $request->attributes->set('capture_token', $token);
            $response = $throttled($request);
        } else {
            $response = app(Pipeline::class)->send($request)->through([
                EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
                Authenticate::class.':web', AuthenticateSession::class, EnsureChartOwner::class,
                RequireTwoFactorAuthentication::class, VerifyCsrfToken::class,
            ])->then($throttled);
        }
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
