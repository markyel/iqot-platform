<?php

namespace App\Services\Monitoring\Discovery;

use App\Jobs\Monitoring\MatchIndexJob;
use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpSite;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\DB;

/**
 * Страницы из индекса каталогов сайтов (SitemapMatcher) → листинги (found_via=sitemap). Проверку не ставим напрямую:
 * next_check_at=now, темп задаёт monitoring:dispatch-checks (кап на сайт за тик). Сопоставление новых товаров с
 * индексом — сразу при загрузке позиций (25.11.2026, решение владельца), а не только ночью.
 */
final class IndexListings
{
    /** Кандидаты только по названию встают в проверку позже артикульных (25.11.2026: 34 тыс. кандидатов разом). */
    public const NAME_DELAY_DAYS = 3;

    /**
     * Совпадение по артикулу — проверка сразу (found_via=sitemap); только по названию — если по артикулу на этом сайте
     * у товара совпадений нет, и проверка через NAME_DELAY_DAYS (found_via=sitemap_name): по опыту такие страницы чаще
     * чужие товары, а первая проверка — загрузка и ИИ.
     *
     * @param array<int, array<int, array{url:string, via?:string}>> $cands product_id => кандидаты
     */
    public static function create(MpSite $site, array $cands): int
    {
        $created = 0;
        foreach ($cands as $pid => $list) {
            $hasCode = collect($list)->contains(fn ($c) => ($c['via'] ?? 'code') === 'code');
            foreach ($list as $c) {
                $byName = ($c['via'] ?? 'code') === 'name';
                if ($byName && $hasCode) {
                    continue;
                }
                $listing = MpListing::query()->firstOrCreate(
                    ['product_id' => $pid, 'url_hash' => UrlCanonicalizer::hash($c['url'])],
                    ['site_id' => $site->id, 'url' => UrlCanonicalizer::canonicalize($c['url']), 'status' => MpListing::STATUS_ACTIVE,
                        'found_via' => $byName ? 'sitemap_name' : 'sitemap', 'bench' => false,
                        'next_check_at' => $byName ? now()->addDays(self::NAME_DELAY_DAYS) : now()]
                );
                if ($listing->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Сопоставить товары с индексом всех проиндексированных сайтов — по заданию на сайт (очередь monitor-import).
     *
     * @param array<int,int> $productIds
     */
    public static function dispatchFor(array $productIds): int
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($productIds === []) {
            return 0;
        }
        $sites = DB::table('mp_sites')->where('sitemap_pages', '>', 0)
            ->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '<', now()))->pluck('id');
        foreach ($sites as $siteId) {
            foreach (array_chunk($productIds, 500) as $chunk) {
                MatchIndexJob::dispatch((int) $siteId, $chunk);
            }
        }

        return count($sites);
    }
}
