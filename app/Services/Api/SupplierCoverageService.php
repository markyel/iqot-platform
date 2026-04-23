<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\DB;

/**
 * Проверка покрытия поставщиков для пары (domain_id, product_type_id).
 * Спека §6.1.
 *
 * Работает на connection=reports.
 * Поле `suppliers.last_response_at` в текущей схеме отсутствует — фильтр по нему
 * опущен. Остальные фильтры соответствуют спеке (is_active=1, notify_email=1,
 * profile_confidence>=0.3) + матчинг scope_domains / scope_product_types через
 * pivot-таблицы с is_included=1.
 */
class SupplierCoverageService
{
    public const DEFAULT_PROFILE_CONFIDENCE_MIN = 0.3;
    public const DEFAULT_THRESHOLD = 50;

    /**
     * @param int|null $domainId null допустим — product_type универсальный.
     * @param int $productTypeId
     * @return array{available:int, threshold:int, is_sufficient:bool}
     */
    public function checkCoverage(?int $domainId, int $productTypeId): array
    {
        $available = $this->countAvailableSuppliers($domainId, $productTypeId);
        $threshold = $this->resolveThreshold($domainId, $productTypeId);

        return [
            'available' => $available,
            'threshold' => $threshold,
            'is_sufficient' => $available >= $threshold,
        ];
    }

    private function countAvailableSuppliers(?int $domainId, int $productTypeId): int
    {
        $q = DB::connection('reports')
            ->table('suppliers as s')
            ->where('s.is_active', 1)
            ->where('s.notify_email', 1)
            ->where('s.profile_confidence', '>=', self::DEFAULT_PROFILE_CONFIDENCE_MIN);

        // domain scope.
        if ($domainId !== null) {
            $q->where(function ($q) use ($domainId) {
                $q->where('s.scope_domains', 'all')
                  ->orWhereExists(function ($sub) use ($domainId) {
                      $sub->select(DB::raw(1))
                          ->from('supplier_domains as sd')
                          ->whereColumn('sd.supplier_id', 's.id')
                          ->where('sd.domain_id', $domainId)
                          ->where('sd.is_included', 1);
                  });
            });
        }

        // product_type scope.
        $q->where(function ($q) use ($productTypeId) {
            $q->where('s.scope_product_types', 'all')
              ->orWhereExists(function ($sub) use ($productTypeId) {
                  $sub->select(DB::raw(1))
                      ->from('supplier_product_types as spt')
                      ->whereColumn('spt.supplier_id', 's.id')
                      ->where('spt.product_type_id', $productTypeId)
                      ->where('spt.is_included', 1);
              });
        });

        return (int) $q->distinct()->count('s.id');
    }

    private function resolveThreshold(?int $domainId, int $productTypeId): int
    {
        $row = DB::connection('reports')
            ->table('product_types as pt')
            ->leftJoin('domain_product_types as dpt', function ($join) use ($domainId) {
                $join->on('dpt.product_type_id', '=', 'pt.id');
                if ($domainId !== null) {
                    $join->where('dpt.domain_id', '=', $domainId);
                }
            })
            ->where('pt.id', $productTypeId)
            ->selectRaw(
                'COALESCE(dpt.min_suppliers_threshold, pt.min_suppliers_threshold, ?) AS threshold',
                [self::DEFAULT_THRESHOLD]
            )
            ->first();

        return (int) ($row->threshold ?? self::DEFAULT_THRESHOLD);
    }
}
