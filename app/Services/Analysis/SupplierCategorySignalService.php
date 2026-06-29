<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Учёт «негативных сигналов» покрытия поставщика по итогам отказа в ответе.
 *
 * При email_type=rejection с информативной причиной (not_our_profile / other —
 * «не наш профиль» / отписки) увеличиваем negative_signals по домену и типу товара
 * отклонённых позиций. При накоплении критической массы снимаем категорию
 * (is_included=0) — её дальше исключает CampaignSupplierSelector.
 *
 * not_available («нет в наличии») НЕ считаем — это временный дефицит, категорию
 * поставщик покрывает. Ручные строки (is_manual=1) авто-снятию не подлежат.
 *
 * Пороги: product_type — services.email_analysis.rejection_product_type_threshold (5),
 * domain — rejection_domain_threshold (10). Коннект reports.
 */
class SupplierCategorySignalService
{
    private const CONN = 'reports';

    /** Причины, которые означают «не та категория» (в отличие от временного not_available). */
    private const COUNTED_REASONS = ['not_our_profile', 'other'];

    public function recordRejection(int $supplierId, int $batchId, ?string $rejectionReason): void
    {
        if ($supplierId <= 0 || $batchId <= 0) {
            return;
        }
        if (!in_array((string) $rejectionReason, self::COUNTED_REASONS, true)) {
            return; // not_available / null — не информативно для снятия категории
        }

        $ptThreshold = (int) config('services.email_analysis.rejection_product_type_threshold', 5);
        $domThreshold = (int) config('services.email_analysis.rejection_domain_threshold', 10);

        [$domainIds, $productTypeIds] = $this->batchCategories($batchId);

        foreach ($productTypeIds as $ptId) {
            $this->bump('supplier_product_types', 'product_type_id', $ptId, $supplierId, $ptThreshold, true);
        }
        foreach ($domainIds as $domId) {
            $this->bump('supplier_domains', 'domain_id', $domId, $supplierId, $domThreshold, false);
        }
    }

    /**
     * Домены и типы товаров позиций батча (по email_batches.request_items → request_items).
     *
     * @return array{0:array<int,int>,1:array<int,int>}
     */
    private function batchCategories(int $batchId): array
    {
        $raw = DB::connection(self::CONN)->table('email_batches')->where('id', $batchId)->value('request_items');
        $ids = [];
        if (is_string($raw) && $raw !== '') {
            $dec = json_decode($raw, true);
            if (is_array($dec)) {
                $ids = array_values(array_filter(array_map('intval', $dec), static fn ($v) => $v > 0));
            }
        }
        if ($ids === []) {
            return [[], []];
        }

        $rows = DB::connection(self::CONN)->table('request_items')->whereIn('id', $ids)
            ->get(['domain_id', 'product_type_id']);

        $domains = [];
        $types = [];
        foreach ($rows as $r) {
            if (!empty($r->domain_id)) {
                $domains[(int) $r->domain_id] = true;
            }
            if (!empty($r->product_type_id)) {
                $types[(int) $r->product_type_id] = true;
            }
        }

        return [array_keys($domains), array_keys($types)];
    }

    private function bump(string $table, string $col, int $catId, int $supplierId, int $threshold, bool $hasLastSignal): void
    {
        $row = DB::connection(self::CONN)->table($table)
            ->where('supplier_id', $supplierId)->where($col, $catId)->first();

        if ($row === null) {
            $ins = [
                'supplier_id' => $supplierId,
                $col => $catId,
                'is_included' => 1,
                'source' => 'response_negative',
                'negative_signals' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if ($hasLastSignal) {
                $ins['last_signal_at'] = now();
            }
            DB::connection(self::CONN)->table($table)->insert($ins);

            return;
        }

        $neg = (int) ($row->negative_signals ?? 0) + 1;
        $upd = ['negative_signals' => $neg, 'updated_at' => now()];
        if ($hasLastSignal) {
            $upd['last_signal_at'] = now();
        }

        $removed = false;
        if ($neg >= $threshold && !((int) ($row->is_manual ?? 0)) && (int) ($row->is_included ?? 1) === 1) {
            $upd['is_included'] = 0;
            $removed = true;
        }

        DB::connection(self::CONN)->table($table)
            ->where('supplier_id', $supplierId)->where($col, $catId)->update($upd);

        if ($removed) {
            Log::info('SupplierCategorySignal: category auto-removed', [
                'table' => $table, 'cat_id' => $catId, 'supplier_id' => $supplierId, 'negative_signals' => $neg,
            ]);
        }
    }
}
