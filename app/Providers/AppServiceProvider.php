<?php

namespace App\Providers;

use App\Services\CaptureParser;
use App\Services\OpenAICaptureParser;
use App\Services\SchemaOrg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Jetstream\Jetstream;
use LemonSqueezy\Laravel\LemonSqueezy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CaptureParser::class, OpenAICaptureParser::class);
        Cashier::ignoreRoutes();
        LemonSqueezy::ignoreRoutes();
        Jetstream::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        // View::share(['schema' => ['organization' => app(SchemaOrg::class)->organization()]]);
    }
}
