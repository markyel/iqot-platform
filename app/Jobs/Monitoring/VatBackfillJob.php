<?php

namespace App\Jobs\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\HtmlText;
use App\Support\Monitoring\VatDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Досчёт НДС у уже найденной цены (24.11.2026): загрузить страницу и определить только признак НДС (VatDetector),
 * без ИИ и без смены цены. Очередь monitor, вежливость как у проверки (слот домена занят — release).
 */
class VatBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 0;

    public function __construct(private readonly int $listingId)
    {
        $this->onQueue('monitor');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(PageFetcher $fetcher): void
    {
        $l = MpListing::query()->with('site')->find($this->listingId);
        if (!$l || $l->vat_included !== null || $l->price === null || !$l->site || $l->site->isBlocked()) {
            return;
        }
        $pool = EgressPool::make();
        $channel = $pool->pick($l->site->domain, $l->site->egress);
        if ($channel === null) {
            return;
        }
        $res = $fetcher->fetch($l->url, ['egress' => $channel, 'rate_ms' => (int) $l->site->rate_limit_ms]);
        if ($res->deferred()) {
            if ($this->attempts() < 40) {
                $this->release(max(1, (int) ceil($res->retryAfterMs / 1000)));
            }

            return;
        }
        if (!$res->ok() || !$res->isHtml()) {
            return;
        }
        $vat = VatDetector::detect(HtmlText::toText($res->body), (float) $l->price);
        if ($vat === null) {
            return;
        }
        $l->forceFill(['vat_included' => $vat])->save();
        // Курсор выдачи (prices?updated_since) — клиент заберёт признак НДС.
        DB::table('program_items')->where('product_id', $l->product_id)->update(['prices_changed_at' => now()]);
    }
}
