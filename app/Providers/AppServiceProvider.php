<?php

namespace App\Providers;

use App\Models\ExternalRequestItem;
use App\Models\ExternalOffer;
use App\Observers\ExternalRequestItemObserver;
use App\Observers\ExternalOfferObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Api\OpenAIClassifierClient::class, function () {
            return \App\Services\Api\OpenAIClassifierClient::fromConfig();
        });

        $this->app->singleton(\App\Services\Discovery\YandexSearchClient::class, function () {
            return \App\Services\Discovery\YandexSearchClient::fromConfig();
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
