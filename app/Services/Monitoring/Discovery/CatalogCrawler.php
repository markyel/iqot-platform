<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpSite;
use App\Services\Analysis\HeadlessPageRenderer;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\DB;

/**
 * Обход каталога сайта-лидера без sitemap или с неполным sitemap (25.11.2026, шаг 2 индекса лидеров): шаблон адреса
 * карточки выводится по уже известным карточкам сайта (общий префикс пути + глубина), обход идёт от главной и
 * разделов известных карточек по ссылкам внутри каталога (и страницам пагинации), адреса карточек ложатся в индекс
 * (mp_site_pages) — их загружает обычный индексатор (monitoring:index-pages: название, артикулы, цена). robots.txt
 * соблюдается, пауза сайта — как у остальных загрузок.
 */
class CatalogCrawler
{
    private const NAV_RE = '~/(search|poisk|cart|basket|korzina|compare|wishlist|login|auth|personal|register|account|checkout|order|bitrix|ajax|upload|news|blog|articles?|contacts?|about|vacanc)(/|\?|$)|\.(jpe?g|png|gif|webp|svg|pdf|docx?|xlsx?|zip|rar)$|^(tel|mailto|javascript):~i';

    private const PAGINATION_RE = '~^(PAGEN_\d+|page|p|start|offset|pg)$~i';

    public function __construct(private readonly PageFetcher $fetcher, private readonly ?HeadlessPageRenderer $headless = null)
    {
    }

    public static function make(): self
    {
        return new self(app(PageFetcher::class), app(HeadlessPageRenderer::class));
    }

    /**
     * Шаблон карточки по известным адресам: преобладающая глубина пути (≥60% адресов) и общий префикс сегментов.
     *
     * @param array<int,string> $urls
     * @return array{prefix: array<int,string>, depth: int}|null
     */
    public static function productPattern(array $urls): ?array
    {
        $byDepth = [];
        foreach ($urls as $u) {
            $segs = self::segments((string) parse_url($u, PHP_URL_PATH));
            if ($segs !== []) {
                $byDepth[count($segs)][] = $segs;
            }
        }
        if ($byDepth === []) {
            return null;
        }
        uasort($byDepth, fn ($a, $b) => count($b) <=> count($a));
        $depth = (int) array_key_first($byDepth);
        $group = $byDepth[$depth];
        if (count($group) < max(3, 0.6 * count($urls))) {
            return null;
        }
        // Сегмент входит в шаблон, если его значение у ≥70% карточек (среди известных страниц бывают и разделы:
        // liftformat /magazin/folder/… рядом с /magazin/product/…).
        $prefix = [];
        for ($i = 0; $i < $depth - 1; $i++) {
            $counts = array_count_values(array_map(fn ($s) => $s[$i], $group));
            arsort($counts);
            $top = (string) array_key_first($counts);
            if ($counts[$top] < 0.7 * count($group)) {
                break;
            }
            $prefix[] = $top;
            $group = array_values(array_filter($group, fn ($s) => $s[$i] === $top));
        }

        return ['prefix' => $prefix, 'depth' => $depth];
    }

    /** @param array{prefix: array<int,string>, depth: int} $pattern */
    public static function isProduct(string $path, array $pattern): bool
    {
        $segs = self::segments($path);

        return count($segs) === $pattern['depth'] && array_slice($segs, 0, count($pattern['prefix'])) === $pattern['prefix'];
    }

    /** Раздел каталога: путь начинается так же, как у карточек (первый сегмент), и короче карточки. */
    public static function isSection(string $path, array $pattern, string $firstSeg): bool
    {
        $segs = self::segments($path);

        return $segs !== [] && count($segs) < $pattern['depth'] && ($firstSeg === '' || $segs[0] === $firstSeg);
    }

    /**
     * @return array{status:string, pattern?:array, fetched:int, products:int, added:int}
     */
    public function crawl(MpSite $site, int $maxPages = 2000, int $timeBudget = 3000): array
    {
        $known = DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)->where('status', 'active')
            ->pluck('url')->all();
        $pattern = self::productPattern($known);
        if ($pattern === null) {
            return ['status' => 'no_pattern', 'fetched' => 0, 'products' => 0, 'added' => 0];
        }
        $host = (string) parse_url((string) ($known[0] ?? ''), PHP_URL_HOST) ?: $site->domain;
        $origin = 'https://' . $host;
        $firstSeg = $pattern['prefix'][0] ?? (self::segments((string) parse_url((string) $known[0], PHP_URL_PATH))[0] ?? '');

