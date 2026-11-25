<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpProduct;
use App\Services\Monitoring\Discovery\IndexListings;
use App\Services\Monitoring\Discovery\SitemapMatcher;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Разовая перекладка непроверенных кандидатов из индекса (25.11.2026): совпавшие только по названию —
 * found_via=sitemap_name и проверка через IndexListings::NAME_DELAY_DAYS; если на том же сайте у товара есть
 * совпадение по артикулу, кандидат по названию снимается с очереди (status=gone, не проверялся).
 */
class IndexReprioritizeCommand extends Command
{
    protected $signature = 'monitoring:index-reprioritize {--since=2026-11-25 12:00:00} {--dry-run}';

    protected $description = 'Мониторинг: кандидаты из индекса по названию — после артикульных';

    public function handle(): int
    {
        $since = (string) $this->option('since');
        $rows = DB::table('mp_listings')->where('found_via', 'sitemap')->whereNull('checked_at')->where('bench', false)
            ->where('created_at', '>=', $since)->get(['id', 'product_id', 'site_id', 'url_hash'])->groupBy('site_id');
        $deferred = 0;
        $dropped = 0;
        $matcher = new SitemapMatcher();
        foreach ($rows as $siteId => $list) {
            $products = MpProduct::query()->with('identifiers')->whereIn('id', $list->pluck('product_id')->unique())->get();
            $cands = $matcher->match((int) $siteId, $products);
            $nameIds = [];
            $dropIds = [];
            $byKey = $list->keyBy(fn ($l) => $l->product_id . '|' . $l->url_hash);
            foreach ($cands as $pid => $cs) {
                $hasCode = collect($cs)->contains(fn ($c) => $c['via'] === 'code');
                foreach ($cs as $c) {
                    if ($c['via'] !== 'name') {
                        continue;
                    }
                    $l = $byKey[$pid . '|' . UrlCanonicalizer::hash($c['url'])] ?? null;
                    if ($l === null) {
                        continue;
                    }
                    if ($hasCode) {
                        $dropIds[] = $l->id;
                    } else {
                        $nameIds[] = $l->id;
                    }
                }
            }
            if (!$this->option('dry-run')) {
                foreach (array_chunk($nameIds, 1000) as $ids) {
                    DB::table('mp_listings')->whereIn('id', $ids)->update(['found_via' => 'sitemap_name',
                        'next_check_at' => now()->addDays(IndexListings::NAME_DELAY_DAYS), 'updated_at' => now()]);
                }
                foreach (array_chunk($dropIds, 1000) as $ids) {
                    DB::table('mp_listings')->whereIn('id', $ids)->update(['found_via' => 'sitemap_name', 'status' => 'gone',
                        'next_check_at' => null, 'updated_at' => now()]);
                }
            }
            $deferred += count($nameIds);
            $dropped += count($dropIds);
        }
        $this->info("Кандидатов по названию: отложено {$deferred}, снято (есть совпадение по артикулу на том же сайте) {$dropped}"
            . ($this->option('dry-run') ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
