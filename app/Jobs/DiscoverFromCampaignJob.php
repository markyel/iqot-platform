<?php

namespace App\Jobs;

use App\Services\Discovery\SupplierDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * #4 фаза 4b: discovery нового поставщика по URL, найденному предрассылочным
 * таргетингом (SupplierTargetingService). product_type/domain берутся из запрошенной
 * позиции, по которой домен всплыл в Яндексе — анализ сайта + авто-добавление с
 * таксономией делает SupplierDiscoveryService::discoverFromUrl. Очередь — default
 * (iqot-queue-worker), чтобы не конкурировать с генерацией/рассылкой.
 */
class DiscoverFromCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        private readonly string $url,
        private readonly int $productTypeId,
        private readonly ?int $domainId = null,
        private readonly ?int $batchId = null,
        private readonly ?int $requestId = null,
        private readonly ?int $deferredBatchId = null,
    ) {
    }

    public function handle(SupplierDiscoveryService $discovery): void
    {
        try {
            $res = $discovery->discoverFromUrl($this->url, $this->productTypeId, $this->domainId);

            // Привязка найденного поставщика к батчу-источнику → волна 2 его подхватит.
            $supplierId = (int) ($res['supplier_id'] ?? 0);
            if ($this->batchId && $supplierId > 0 && in_array(($res['status'] ?? ''), ['created', 'extended'], true)) {
                DB::connection('reports')->table('campaign_discoveries')->insertOrIgnore([
                    'batch_id' => $this->batchId,
                    'request_id' => $this->requestId,
                    'supplier_id' => $supplierId,
                    'source_url' => mb_substr($this->url, 0, 500),
                    'emailed' => 0,
                    'created_at' => now(),
                ]);
            }

            Log::info('DiscoverFromCampaignJob: done', [
                'url' => $this->url,
                'product_type_id' => $this->productTypeId,
                'batch_id' => $this->batchId,
                'result' => $res,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DiscoverFromCampaignJob: failed', [
                'url' => $this->url,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        } finally {
            // Счётчик готовности отложенного батча: любой исход = обработан. Когда все
            // кандидаты обработаны → deferred_batch готов к повтору (emails:retry-deferred).
            if ($this->deferredBatchId) {
                DB::connection('reports')->table('deferred_batches')
                    ->where('id', $this->deferredBatchId)
                    ->increment('candidates_done', 1, ['updated_at' => now()]);
                DB::connection('reports')->table('deferred_batches')
                    ->where('id', $this->deferredBatchId)
                    ->where('status', 'pending')
                    ->whereColumn('candidates_done', '>=', 'candidates_total')
                    ->update(['status' => 'ready', 'updated_at' => now()]);
            }
        }
    }
}
