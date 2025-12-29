<?php

namespace App\Providers;

use App\Services\N8nSenderService;
use App\Services\N8nParseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(N8nSenderService::class, function ($app) {
            return new N8nSenderService();
        });

        $this->app->singleton(N8nParseService::class, function ($app) {
            return new N8nParseService();
        });
    }

    public function boot(): void
    {
        //
    }
}
