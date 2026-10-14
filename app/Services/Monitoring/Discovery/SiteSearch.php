<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Web\EgressPool;
use App\Services\Monitoring\Web\FetchResult;
use App\Services\Monitoring\Web\PageFetcher;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: поиск по сайту продавца — для сайтов, где sitemap не помогает (внутренние коды
 * в адресах: revator /…/RR3096/, nlp-group; неполный sitemap: liftcomplete, delolift).
 *
 * profile(): шаблон поиска — из формы на главной (role=search / type=search / q, query, s…)
 * и типовых (Bitrix, WordPress/WooCommerce, OpenCart); принимается, только если по артикулу
 * находит страницы, уже известные нам с ценой на этом сайте (проба 14.10: 6 из 6 доменов).
 * candidates(): из выдачи — карточка (сайт сам перешёл на неё) или до 2 ссылок с артикулом
 * товара / совпадением названия в тексте ссылки. Дальше — обычная проверка ListingChecker.
 */
class SiteSearch
{
    public const TEMPLATES = [
        '/search/?q={q}', '/search?q={q}', '/catalog/?q={q}', '/search/index.php?q={q}', '/?s={q}&post_type=product',
        '/index.php?route=product/search&search={q}', '/search/?search={q}', '/catalog/search/?q={q}', '/search/?query={q}',
        '/poisk/?q={q}', '/search?search={q}', '/search?query={q}',
    ];

    private const NAV_RE = '~/(search|poisk|cart|basket|korzina|compare|wishlist|login|auth|personal|register|account|checkout|order)(/|\?|$)|[?&](page|PAGEN_\d+|sort|order|limit)=|^(tel|mailto|javascript):~i';

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
     * Подобрать и сохранить шаблон поиска сайта.
     *
     * @return array{template: ?string, tried: int, note: string, disallowed?: array<int,string>}
     */
    public function profile(MpSite $site): array
    {
        $channel = $this->pool->pick($site->domain, $site->egress);
        if ($channel === null) {
            return ['template' => null, 'tried' => 0, 'note' => 'все каналы забанены'];
        }
        $known = DB::select(
            "SELECT l.url, i.value code FROM mp_listings l
             JOIN mp_identifiers i ON i.product_id = l.product_id AND i.kind IN ('oem', 'mpn')
             WHERE l.site_id = ? AND l.status = 'active' AND l.price IS NOT NULL AND l.bench = 0
             ORDER BY l.observed_at DESC LIMIT 2",
            [$site->id]
        );
        if ($known === []) {
            return ['template' => null, 'tried' => 0, 'note' => 'нет известных страниц с ценой для проверки шаблона'];
        }

        $home = $this->get('https://' . $site->domain . '/', $site, $channel);
        $templates = array_values(array_unique(array_merge($home ? $this->formTemplates($home->body) : [], self::TEMPLATES)));
        $tried = 0;
        $disallowed = [];
        foreach ($templates as $tpl) {
            // robots.txt соблюдаем (решение владельца): /search/ у Bitrix часто закрыт.
            if (!$this->fetcher->robotsAllow($this->url($site, $tpl, 'test'), $channel['proxy'])) {
                $disallowed[] = $tpl;
                continue;
            }
            $hits = 0;
            foreach ($known as $k) {
                $tried++;
                $res = $this->get($this->url($site, $tpl, $k->code), $site, $channel);
                $path = (string) parse_url($k->url, PHP_URL_PATH);
                if ($res && $res->ok() && ($path !== '' && (str_contains($res->body, $path) || UrlCanonicalizer::hash($res->finalUrl) === UrlCanonicalizer::hash($k->url)))) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $site->forceFill(['search_url_template' => $tpl])->save();

                return ['template' => $tpl, 'tried' => $tried, 'note' => "{$hits}/" . count($known)];
            }
        }

        $note = 'ни один шаблон не нашёл известные страницы';
        if ($disallowed !== []) {
            $note .= '; запрещено robots.txt: ' . implode(', ', array_slice($disallowed, 0, 4)) . (count($disallowed) > 4 ? '…' : '');
        }

        return ['template' => null, 'tried' => $tried, 'note' => $note, 'disallowed' => $disallowed];
    }

    /** Адрес поиска товара на сайте (по OEM/MPN, иначе по названию). */
    public function searchUrl(MpSite $site, MpProduct $product): ?string
    {
        if (!$site->search_url_template) {
            return null;
        }
        $q = null;
        foreach ($product->identifiers as $i) {
            if (in_array($i->kind, ['oem', 'mpn'], true)) {
                $q = $i->value;
                break;
            }
        }
        $q ??= mb_substr(trim((string) $product->name), 0, 80);

        return $this->url($site, $site->search_url_template, $q);
    }

