<?php

namespace App\Providers;

use App\Http\Middleware\EnsureChartOwner;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    public const PUBLIC_ROUTES = [
        'home', 'health', 'login', 'login.store',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'two-factor.login', 'two-factor.login.store', 'sanctum.csrf-cookie',
    ];

    public const ACCOUNT_ROUTES = [
        'logout', 'profile.show', 'other-browser-sessions.destroy',
        'verification.notice', 'verification.verify', 'verification.send',
        'user-profile-information.update', 'user-password.update',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'two-factor.enable', 'two-factor.confirm', 'two-factor.disable',
        'two-factor.qr-code', 'two-factor.secret-key', 'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
    ];

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Include package routes in the private boundary; only named entry routes are public.
            foreach (Route::getRoutes() as $route) {
                if (in_array($route->getName(), self::PUBLIC_ROUTES, true)) {
                    continue;
                }

                $route->middleware(['web', 'auth', EnsureChartOwner::class]);
                if (! in_array($route->getName(), self::ACCOUNT_ROUTES, true)) {
                    $route->middleware(RequireTwoFactorAuthentication::class);
                }
            }
        });
    }
}
