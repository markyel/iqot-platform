<?php

namespace App\Services\Analysis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сигналы покрытия поставщика по итогам ответа на запрос.
 *
 * ОТКАЗ (email_type=rejection, причина not_our_profile / other — «не наш профиль» /
 * отписки; not_available НЕ считаем — временный дефицит) → negative_signals++ по
 * домену и типу товара отклонённых позиций. Критическая масса → is_included=0
 * (категория снимается, дальше CampaignSupplierSelector её исключает).
 *
 * ОФФЕР (есть offers) → positive_signals++ по домену/типу предложенных позиций,
 * negative_signals сбрасывается (оффер доказывает покрытие), и если категория была
 * авто-снята — восстанавливается (is_included=1).
 *
 * Ручные строки (is_manual=1) авто-снятию/восстановлению не подлежат. Коннект reports.
 * Пороги: product_type — services.email_analysis.rejection_product_type_threshold (5),
 * domain — rejection_domain_threshold (10).
 */
class SupplierCategorySignalService
{
    private const CONN = 'reports';

    /** Причины, означающие «не та категория» (в отличие от временного not_available). */
    private const COUNTED_REASONS = ['not_our_profile', 'other'];

    /**
     * Негативный сигнал по всем позициям батча (отказ касается всего запроса).
     */
    public function recordRejection(int $supplierId, int $batchId, ?string $rejectionReason): void
    {
        if ($supplierId <= 0 || $batchId <= 0) {
            return;
        }
        if (!in_array((string) $rejectionReason, self::COUNTED_REASONS, true)) {
            return;
        }

        $ptThreshold = (int) config('services.email_analysis.rejection_product_type_threshold', 5);
        $domThreshold = (int) config('services.email_analysis.rejection_domain_threshold', 10);

        [$domainIds, $productTypeIds] = $this->categoriesForItems($this->batchItemIds($batchId));

        foreach ($productTypeIds as $ptId) {
            $this->bumpNegative('supplier_product_types', 'product_type_id', $ptId, $supplierId, $ptThreshold, true);
        }
        foreach ($domainIds as $domId) {
            $this->bumpNegative('supplier_domains', 'domain_id', $domId, $supplierId, $domThreshold, false);
        }
    }

    /**
     * Позитивный сигнал по предложенным позициям (оффер доказывает покрытие точечно).
     *
     * @param array<int,int> $offerItemIds request_items.id, по которым есть оффер
     */
    public function recordOffer(int $supplierId, array $offerItemIds): void
    {
        if ($supplierId <= 0 || $offerItemIds === []) {
            return;
        }

        [$domainIds, $productTypeIds] = $this->categoriesForItems($offerItemIds);

        foreach ($productTypeIds as $ptId) {
            $this->bumpPositive('supplier_product_types', 'product_type_id', $ptId, $supplierId, true);
        }
        foreach ($domainIds as $domId) {
            $this->bumpPositive('supplier_domains', 'domain_id', $domId, $supplierId, false);
        }
    }

    /**
     * @return array<int,int> request_items.id позиций батча
     */
    private function batchItemIds(int $batchId): array
    {
        $raw = DB::connection(self::CONN)->table('email_batches')->where('id', $batchId)->value('request_items');
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $dec), static fn ($v) => $v > 0));
    }

    /**
     * @param array<int,int> $itemIds
     * @return array{0:array<int,int>,1:array<int,int>} [domainIds, productTypeIds]
     */
    private function categoriesForItems(array $itemIds): array
    {
        if ($itemIds === []) {
            return [[], []];
        }

        $rows = DB::connection(self::CONN)->table('request_items')->whereIn('id', $itemIds)
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

    private function bumpNegative(string $table, string $col, int $catId, int $supplierId, int $threshold, bool $hasLastSignal): void
    {
        $row = $this->row($table, $col, $catId, $supplierId);

        if ($row === null) {
            $this->insertRow($table, $col, $catId, $supplierId, 'response_negative', ['negative_signals' => 1], $hasLastSignal);

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

        $this->update($table, $col, $catId, $supplierId, $upd);

        if ($removed) {
            Log::info('SupplierCategorySignal: category auto-removed', [
                'table' => $table, 'cat_id' => $catId, 'supplier_id' => $supplierId, 'negative_signals' => $neg,
            ]);
        }
    }

    private function bumpPositive(string $table, string $col, int $catId, int $supplierId, bool $hasLastSignal): void
    {
        $row = $this->row($table, $col, $catId, $supplierId);

        if ($row === null) {
            $this->insertRow($table, $col, $catId, $supplierId, 'response_positive', ['positive_signals' => 1, 'negative_signals' => 0], $hasLastSignal);

            return;
        }

        $pos = (int) ($row->positive_signals ?? 0) + 1;
        $upd = ['positive_signals' => $pos, 'negative_signals' => 0, 'updated_at' => now()];
        if ($hasLastSignal) {
            $upd['last_signal_at'] = now();
        }

        $restored = false;
        if ((int) ($row->is_included ?? 1) === 0 && !((int) ($row->is_manual ?? 0))) {
            $upd['is_included'] = 1;
            $upd['source'] = 'response_positive';
            $restored = true;
        }

        $this->update($table, $col, $catId, $supplierId, $upd);

        if ($restored) {
            Log::info('SupplierCategorySignal: category restored by offer', [
                'table' => $table, 'cat_id' => $catId, 'supplier_id' => $supplierId, 'positive_signals' => $pos,
            ]);
        }
    }

    private function row(string $table, string $col, int $catId, int $supplierId): ?object
    {
        return DB::connection(self::CONN)->table($table)
            ->where('supplier_id', $supplierId)->where($col, $catId)->first();
    }

    /**
     * @param array<string,mixed> $signals
     */
    private function insertRow(string $table, string $col, int $catId, int $supplierId, string $source, array $signals, bool $hasLastSignal): void
    {
        $ins = array_merge([
            'supplier_id' => $supplierId,
            $col => $catId,
            'is_included' => 1,
            'source' => $source,
            'created_at' => now(),
            'updated_at' => now(),
        ], $signals);
        if ($hasLastSignal) {
            $ins['last_signal_at'] = now();
        }
        DB::connection(self::CONN)->table($table)->insert($ins);
    }

    /**
     * @param array<string,mixed> $upd
     */
    private function update(string $table, string $col, int $catId, int $supplierId, array $upd): void
    {
        DB::connection(self::CONN)->table($table)
            ->where('supplier_id', $supplierId)->where($col, $catId)->update($upd);
    }
}
