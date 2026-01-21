<?php

namespace App\Observers;

use App\Models\ExternalOffer;
use Illuminate\Support\Facades\Log;

/**
 * Observer для автоматического обновления offers_count при создании/удалении предложений
 */
class ExternalOfferObserver
{
    /**
     * Handle the ExternalOffer "created" event.
     */
    public function created(ExternalOffer $offer): void
    {
        try {
            if ($offer->requestItem) {
                $offer->requestItem->increment('offers_count');
                Log::info("ExternalOfferObserver: offers_count увеличен для item_id={$offer->request_item_id}, новое значение: {$offer->requestItem->fresh()->offers_count}");
            }
        } catch (\Exception $e) {
            Log::error("ExternalOfferObserver: Ошибка при увеличении offers_count для offer_id={$offer->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the ExternalOffer "deleted" event.
     */
    public function deleted(ExternalOffer $offer): void
    {
        try {
            if ($offer->requestItem) {
                $offer->requestItem->decrement('offers_count');
                Log::info("ExternalOfferObserver: offers_count уменьшен для item_id={$offer->request_item_id}, новое значение: {$offer->requestItem->fresh()->offers_count}");
            }
        } catch (\Exception $e) {
            Log::error("ExternalOfferObserver: Ошибка при уменьшении offers_count для offer_id={$offer->id}: " . $e->getMessage());
        }
    }
}
