<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Discovery\ListingDiscoverer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Поиск листингов одного товара (очередь monitor): запросы в Яндекс → новые
 * листинги → сразу в проверку (CheckListingJob). Состояние товара (found /
 * not_found) подводит monitoring:refresh-coverage, когда проверки отработают.
 */
class DiscoverProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(private readonly int $productId)
    {
        $this->onQueue('monitor');
    }

    public function handle(): void
    {
        $lock = Cache::lock("monitor:discover:{$this->productId}", 590);
        if (!$lock->get()) {
            return;
        }
        try {
            $product = MpProduct::find($this->productId);
            if (!$product) {
                return;
            }
            $res = ListingDiscoverer::make()->discover($product);
            if ($res['status'] === 'quota') {
                $product->forceFill(['next_search_at' => now()->addDay()->startOfDay()->addHours(1)])->save();

                return;
            }
            foreach ($res['created'] as $listingId) {
                CheckListingJob::dispatch($listingId);
            }
            Log::info('DiscoverProductJob', ['product_id' => $product->id] + $res);
        } finally {
            $lock->release();
        }
    }
}
