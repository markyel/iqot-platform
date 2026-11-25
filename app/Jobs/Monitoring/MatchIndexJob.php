<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Discovery\IndexListings;
use App\Services\Monitoring\Discovery\SitemapMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Новые товары × индекс каталога одного сайта → листинги (при загрузке позиций, 25.11.2026). Очередь monitor-import. */
class MatchIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** @param array<int,int> $productIds */
    public function __construct(private readonly int $siteId, private readonly array $productIds)
    {
        $this->onQueue('monitor-import');
    }

    public function handle(): void
    {
        $site = MpSite::find($this->siteId);
        if (!$site || $site->isBlocked() || (int) $site->sitemap_pages === 0) {
            return;
        }
        $products = MpProduct::query()->with('identifiers')->whereIn('id', $this->productIds)->get();
        if ($products->isEmpty()) {
            return;
        }
        $created = IndexListings::create($site, (new SitemapMatcher())->match($site->id, $products));
        if ($created > 0) {
            Log::info('MatchIndexJob: листинги из индекса', ['site' => $site->domain, 'products' => $products->count(), 'created' => $created]);
        }
    }
}
