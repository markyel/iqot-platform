<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Порт n8n-узлов «Build Supplier SQL v3» + «Get Suppliers».
 *
 * Подбирает ПРОФИЛЬНЫЙ список поставщиков под батч (только notify_email=1,
 * is_active=1). Две системы маршрутизации:
 *   NEW: фильтр по supplier_domains / supplier_product_types с поддержкой
 *        исключений (scope='all' + NOT EXISTS is_included=FALSE) OR
 *        (EXISTS is_included=TRUE).
 *   OLD: JSON_CONTAINS(s.categories, '"<target_category>"') по любой из целевых.
 */
class CampaignSupplierSelector
{
    private const CONN = 'reports';

    /**
     * @return array<int,array<string,mixed>> поставщики: id,name,email,contact_person,categories
     */
    public function select(Batch $batch): array
    {
        if ($batch->useNewRouting) {
            $rows = $this->selectNew($batch);
        } else {
            $rows = $this->selectOld($batch);
        }

        $suppliers = array_map(static fn ($r) => (array) $r, $rows);

        if ((bool) config('services.email_pool.waves_v2', false)) {
            // Waves-v2: НЕ режем по размеру. Весь пул → suppliers, а деление на волны по
            // ТЕМПЕРАТУРЕ Яндекс-матча делает GenerateCampaignJob::classifyByTier ПОСЛЕ
            // таргетинга (нужен весь пул в supplierIds, чтобы классифицировать всех).
            $wave1 = $suppliers;
            $expansion = [];
        } else {
            // Legacy: при сверх-большом пуле волна 1 = ужесточённый поднабор, остальное
            // → пул расширения (волна 2, досыл при малом отклике).
            [$wave1, $expansion] = $this->splitPool($batch, $suppliers);
        }

        // Дозаполняем батч профильным списком (его потребляют CampaignEmailBuilder
        // и CampaignPersister: per-supplier письма + email_batches.supplier_ids).
        $batch->suppliers = $wave1;
        $batch->expansionSuppliers = $expansion;
        $batch->supplierIds = [];
        foreach ($wave1 as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0 && !in_array($id, $batch->supplierIds, true)) {
                $batch->supplierIds[] = $id;
            }
        }

