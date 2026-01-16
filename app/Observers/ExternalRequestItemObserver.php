<?php

namespace App\Observers;

use App\Models\ExternalRequestItem;
use App\Models\Request;
use App\Models\BalanceHold;
use Illuminate\Support\Facades\Log;

class ExternalRequestItemObserver
{
    /**
     * Обработка события обновления позиции
     */
    public function updated(ExternalRequestItem $item): void
    {
        // Проверяем, изменилось ли количество предложений
        if ($item->isDirty('offers_count')) {
            $this->checkAndChargeForItem($item);
        }
    }

    /**
     * Проверить и списать средства если позиция получила 3+ предложений
     */
    private function checkAndChargeForItem(ExternalRequestItem $item): void
    {
        // Позиция считается выполненной если получено 3 или более предложений
        if ($item->offers_count < 3) {
            return;
        }

        // Находим соответствующую заявку в основной БД по external_request_id
        $externalRequest = $item->request;
        if (!$externalRequest || !$externalRequest->internal_request_id) {
            Log::warning("ExternalRequestItemObserver: Не найдена связь с внутренней заявкой для external_request_id={$externalRequest->id}");
            return;
        }

        // Находим заявку в основной БД
        $request = Request::find($externalRequest->internal_request_id);
        if (!$request) {
            Log::warning("ExternalRequestItemObserver: Не найдена заявка в основной БД с id={$externalRequest->internal_request_id}");
            return;
        }

        // Находим заморозку средств
        $balanceHold = $request->balanceHold;
        if (!$balanceHold || $balanceHold->status !== 'held') {
            Log::info("ExternalRequestItemObserver: Нет активной заморозки для request_id={$request->id}");
            return;
        }

        // Получаем активный тариф пользователя для расчета стоимости
        $user = $request->user;
        $tariff = $user->getActiveTariff();

        if (!$tariff) {
            Log::warning("ExternalRequestItemObserver: У пользователя user_id={$user->id} нет активного тарифа");
            return;
        }

        // Рассчитываем стоимость одной позиции
        $itemCost = $tariff->tariffPlan->getItemCost($user);

        // Списываем средства за эту позицию
        $balanceHold->chargeForItem(
            $item->id,
            $itemCost,
            "Списание за выполненную позицию #{$item->position_number} в заявке {$request->request_number}"
        );

        Log::info("ExternalRequestItemObserver: Списано {$itemCost} ₽ за позицию external_request_item_id={$item->id}, request={$request->request_number}");
    }
}
