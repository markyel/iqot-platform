<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: индекс страниц сайта продавца по sitemap → mp_site_pages (без Яндекса).
 *
 * Корни — строки Sitemap: из robots.txt, иначе /sitemap.xml. Индексы разворачиваются
 * (сначала дочерние с product/catalog/goods/shop в имени, без news/blog/article/image),
 * .gz распаковываются. Загрузка — тем же вежливым PageFetcher через канал сайта
 * (EgressPool), лимит на домен соблюдается ожиданием слота. Страницы, пропавшие из
 * sitemap, удаляются из индекса по завершении полного прохода.
 */
class SitemapIndexer
{
    private const PRIORITY_RE = '~product|catalog|goods|shop|tovar|item|offer|iblock~i';
    private const SKIP_RE = '~news|blog|article|stati|image|video|post|tag|author|brand-?page~i';

    /** Служебные разделы сайта — не карточки (теги, бренды, статьи, «о компании»…). */
    public const NON_PRODUCT_URL_RE = '~/(tags?|brands?|proizvoditeli|blog|news|novosti|articles?|stati|about|o-kompanii|o_kompanii|kontakty|contacts?|dostavka|oplata|delivery|payment|faq|reviews?|otzyvy|vacanc\w*|sertifikaty|uslugi|services|sitemap|politika\w*|privacy|agreement|cart|basket|compare|wishlist|login|register|personal|account)(/|$|\.)~i';

    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly EgressPool $pool,
    ) {
    }

    public static function make(): self
    {
        return new self(app(PageFetcher::class), EgressPool::make());
    }

    /**
     * Потолок адресов на сайт. Профильные продавцы — до ~25 тыс. карточек; sitemap на сотни
     * тысяч — универсальный магазин (автозапчасти, электроника), а не поставщик лифтового
     * оборудования. 15.10.2026 без потолка `--all` занёс 132 сайта / 13,7 млн строк → бинлог
     * MySQL забил диск прода.
     */
    public const MAX_PAGES = 60_000;

    /**
     * @return array{status: string, files: int, pages: int, error?: string}
     *   status: ok | partial | empty | too_big | banned
     */
    public function index(MpSite $site, int $maxFiles = 80, int $maxPages = self::MAX_PAGES): array
    {
        $channel = $this->pool->pick($site->domain, $site->egress);
        if ($channel === null) {
            return ['status' => 'banned', 'files' => 0, 'pages' => 0];
        }
        $started = now();
        $origin = 'https://' . $site->domain;
        // robots.txt — один раз на прогон: и корни sitemap, и фильтр запрещённых адресов.
        $robots = $this->fetcher->robotsFor($origin, $channel['proxy']);
        $queue = $site->sitemap_url ? [$site->sitemap_url] : ($robots?->sitemaps() ?: [$origin . '/sitemap.xml']);
        $root = $queue[0];

        $seenFiles = [];
        $files = 0;
        $pages = 0;
        $buffer = [];
        $tooBig = false;
        while ($queue !== [] && $files < $maxFiles && !$tooBig) {
            $url = array_shift($queue);
            if (isset($seenFiles[$url])) {
                continue;
            }
            $seenFiles[$url] = true;
            $body = $this->get($url, $channel, (int) $site->rate_limit_ms);
            $files++;
            if ($body === null) {
                continue;
            }
            if (str_contains(substr($body, 0, 2000), '<sitemapindex')) {
                $children = $this->locs($body);
                $children = array_values(array_filter($children, fn ($c) => !preg_match(self::SKIP_RE, (string) parse_url($c, PHP_URL_PATH))));
                usort($children, fn ($a, $b) => (int) !preg_match(self::PRIORITY_RE, $a) <=> (int) !preg_match(self::PRIORITY_RE, $b));
                array_push($queue, ...$children);
                continue;
            }
            foreach ($this->urlEntries($body) as [$loc, $lastmod]) {
                if (UrlCanonicalizer::registrableDomain($loc) !== $site->domain || UrlCanonicalizer::looksLikeNonProduct($loc)
                    || preg_match(self::NON_PRODUCT_URL_RE, (string) parse_url($loc, PHP_URL_PATH))
                    || ($robots !== null && !$robots->isAllowed($this->pathOf($loc), 'iqotbot'))) {
                    continue;
                }
                // Копим в памяти: в БД — только после прохода в пределах потолка.
                $buffer[UrlCanonicalizer::hash($loc)] = [
                    'site_id' => $site->id, 'url' => mb_substr($loc, 0, 1000), 'url_hash' => UrlCanonicalizer::hash($loc),
                    'lastmod' => $lastmod, 'seen_at' => now(),
                ];
                $pages = count($buffer);
                if ($pages > $maxPages) {
                    $tooBig = true;
                    break;
                }
            }
        }

        if ($tooBig) {
            DB::table('mp_site_pages')->where('site_id', $site->id)->delete();
            $site->forceFill(['sitemap_indexed_at' => now(), 'sitemap_pages' => 0])->save();

            return ['status' => 'too_big', 'files' => $files, 'pages' => $pages];
        }

        foreach (array_chunk(array_values($buffer), 1000) as $chunk) {
            $this->flush($chunk);
        }
        $complete = $queue === [] && $files < $maxFiles;
        if ($complete && $pages > 0) {
            DB::table('mp_site_pages')->where('site_id', $site->id)->where('seen_at', '<', $started)->delete();
        }
        $site->forceFill([
            'sitemap_url' => $site->sitemap_url ?: $root,
            'sitemap_indexed_at' => now(),
            'sitemap_pages' => DB::table('mp_site_pages')->where('site_id', $site->id)->count(),
        ])->save();

        return ['status' => $pages > 0 ? ($complete ? 'ok' : 'partial') : 'empty', 'files' => $files, 'pages' => $pages];
    }

    /** Тело файла sitemap (с ожиданием слота домена); null — не получили. */
    private function get(string $url, array $channel, int $rateMs): ?string
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $res = $this->fetcher->fetch($url, [
                'egress' => $channel, 'rate_ms' => $rateMs, 'respect_robots' => false,
                'timeout' => 60, 'max_bytes' => 60_000_000,
            ]);
            if ($res->deferred()) {
                usleep(max(200, $res->retryAfterMs) * 1000);
                continue;
            }
            if (!$res->ok()) {
                return null;
            }
            $body = $res->body;
            if (str_starts_with($body, "\x1f\x8b")) {
                $body = @gzdecode($body) ?: '';
            }

            return $body !== '' ? $body : null;
        }

        return null;
    }

    /** @return array<int,string> */
    private function locs(string $xml): array
    {
        preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?\s*([^<\]]+?)\s*(?:\]\]>)?\s*</loc>~i', $xml, $m);

        return array_map(fn ($u) => html_entity_decode(trim($u), ENT_QUOTES | ENT_XML1), $m[1]);
    }

    /** @return \Generator<int, array{0: string, 1: ?string}> [loc, lastmod] */
    private function urlEntries(string $xml): \Generator
    {
        $offset = 0;
        while (($start = stripos($xml, '<url>', $offset)) !== false) {
            $end = stripos($xml, '</url>', $start);
            if ($end === false) {
                break;
            }
            $chunk = substr($xml, $start, $end - $start);
            $offset = $end + 6;
            $loc = $this->locs($chunk)[0] ?? null;
            if ($loc === null || !preg_match('~^https?://~i', $loc)) {
                continue;
            }
            $lastmod = null;
            if (preg_match('~<lastmod>\s*([^<]+?)\s*</lastmod>~i', $chunk, $lm)) {
                $ts = strtotime($lm[1]);
                $lastmod = $ts ? date('Y-m-d H:i:s', $ts) : null;
            }
            yield [$loc, $lastmod];
        }
    }

    private function pathOf(string $url): string
    {
        $p = parse_url($url);

        return ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private function flush(array &$buffer): void
    {
        if ($buffer === []) {
            return;
        }
        DB::table('mp_site_pages')->upsert($buffer, ['url_hash'], ['site_id', 'url', 'lastmod', 'seen_at']);
        $buffer = [];
    }
}