        return $wave1;
    }

    /**
     * Адаптивное ужесточение пула. Если |pool| <= порога — волна 1 = весь пул.
     * Иначе волна 1 = поставщики с ЯВНОЙ привязкой к типам позиций батча (ранжир по
     * profile_confidence/rating, срез до порога), остальное → пул расширения.
     *
     * @param array<int,array<string,mixed>> $suppliers
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>} [wave1, expansion]
     */
    private function splitPool(Batch $batch, array $suppliers): array
    {
        $threshold = max(1, (int) config('services.email_pool.wave1_threshold', 150));
        if (count($suppliers) <= $threshold) {
            return [$suppliers, []];
        }

        $ids = array_map(static fn ($s) => (int) ($s['id'] ?? 0), $suppliers);
        $typeIds = $this->intList($batch->productTypeIds);

        // Поставщики с явной привязкой к типам позиций (целевые по типу).
        $explicit = [];
        if ($typeIds !== []) {
            $in = implode(',', $typeIds);
            $explicit = array_flip(
                DB::connection(self::CONN)->table('supplier_product_types')
                    ->whereIn('supplier_id', $ids)
                    ->whereRaw("product_type_id IN ($in)")
                    ->where('is_included', 1)
                    ->distinct()->pluck('supplier_id')->map(fn ($v) => (int) $v)->all()
            );
        }

        // Метаданные для ранжира.
        $meta = DB::connection(self::CONN)->table('suppliers')
            ->whereIn('id', $ids)
            ->get(['id', 'profile_confidence', 'rating'])
            ->keyBy('id');

        // Приоритет: сначала явно-привязанные по типу (целевые), затем остальные —
        // и те и другие ранжируем по (confidence desc, rating desc). Волну 1 ДОБИВАЕМ
        // до порога (не режем до одних явно-привязанных — среди «остальных» тоже есть
        // целевые без pt-метки). В волну 2 уходит только хвост за порогом.
        $cmp = function ($a, $b) use ($meta) {
            $ca = (float) ($meta[$a]->profile_confidence ?? 0);
            $cb = (float) ($meta[$b]->profile_confidence ?? 0);
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }
            return (float) ($meta[$b]->rating ?? 0) <=> (float) ($meta[$a]->rating ?? 0);
        };
        $tightIds = array_values(array_filter($ids, static fn ($id) => isset($explicit[$id])));
        $restIds = array_values(array_filter($ids, static fn ($id) => !isset($explicit[$id])));
        usort($tightIds, $cmp);
        usort($restIds, $cmp);

        // Явно-привязанные впереди, добор общими до порога.
        $ordered = array_merge($tightIds, $restIds);
        $wave1Ids = array_flip(array_slice($ordered, 0, $threshold));

        $wave1 = [];
        $expansion = [];
        foreach ($suppliers as $s) {
            $id = (int) ($s['id'] ?? 0);
            if (isset($wave1Ids[$id])) {
                $wave1[] = $s;
            } else {
                $expansion[] = $s;
            }
        }

        return [$wave1, $expansion];
    }

    /**
     * @return array<int,object>
     */
    private function selectNew(Batch $batch): array
    {
        $query = DB::connection(self::CONN)->table('suppliers as s')
            ->distinct()
            ->select('s.id', 's.name', 's.email', 's.contact_person', 's.categories')
            ->where('s.is_active', 1)
            ->where('s.notify_email', 1);

        $this->excludeBlockedDomains($query);
        $this->excludeBlockedRecipients($query);
        $this->excludePaused($query);
        $this->excludeUndeliverable($query);

        $domainIds = $this->intList($batch->domainIds);
        if (!empty($domainIds)) {
            $in = implode(',', $domainIds);
            $query->where(function ($q) use ($in) {
                $q->where(function ($q2) use ($in) {
                    $q2->where('s.scope_domains', 'all')
                        ->whereRaw("NOT EXISTS (SELECT 1 FROM supplier_domains sd WHERE sd.supplier_id = s.id AND sd.domain_id IN ($in) AND sd.is_included = 0)");
                })->orWhereRaw("EXISTS (SELECT 1 FROM supplier_domains sd WHERE sd.supplier_id = s.id AND sd.domain_id IN ($in) AND sd.is_included = 1)");
            });
        }

        $typeIds = $this->intList($batch->productTypeIds);
        if (!empty($typeIds)) {
            $in = implode(',', $typeIds);
            $incExists = $this->includedExistsSql($typeIds);
            $query->where(function ($q) use ($in, $incExists) {
                $q->where(function ($q2) use ($in) {
                    $q2->where('s.scope_product_types', 'all')
                        ->whereRaw("NOT EXISTS (SELECT 1 FROM supplier_product_types spt WHERE spt.supplier_id = s.id AND spt.product_type_id IN ($in) AND spt.is_included = 0)");
                })->orWhereRaw($incExists);
            });
        }

        return $query->get()->all();
    }

    /**
     * SQL-условие «поставщик профилен под эти типы».
     * Флаг OFF — как было: любая строка is_included=1 (trusted ИЛИ probation).
     * Флаг ON (EMAILS_POOL_KARMA_GATE) — probation-гейт: в rich-темах (trusted >= порога)
     * требуем trusted-строку (source manual/response_positive ИЛИ positive_signals>0),
     * в thin-темах — как было. frozen (is_included=0) исключён в обоих режимах.
     *
     * @param array<int,int> $typeIds
     */
    private function includedExistsSql(array $typeIds): string
    {
        $in = implode(',', $typeIds);
        if (!$this->karmaEnabled()) {
            return "EXISTS (SELECT 1 FROM supplier_product_types spt WHERE spt.supplier_id = s.id AND spt.product_type_id IN ($in) AND spt.is_included = 1)";
        }
        $rich = $this->richProductTypes($typeIds);
        $thin = array_values(array_diff($typeIds, $rich));
        $richIn = implode(',', $rich !== [] ? $rich : [0]);
        $thinIn = implode(',', $thin !== [] ? $thin : [0]);

        return "EXISTS (SELECT 1 FROM supplier_product_types spt WHERE spt.supplier_id = s.id AND ("
            . "(spt.product_type_id IN ($richIn) AND spt.is_included = 1 AND (spt.source IN ('manual','response_positive') OR spt.positive_signals > 0)) "
            . "OR (spt.product_type_id IN ($thinIn) AND spt.is_included = 1)))";
    }

    /**
     * Подмножество типов, где trusted-поставщиков (ответивших/manual) >= порога → тема
     * «проработанная» (rich): probation-кандидатов в общий пул не пускаем. Кэш 10 мин —
     * в одном прогоне плана селектор зовётся многократно по тем же типам.
     *
     * @param array<int,int> $typeIds
     * @return array<int,int>
     */
    private function richProductTypes(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $min = max(1, (int) config('services.email_pool.karma_regime_rich_min', 30));
        sort($typeIds);
        $key = 'pool:rich_types:' . md5(implode(',', $typeIds)) . ':' . $min;

        return Cache::remember($key, now()->addMinutes(10), function () use ($typeIds, $min) {
            $in = implode(',', $typeIds);
            $rows = DB::connection(self::CONN)->select(
                "SELECT spt.product_type_id AS pt FROM supplier_product_types spt "
                . "JOIN suppliers s ON s.id = spt.supplier_id "
                . "WHERE spt.product_type_id IN ($in) AND s.is_active = 1 AND spt.is_included = 1 "
                . "AND (spt.source IN ('manual','response_positive') OR spt.positive_signals > 0) "
                . "GROUP BY spt.product_type_id HAVING COUNT(DISTINCT spt.supplier_id) >= $min"
            );

            return array_map(static fn ($r) => (int) $r->pt, $rows);
        });
    }

    private function karmaEnabled(): bool
    {
        return (bool) config('services.email_pool.karma_gate_enabled', false);
    }

    /**
     * Хард-дроп недоставляемых: пустой/пробельный email — слать некуда. Только при
     * включённом карма-гейте (флаг OFF = поведение без изменений).
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function excludeUndeliverable($query): void
    {
        if ($this->karmaEnabled()) {
            $query->whereRaw("TRIM(COALESCE(s.email, '')) <> ''");
        }
    }

    /**
     * @return array<int,object>
     */
    private function selectOld(Batch $batch): array
    {
        $targetCategories = !empty($batch->targetCategories) ? $batch->targetCategories : ['Все товары'];

        $query = DB::connection(self::CONN)->table('suppliers as s')
            ->distinct()
            ->select('s.id', 's.name', 's.email', 's.contact_person', 's.categories')
            ->where('s.is_active', 1)
            ->where('s.notify_email', 1)
            ->where(function ($q) use ($targetCategories) {
                foreach ($targetCategories as $cat) {
                    // JSON_CONTAINS(s.categories, '"<cat>"') — значение биндим как JSON-строку.
                    $q->orWhereRaw('JSON_CONTAINS(s.categories, ?)', [json_encode((string) $cat, JSON_UNESCAPED_UNICODE)]);
                }
            });

        $this->excludeBlockedDomains($query);
        $this->excludeBlockedRecipients($query);
        $this->excludePaused($query);

        return $query->get()->all();
    }

    /**
     * Исключить поставщиков на ПАУЗЕ по отписке (suppliers.unsubscribe_until в будущем).
     * После истечения паузы поставщик снова попадает в подбор, но с увеличенным личным
     * интервалом (send_interval_override_seconds — чтит DispatchPendingEmails). Полностью
     * отключённые (is_active=0) уже отсечены условием выше.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function excludePaused($query): void
    {
        $query->where(function ($q) {
            $q->whereNull('s.unsubscribe_until')
                ->orWhereRaw('s.unsubscribe_until <= NOW()');
        });
    }

    /**
     * Исключить поставщиков, чей домен email — в блок-листе (reports.blocked_domains):
     * домены, кому рассылку не шлём совсем (жалобы на спам и т.п.). Сверяется по домену
     * primary email поставщика, регистронезависимо. Раздел ответственности:
     * blocked_domains — доменный блок; recipient_mailboxes.is_blocked — per-адресный
     * (см. excludeBlockedRecipients); suppliers.is_active/notify_email — точечный.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function excludeBlockedDomains($query): void
    {
        $query->whereRaw(
            "NOT EXISTS (SELECT 1 FROM blocked_domains bd "
            . "WHERE bd.domain = SUBSTRING_INDEX(LOWER(s.email), '@', -1))"
        );
    }

    /**
     * Исключить поставщиков, чей email — в per-адресном блок-листе
     * (reports.recipient_mailboxes.is_blocked=1): адреса, давшие хард-баунсы/спам-жалобу.
     * Раньше этот блок отсекался ТОЛЬКО на отправке (диспетчер их скипал), из-за чего
     * генератор продолжал штамповать письма на мёртвые ящики — они копились в pending
     * навсегда (dead-weight). Теперь блок применяется и на ГЕНЕРАЦИИ: заблокированный
     * адрес больше не попадает в рассылку в принципе. Сверка по нормализованному email
     * (recipient_mailboxes.email хранится в lower-case), регистронезависимо.
     *
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function excludeBlockedRecipients($query): void
    {
        $query->whereRaw(
            "NOT EXISTS (SELECT 1 FROM recipient_mailboxes rm "
            . "WHERE rm.is_blocked = 1 AND rm.email = LOWER(TRIM(s.email)))"
        );
    }

    /**
     * Приводит к списку положительных целых (для безопасной inline-подстановки в IN).
     *
     * @param array<int,mixed> $values
     * @return array<int,int>
     */
    private function intList(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $n = (int) $v;
            if ($n > 0 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        return $out;
    }
}
