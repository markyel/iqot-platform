<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\DB;

/**
 * Фаза 2 (планировщик): ПОЗИЦИОННОЕ покрытие. Единица работы — позиция (request_item),
 * не батч. Позиция «закрыта», когда набрала >= offer_target РАЗНЫХ поставщиков с ценовым
 * ответом (request_item_responses с price_per_unit/total_price). Закрытая позиция выпадает
 * из активного набора → её больше не включаем в письма (освобождаем ёмкость под другие).
 *
 * Режим max_reach: цель — макс. охват, позиции офферами НЕ закрываются (шлём всем
 * кандидатам до исчерпания пула — исчерпание считает планировщик по интентам).
 */
class PositionCoverage
{
    private const CONN = 'reports';

    /** Дефолтная цель офферов на позицию (переиспользуем порог холодной волны). */
    public function defaultTarget(): int
    {
        return max(1, (int) config('services.email_pool.wave3_min_offers', 4));
    }

    /**
     * Активные (ещё не закрытые) позиции заявки.
     *   target-режим: позиции с < target РАЗНЫХ ценовых ответов;
     *   max_reach: ВСЕ позиции (офферами не закрываются).
     *
     * @return array<int,int> request_item_id
     */
    public function activeItemIds(int $requestId, ?int $offerTarget = null, bool $maxReach = false): array
    {
        $itemIds = DB::connection(self::CONN)->table('request_items')
            ->where('request_id', $requestId)
            ->pluck('id')->map(static fn ($v) => (int) $v)->all();
        if ($itemIds === []) {
            return [];
        }
        if ($maxReach) {
            return $itemIds;
        }

        $target = max(1, $offerTarget ?? $this->defaultTarget());
        $covered = $this->pricedSupplierCounts($itemIds);

        $active = [];
        foreach ($itemIds as $iid) {
            if ((int) ($covered[$iid] ?? 0) < $target) {
                $active[] = $iid;
            }
        }
        return $active;
    }

    /**
     * Активные позиции заявки, СРЕДИ матчащих поставщика (по его product_types/domains
     * или category-routing). Именно они пойдут в письмо этому поставщику. Пустой список
     * → поставщику писать нечего (все его позиции закрыты).
     *
     * @param array<int,int> $supplierItemIds позиции заявки, которые матчит поставщик
     * @return array<int,int>
     */
    public function activeAmong(int $requestId, array $supplierItemIds, ?int $offerTarget = null, bool $maxReach = false): array
    {
        $active = array_flip($this->activeItemIds($requestId, $offerTarget, $maxReach));
        $out = [];
        foreach ($supplierItemIds as $iid) {
            if (isset($active[(int) $iid])) {
                $out[] = (int) $iid;
            }
        }
        return $out;
    }

    /** Заявка закрыта (target): нет активных позиций. max_reach — по офферам не закрывается. */
    public function isSatisfied(int $requestId, ?int $offerTarget = null, bool $maxReach = false): bool
    {
        if ($maxReach) {
            return false;
        }
        return $this->activeItemIds($requestId, $offerTarget, false) === [];
    }

    /**
     * Сколько РАЗНЫХ поставщиков дали ценовой ответ по каждой позиции.
     *
     * @param array<int,int> $itemIds
     * @return array<int,int> request_item_id => count
     */
    public function pricedSupplierCounts(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        return DB::connection(self::CONN)->table('request_item_responses')
            ->whereIn('request_item_id', $itemIds)
            ->where(function ($q) {
                $q->whereNotNull('price_per_unit')->orWhereNotNull('total_price');
            })
            ->selectRaw('request_item_id, COUNT(DISTINCT supplier_id) c')
            ->groupBy('request_item_id')
            ->pluck('c', 'request_item_id')
            ->map(static fn ($v) => (int) $v)
            ->all();
    }
}
