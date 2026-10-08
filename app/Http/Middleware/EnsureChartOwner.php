<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChartOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->routeIs('logout')) {
            abort_unless(
                config('chart.owner_id') && (string) $request->user()->getAuthIdentifier() === (string) config('chart.owner_id'),
                403,
            );
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
