<?php

namespace App\Jobs\Monitoring;

use App\Services\Discovery\SupplierDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сайт мониторинга с ценой, но без поставщика → проверка как кандидата в поставщики IQOT
 * (SiteSupplierDiscovery). Очередь default (iqot-queue-worker), как DiscoverFromCampaignJob.
 */
class DiscoverSiteSupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        private readonly int $siteId,
        private readonly string $url,
        private readonly int $productTypeId,
        private readonly ?int $domainId = null,
    ) {
    }

    public function handle(SupplierDiscoveryService $discovery): void
    {
        $result = 'failed';
        try {
            $res = $discovery->discoverFromUrl($this->url, $this->productTypeId, $this->domainId);
            $status = (string) ($res['status'] ?? 'skip');
            $result = $status === 'skip' ? 'skip:' . ($res['reason'] ?? '') : $status;
            $supplierId = (int) ($res['supplier_id'] ?? 0);
            if ($supplierId > 0 && in_array($status, ['created', 'extended'], true)) {
                DB::table('mp_sites')->where('id', $this->siteId)->whereNull('supplier_id')
                    ->update(['supplier_id' => $supplierId]);
            }
            Log::info('DiscoverSiteSupplierJob: done', ['site_id' => $this->siteId, 'url' => $this->url, 'result' => $res]);
        } catch (\Throwable $e) {
            Log::warning('DiscoverSiteSupplierJob: failed', ['site_id' => $this->siteId, 'url' => $this->url,
                'error' => mb_substr($e->getMessage(), 0, 300)]);
        } finally {
            DB::table('mp_sites')->where('id', $this->siteId)->update([
                'supplier_discovery_at' => now(),
                'supplier_discovery_result' => mb_substr($result, 0, 60),
                'updated_at' => now(),
            ]);
        }
    }
}
