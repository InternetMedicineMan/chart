<?php

namespace App\Providers;

use App\Services\XAIService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class XAIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->bind(XAIService::class, function () {
            $client = Http::baseUrl(config('services.xai.urls.base'))
                ->timeout(3600)
                ->withToken(config('services.xai.key'));

            return new XAIService($client);
        });
    }
}
