<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;

/**
 * Поиск листингов товара (Ф1, «вслепую»): 1–2 запроса в Яндекс → кандидаты-страницы →
 * листинги status=active без цены → их проверяет ListingChecker (он и отсекает
 * «не тот товар»: аналоги, другой бренд/типоразмер, категории).
 *
 * Отличия от исследования Content Engine: 1–2 запроса вместо 8, только витрины
 * (маркетплейсы/агрегаторы/соцсети — стоп-лист), ≤2 страниц на домен, известные
 * отрицательные URL товара (mismatch/gone) повторно не заводим.
 */
class ListingDiscoverer
{
    public function __construct(private readonly SearchGateway $search)
    {
    }

    public static function make(): self
    {
        return new self(SearchGateway::make());
    }

    /**
     * @return array{status: string, queries: int, results: int, created: array<int,int>}
     *   status: ok | quota | not_configured
     */
    public function discover(MpProduct $product): array
    {
        if (!$this->search->isConfigured()) {
            return ['status' => 'not_configured', 'queries' => 0, 'results' => 0, 'created' => []];
        }
        $product->loadMissing('identifiers');

        $candidates = [];
        $queries = 0;
        $results = 0;
        foreach ($this->queries($product) as $query) {
            $r = $this->search->search($query);
            if (!$r['quota']) {
                if ($queries === 0) {
                    return ['status' => 'quota', 'queries' => 0, 'results' => 0, 'created' => []];
                }
                break;
            }
            $queries++;
            foreach ($r['results'] as $hit) {
                $results++;
                $url = (string) ($hit['url'] ?? '');
                if ($url === '' || DomainPolicy::isBlocked($url)) {
                    continue;
                }
                $candidates[UrlCanonicalizer::hash($url)] ??= $url;
            }
        }

        $created = $this->createListings($product, $candidates);

        $product->forceFill([
            'search_state' => 'searching',
            'last_search_at' => now(),
            'search_attempts' => (int) $product->search_attempts + 1,
        ])->save();

        return ['status' => 'ok', 'queries' => $queries, 'results' => $results, 'created' => $created];
    }

    /** @return array<int,string> */
    public function queries(MpProduct $product): array
    {
        $codes = $product->articleValues();
        $brand = trim((string) $product->brand);
        $max = max(1, (int) config('monitoring.search.queries_per_product', 2));
        $q = [];

        // Код каталога клиента (LW-…) в поиск не идёт — по нему ищем только свои партнёрские сайты.
        $article = null;
        foreach ($product->identifiers as $i) {
            if (in_array($i->kind, ['oem', 'mpn'], true)) {
                $article = $i->value;
                break;
            }
        }

        if ($article) {
            $q[] = trim('"' . $article . '" ' . $brand . ' цена');
            $q[] = trim('"' . $article . '" купить');
        } else {
            $name = mb_substr(trim((string) $product->name), 0, 120);
            $q[] = trim($name . ' ' . $brand . ' цена');
            $q[] = trim($name . ' купить');
        }

        return array_slice(array_values(array_unique($q)), 0, $max);
    }

    /**
     * @param array<string,string> $candidates url_hash => url
     * @return array<int,int> id созданных листингов
     */
    private function createListings(MpProduct $product, array $candidates): array
    {
        $maxCandidates = (int) config('monitoring.search.max_candidates', 8);
        $maxPerDomain = (int) config('monitoring.search.max_per_domain', 2);

        $known = MpListing::query()->where('product_id', $product->id)->where('bench', false)->pluck('url_hash')->flip();
        $perDomain = [];
        $created = [];
        foreach ($candidates as $hash => $url) {
            if (count($created) >= $maxCandidates) {
                break;
            }
            if (isset($known[$hash])) {
                continue; // уже есть (в т.ч. отрицательный mismatch/gone) — не дублируем
            }
            $domain = UrlCanonicalizer::registrableDomain($url);
            if (($perDomain[$domain] ?? 0) >= $maxPerDomain) {
                continue;
            }
            $site = MpSite::forUrl($url);
            if ($site->kind === 'marketplace' || $site->kind === 'aggregator') {
                continue;
            }
            $listing = MpListing::query()->firstOrCreate(
                ['product_id' => $product->id, 'url_hash' => $hash],
                [
                    'site_id' => $site->id,
                    'url' => UrlCanonicalizer::canonicalize($url),
                    'status' => MpListing::STATUS_ACTIVE,
                    'found_via' => 'yandex',
                    'bench' => false,
                ]
            );
            if ($listing->wasRecentlyCreated) {
                $created[] = $listing->id;
                $perDomain[$domain] = ($perDomain[$domain] ?? 0) + 1;
            }
        }

        return $created;
    }
}
