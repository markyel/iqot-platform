<?php

namespace App\Jobs;

use App\Services\Discovery\SupplierDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    ) {
    }

    public function handle(SupplierDiscoveryService $discovery): void
    {
        try {
            $res = $discovery->discoverFromUrl($this->url, $this->productTypeId, $this->domainId);
            Log::info('DiscoverFromCampaignJob: done', [
                'url' => $this->url,
                'product_type_id' => $this->productTypeId,
                'result' => $res,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DiscoverFromCampaignJob: failed', [
                'url' => $this->url,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
        }
    }
}
