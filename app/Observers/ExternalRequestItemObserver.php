<?php

namespace App\Observers;

use App\Models\Api\RequestItemStaging;
use App\Models\BalanceHold;
use App\Models\ExternalRequestItem;
use App\Models\Request;
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
     * Проверить и списать средства если позиция получила 3+ предложений.
     * Unified для web-flow и API-flow — их отличает только способ получения hold.
     */
    private function checkAndChargeForItem(ExternalRequestItem $item): void
    {
        // Позиция считается выполненной если получено 3 или более ВАЛИДНЫХ предложений (с ценой > 0)
        $validOffersCount = $item->offers()
            ->whereIn('status', ['received', 'processed'])
            ->whereNotNull('price_per_unit')
            ->where('price_per_unit', '>', 0)
            ->count();

        if ($validOffersCount < 3) {
            Log::info("ExternalRequestItemObserver: Позиция {$item->id} имеет {$validOffersCount} валидных предложений (нужно 3+), пропускаем списание");
            return;
        }

        // Резолвим hold — либо через web-flow (Request), либо через API-flow (RequestItemStaging).
        [$hold, $descriptionSuffix] = $this->resolveHoldAndLabel($item);
        if (!$hold) {
            return; // сообщения уже залогированы
        }

        if ($hold->status !== 'held') {
            Log::info("ExternalRequestItemObserver: Нет активной заморозки для hold_id={$hold->id} (status={$hold->status})");
            return;
        }

        // Тариф плательщика (владельца hold).
        $user = $hold->user;
        if (!$user) {
            Log::warning("ExternalRequestItemObserver: у hold_id={$hold->id} не найден user");
            return;
        }
        $tariff = $user->getActiveTariff();
        if (!$tariff) {
            Log::warning("ExternalRequestItemObserver: у user_id={$user->id} нет активного тарифа");
            return;
        }

        $itemCost = $tariff->tariffPlan->getItemCost($user);

        $hold->chargeForItem(
            $item->id,
            $itemCost,
            "Списание за выполненную позицию #{$item->position_number} в {$descriptionSuffix}"
        );

        Log::info("ExternalRequestItemObserver: списано {$itemCost} ₽ за позицию external_request_item_id={$item->id}, {$descriptionSuffix}");
    }

    /**
     * Находит balance hold для позиции и формирует человеко-читаемый суффикс описания.
     *
     * @return array{0: BalanceHold|null, 1: string}
     */
    private function resolveHoldAndLabel(ExternalRequestItem $item): array
    {
        // 1) Web-flow: есть пара iqot.requests по request_number → Request::balanceHold.
        $externalRequest = $item->request;
        if ($externalRequest && $externalRequest->request_number) {
            $request = Request::where('request_number', $externalRequest->request_number)->first();
            if ($request) {
                $hold = $request->balanceHold;
                if ($hold) {
                    return [$hold, "заявке {$request->request_number}"];
                }
                Log::info("ExternalRequestItemObserver: web-flow, но hold не найден для request_id={$request->id}");
                // Продолжаем в API-ветку — возможно заявка промоутнута из API.
            }
        }

        // 2) API-flow: RequestItemStaging.promoted_request_item_id = external_item.id → staging.balance_hold_id.
        $staging = RequestItemStaging::where('promoted_request_item_id', $item->id)->first();
        if ($staging && $staging->balance_hold_id) {
            $hold = BalanceHold::find($staging->balance_hold_id);
            if ($hold) {
                // Попробуем достать client_ref для красивого описания.
                $subLabel = $this->apiSubmissionLabel($hold);
                return [$hold, "API-заявке {$subLabel}"];
            }
            Log::warning("ExternalRequestItemObserver: API-flow, staging_id={$staging->id} указывает на hold_id={$staging->balance_hold_id}, но BalanceHold не найден");
        }

        Log::info("ExternalRequestItemObserver: не удалось резолвить hold для external_request_item_id={$item->id}");
        return [null, ''];
    }

    private function apiSubmissionLabel(BalanceHold $hold): string
    {
        if (empty($hold->api_submission_id)) {
            return '#' . $hold->id;
        }
        $sub = \App\Models\Api\ApiSubmission::find($hold->api_submission_id);
        if (!$sub) {
            return '#' . $hold->api_submission_id;
        }
        $parts = ['sub_' . $sub->external_id];
        if ($sub->client_ref) {
            $parts[] = 'ref=' . $sub->client_ref;
        }
        return implode(' ', $parts);
    }
}
