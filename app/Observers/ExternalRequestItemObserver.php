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

        // Находим соответствующую заявку в БД reports
        $externalRequest = $item->request;
        if (!$externalRequest || !$externalRequest->request_number) {
            Log::warning("ExternalRequestItemObserver: Не найден request_number для external_request_id={$externalRequest->id}");
            return;
        }

        // Находим заявку в основной БД по request_number
        $request = Request::where('request_number', $externalRequest->request_number)->first();
        if (!$request) {
            Log::warning("ExternalRequestItemObserver: Не найдена заявка в основной БД с request_number={$externalRequest->request_number}");
            return;
        }

        // Находим заморозку средств
        $balanceHold = $request->balanceHold;
        if (!$balanceHold || $balanceHold->status !== 'held') {
            Log::info("ExternalRequestItemObserver: Нет активной заморозки для request_id={$request->id}, request_number={$request->request_number}");
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