        // Старт: главная и разделы (родители) известных карточек.
        $queue = [$origin . '/'];
        foreach ($known as $u) {
            $segs = self::segments((string) parse_url($u, PHP_URL_PATH));
            for ($d = max(1, count($pattern['prefix'])); $d < count($segs); $d++) {
                $queue[] = $origin . '/' . implode('/', array_slice($segs, 0, $d)) . '/';
            }
        }
        $queue = array_values(array_unique($queue));
        $seen = array_flip($queue);
        $products = [];
        $fetched = 0;
        $started = time();
        $channel = EgressPool::make()->pick($site->domain, $site->egress);

        while ($queue !== [] && $fetched < $maxPages && time() - $started < $timeBudget) {
            $url = array_shift($queue);
            $html = $this->get($site, $url, $channel);
            $fetched++;
            if ($html === null) {
                continue;
            }
            foreach (self::links($html, $url) as $link) {
                if (parse_url($link, PHP_URL_HOST) !== $host || preg_match(self::NAV_RE, $link) || DomainPolicy::isBlocked($link)) {
                    continue;
                }
                $path = (string) parse_url($link, PHP_URL_PATH);
                $query = (string) parse_url($link, PHP_URL_QUERY);
                if ($query === '' && self::isProduct($path, $pattern)) {
                    $products[UrlCanonicalizer::hash($link)] = UrlCanonicalizer::canonicalize($link);
                    continue;
                }
                // Разделы и их пагинация (только параметры страницы) — в очередь обхода.
                if (self::isSection($path, $pattern, $firstSeg) && self::onlyPagination($query) && !isset($seen[$link])) {
                    $seen[$link] = true;
                    $queue[] = $link;
                }
            }
        }

        $added = $this->store($site, $products);

        return ['status' => 'ok', 'pattern' => $pattern, 'fetched' => $fetched, 'products' => count($products), 'added' => $added];
    }

    /** @param array<string,string> $products url_hash => url */
    private function store(MpSite $site, array $products): int
    {
        $before = DB::table('mp_site_pages')->where('site_id', $site->id)->count();
        $now = now();
        foreach (array_chunk($products, 500, true) as $chunk) {
            $rows = [];
            foreach ($chunk as $hash => $url) {
                $rows[] = ['site_id' => $site->id, 'url' => mb_substr($url, 0, 1000), 'url_hash' => $hash, 'lastmod' => null, 'seen_at' => $now];
            }
            DB::table('mp_site_pages')->upsert($rows, ['url_hash'], ['site_id', 'seen_at']);
        }
        $after = DB::table('mp_site_pages')->where('site_id', $site->id)->count();
        $site->forceFill(['sitemap_pages' => $after, 'sitemap_indexed_at' => now()])->save();

        return $after - $before;
    }

    private function get(MpSite $site, string $url, ?array $channel): ?string
    {
        if ($site->fetch_mode === 'headless' && $this->headless !== null) {
            if (!$this->fetcher->robotsAllow($url, $channel['proxy'] ?? null)) {
                return null;
            }
            usleep(max(1500, (int) $site->rate_limit_ms) * 1000);

            return $this->headless->renderHtml($url, $channel['proxy'] ?? null);
        }
        for ($i = 0; $i < 20; $i++) {
            $res = $this->fetcher->fetch($url, ['egress' => $channel, 'rate_ms' => max(1000, (int) $site->rate_limit_ms), 'max_bytes' => 3_000_000]);
            if (!$res->deferred()) {
                return $res->ok() && $res->isHtml() ? $res->body : null;
            }
            usleep(max(300, $res->retryAfterMs) * 1000);
        }

        return null;
    }

    /** @return array<int,string> абсолютные ссылки страницы */
    public static function links(string $html, string $base): array
    {
        if (!preg_match_all('~<a\s[^>]*href\s*=\s*["\']([^"\'#]+)~i', $html, $m)) {
            return [];
        }
        $out = [];
        $b = parse_url($base);
        foreach ($m[1] as $href) {
            $href = html_entity_decode(trim($href));
            if ($href === '' || preg_match('~^(tel|mailto|javascript):~i', $href)) {
                continue;
            }
            if (str_starts_with($href, '//')) {
                $href = ($b['scheme'] ?? 'https') . ':' . $href;
            } elseif (str_starts_with($href, '/')) {
                $href = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . $href;
            } elseif (!preg_match('~^https?://~i', $href)) {
                $dir = rtrim(preg_replace('~/[^/]*$~', '/', (string) ($b['path'] ?? '/')) ?? '/', '/');
                $href = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . $dir . '/' . $href;
            }
            $out[$href] = true;
        }

        return array_keys($out);
    }

    private static function onlyPagination(string $query): bool
    {
        if ($query === '') {
            return true;
        }
        parse_str($query, $q);

        return $q !== [] && array_filter(array_keys($q), fn ($k) => !preg_match(self::PAGINATION_RE, (string) $k)) === [];
    }

    /** @return array<int,string> */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', trim(rawurldecode($path), '/')), fn ($s) => $s !== ''));
    }
}
