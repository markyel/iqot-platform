<?php

namespace App\Providers;

use App\Services\N8nSenderService;
use App\Services\N8nParseService;
use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;
use App\Observers\ExternalRequestItemObserver;
use App\Observers\ExternalOfferObserver;
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
        // Регистрируем Observer для отслеживания изменений в позициях заявок
        ExternalRequestItem::observe(ExternalRequestItemObserver::class);

        // Регистрируем Observer для автоматического обновления offers_count при создании/удалении предложений
        ExternalOffer::observe(ExternalOfferObserver::class);
    }
}
