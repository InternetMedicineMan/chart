<?php

namespace App\Providers;

use App\Services\SchemaOrg;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