    /**
     * Кандидаты из выдачи поиска: url => причина.
     *
     * @return array<string, string>
     */
    public function candidates(FetchResult $res, MpSite $site, MpProduct $product, int $max = 2): array
    {
        if (!$res->ok()) {
            return [];
        }
        $p = SitemapMatcher::productTokens($product);
        // Сайт сам открыл карточку (единственный результат) — берём её.
        $searchPath = (string) parse_url((string) $site->search_url_template, PHP_URL_PATH);
        if ($res->finalUrl !== $res->url && !str_contains((string) parse_url($res->finalUrl, PHP_URL_PATH), rtrim($searchPath, '/') ?: '/search')
            && !UrlCanonicalizer::looksLikeNonProduct($res->finalUrl)) {
            return [$res->finalUrl => 'redirect'];
        }

        $scored = [];
        foreach ($this->links($res->body, $res->finalUrl) as $href => $text) {
            if (UrlCanonicalizer::registrableDomain($href) !== $site->domain || preg_match(self::NAV_RE, $href)
                || UrlCanonicalizer::looksLikeNonProduct($href)) {
                continue;
            }
            $t = SitemapMatcher::urlTokens($href);
            $a = SitemapMatcher::textTokens($text);
            if (array_intersect_key($p['codes'], $t['codes'] + $a['codes']) !== []) {
                $scored[$href] = 2.0;
                continue;
            }
            $words = array_filter(array_keys($p['words']), fn ($w) => $w[0] !== '#');
            if (count($words) === 0) {
                continue;
            }
            $hit = count(array_filter($words, fn ($w) => isset($a['words'][$w]) || isset($t['words'][$w])));
            $share = $hit / count($words);
            if ($hit >= min(3, count($words)) && $share >= 0.6) {
                $scored[$href] = max($scored[$href] ?? 0, $share);
            }
        }
        arsort($scored);
        $out = [];
        foreach (array_slice($scored, 0, $max, true) as $href => $score) {
            $out[$href] = $score >= 2.0 ? 'code' : 'name';
        }

        return $out;
    }

    /** @return array<string,string> абсолютный href => текст ссылки (последний непустой) */
    private function links(string $html, string $base): array
    {
        $out = [];
        if (!preg_match_all('~<a\s[^>]*href\s*=\s*["\']([^"\'#]+)["\'][^>]*>(.*?)</a>~is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($m as [$_, $href, $inner]) {
            $abs = $this->absolute(html_entity_decode($href, ENT_QUOTES), $base);
            if ($abs === null) {
                continue;
            }
            $text = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($inner), ENT_QUOTES)) ?? '');
            if ($text !== '' || !isset($out[$abs])) {
                $out[$abs] = $text !== '' ? $text : ($out[$abs] ?? '');
            }
        }

        return $out;
    }

    private function absolute(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('~^(tel|mailto|javascript):~i', $href)) {
            return null;
        }
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $href;
        }

        return $origin . (str_starts_with($href, '/') ? $href : '/' . $href);
    }

    /** @return array<int,string> шаблоны из форм поиска главной */
    private function formTemplates(string $html): array
    {
        $out = [];
        if (!preg_match_all('~<form\b([^>]*)>(.*?)</form>~is', $html, $forms, PREG_SET_ORDER)) {
            return [];
        }
        foreach ($forms as [$_, $attrs, $inner]) {
            if (preg_match('~method\s*=\s*["\']?post~i', $attrs)) {
                continue;
            }
            $action = preg_match('~action\s*=\s*["\']([^"\']*)["\']~i', $attrs, $am) ? $am[1] : '/';
            $isSearch = preg_match('~role\s*=\s*["\']search|search|poisk~i', $attrs . ' ' . $action);
            if (!preg_match_all('~<input\b[^>]*>~i', $inner, $inputs)) {
                continue;
            }
            foreach ($inputs[0] as $input) {
                if (!preg_match('~name\s*=\s*["\']([^"\']+)["\']~i', $input, $nm)) {
                    continue;
                }
                $name = $nm[1];
                $typeSearch = preg_match('~type\s*=\s*["\']search~i', $input);
                if ($typeSearch || ($isSearch && in_array(mb_strtolower($name), ['q', 'query', 's', 'search', 'text', 'keyword', 'keywords', 'k', 'searchword'], true))) {
                    $path = (string) (parse_url($action, PHP_URL_PATH) ?: '/');
                    $out[] = $path . (str_contains($action, '?') ? '?' . parse_url($action, PHP_URL_QUERY) . '&' : '?') . rawurlencode($name) . '={q}';
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function url(MpSite $site, string $tpl, string $q): string
    {
        return 'https://' . $site->domain . str_replace('{q}', rawurlencode($q), $tpl);
    }

    /** Загрузка с ожиданием слота домена (для профилирования — синхронно). */
    private function get(string $url, MpSite $site, array $channel): ?FetchResult
    {
        for ($i = 0; $i < 20; $i++) {
            $res = $this->fetcher->fetch($url, ['egress' => $channel, 'rate_ms' => (int) $site->rate_limit_ms]);
            if (!$res->deferred()) {
                return $res;
            }
            usleep(max(200, $res->retryAfterMs) * 1000);
        }

        return null;
    }
}
