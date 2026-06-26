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

        // Дозаполняем батч профильным списком (его потребляют CampaignEmailBuilder
        // и CampaignPersister: per-supplier письма + email_batches.supplier_ids).
        $batch->suppliers = $suppliers;
        $batch->supplierIds = [];
        foreach ($suppliers as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0 && !in_array($id, $batch->supplierIds, true)) {
                $batch->supplierIds[] = $id;
            }
        }

        return $suppliers;
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

        return $query->get()->all();
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
