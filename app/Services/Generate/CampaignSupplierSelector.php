<?php

namespace App\Services\Generate;

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

        // Адаптивный двухволновой пул: при сверх-большом пуле волна 1 = ужесточённый
        // поднабор, остальное → пул расширения (волна 2, досыл при малом отклике).
        [$wave1, $expansion] = $this->splitPool($batch, $suppliers);

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
        $this->excludePaused($query);

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
            $query->where(function ($q) use ($in) {
                $q->where(function ($q2) use ($in) {
                    $q2->where('s.scope_product_types', 'all')
                        ->whereRaw("NOT EXISTS (SELECT 1 FROM supplier_product_types spt WHERE spt.supplier_id = s.id AND spt.product_type_id IN ($in) AND spt.is_included = 0)");
                })->orWhereRaw("EXISTS (SELECT 1 FROM supplier_product_types spt WHERE spt.supplier_id = s.id AND spt.product_type_id IN ($in) AND spt.is_included = 1)");
            });
        }

        return $query->get()->all();
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
     * blocked_domains — доменный блок на ГЕНЕРАЦИИ; recipient_mailboxes.is_blocked —
     * per-адресный блок на ОТПРАВКЕ; suppliers.is_active/notify_email — точечный.
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
