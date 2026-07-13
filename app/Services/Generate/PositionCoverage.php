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
     * Поставщики, которым УЖЕ писали по позиции (v2 позиционный дедуп). Строка
     * request_item_responses существует для каждой (позиция × поставщик), которой
     * отправили письмо (с ценой или без) → это множество «кому по факту слали».
     *
     * НЕ считаются пары, чьё письмо НЕ ушло (error/failed/cancelled): иначе сгоревшее
     * на отправке письмо (напр. битый ящик-отправитель) навсегда выкидывает поставщика
     * из пула позиции — v2 не может дослать его живым ящиком (инцидент 2026-07-13,
     * недонастроенный домен tomailbox.store). Письмо удалено (LEFT JOIN мимо) —
     * консервативно считаем «слали». Оффер/ответ уже есть (status в rir не pending) —
     * тоже слали, даже если email_queue-строку перекрыло/отменило: ответ важнее письма.
     *
     * @param array<int,int> $itemIds
     * @return array<int,array<int,int>> request_item_id => [supplier_id, ...]
     */
    public function emailedSuppliersPerItem(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }
        $out = [];
        foreach (DB::connection(self::CONN)->table('request_item_responses as r')
            ->leftJoin('email_queue as q', 'q.id', '=', 'r.email_queue_id')
            ->whereIn('r.request_item_id', $itemIds)
            ->where(function ($w) {
                $w->whereNull('q.id')
                    ->orWhereNotIn('q.status', ['error', 'failed', 'cancelled'])
                    ->orWhere('r.status', '<>', 'pending');
            })
            ->distinct()->get(['r.request_item_id', 'r.supplier_id']) as $r) {
            $out[(int) $r->request_item_id][] = (int) $r->supplier_id;
        }
        return $out;
    }

    /**
     * Остаток пула на позицию (v2): кандидаты − уже писавшие. Пустой → пул исчерпан.
     *
     * @param array<int,int> $candidateSupplierIds профильные поставщики позиции
     * @param array<int,int> $emailedSupplierIds кому уже писали по этой позиции
     * @return array<int,int> supplier_id
     */
    public function remainingPool(array $candidateSupplierIds, array $emailedSupplierIds): array
    {
        if ($emailedSupplierIds === []) {
            return array_values(array_unique(array_map('intval', $candidateSupplierIds)));
        }
        $emailed = array_flip(array_map('intval', $emailedSupplierIds));
        $out = [];
        foreach ($candidateSupplierIds as $sid) {
            $sid = (int) $sid;
            if (!isset($emailed[$sid])) {
                $out[] = $sid;
            }
        }
        return $out;
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
