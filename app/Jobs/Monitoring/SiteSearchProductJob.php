<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Discovery\SiteSearch;
use App\Services\Monitoring\MonitoringGovernor;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Ф2: поиск одного товара на сайте продавца (очередь monitor) → листинги found_via=site_search
 * → CheckListingJob. Вежливость как у проверки: слот домена занят — release без роста попыток;
 * бан (401/403/капча) — бан канала для домена; отказ прокси — канал down.
 * Повторно тот же товар на том же сайте не ищем 30 дней (кэш-метка).
 */
class SiteSearchProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 0;

    private const MAX_DEFERRALS = 40;

    public function __construct(private readonly int $siteId, private readonly int $productId)
    {
        $this->onQueue('monitor');
    }

    public static function doneKey(int $siteId, int $productId): string
    {
        return "mon:ss:{$siteId}:{$productId}";
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(PageFetcher $fetcher, MonitoringGovernor $governor): void
    {
        $site = MpSite::find($this->siteId);
        $product = MpProduct::query()->with('identifiers')->find($this->productId);
        if (!$site || !$product || $site->isBlocked() || !$site->search_url_template) {
            return;
        }
        $pool = EgressPool::make();
        $channel = $pool->exhaustedFor($site->domain) ? null : $pool->pick($site->domain, $site->egress);
        if ($channel === null) {
            return;
        }
        if (!$governor->available('fetch_http')) {
            $this->release(3600);

            return;
        }
        $search = new SiteSearch($fetcher, $pool);
        $url = $search->searchUrl($site, $product);
        if ($url === null) {
            return;
        }

        $res = $fetcher->fetch($url, ['egress' => $channel, 'rate_ms' => (int) $site->rate_limit_ms]);
        if ($res->deferred()) {
            if ($this->attempts() < self::MAX_DEFERRALS) {
                $this->release(max(1, (int) ceil($res->retryAfterMs / 1000)));
            }

            return;
        }
        $governor->record('fetch_http', 1, 0.0);
        if ($res->proxyFailed) {
            $pool->markDown($channel['name'], (string) $res->error);
            $this->release(60);

            return;
        }
        if (in_array($res->status, [401, 403], true)) {
            $pool->markBanned($channel['name'], $site->domain, "site search HTTP {$res->status}");

            return;
        }

        $created = 0;
        foreach ($search->candidates($res, $site, $product) as $href => $via) {
            $listing = MpListing::query()->firstOrCreate(
                ['product_id' => $product->id, 'url_hash' => UrlCanonicalizer::hash($href)],
                ['site_id' => $site->id, 'url' => UrlCanonicalizer::canonicalize($href), 'status' => MpListing::STATUS_ACTIVE,
                    'found_via' => 'site_search', 'bench' => false, 'next_check_at' => now()]
            );
            if ($listing->wasRecentlyCreated) {
                CheckListingJob::dispatch($listing->id);
                $created++;
            }
        }
        Cache::put(self::doneKey($site->id, $product->id), $created, now()->addDays(30));
    }
}
