<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\MonitoringSettings;
use App\Support\Monitoring\UrlCanonicalizer;

/**
 * Поиск листингов товара: N LLM-запросов в Яндекс (SearchQueryBuilder) → кандидаты,
 * ранжированные как в Content Engine (встречаемость в выдачах + позиция) → до M листингов
 * status=active без цены, ≤2 на домен → их проверяет ListingChecker (он и отсекает
 * «не тот товар»: аналоги, другой бренд/типоразмер, категории).
 *
 * Глубина (N запросов, M кандидатов) — агрессивность из MonitoringSettings. Замер
 * 14.10.2026 на 20 позициях: 2 простых запроса/8 кандидатов — полнота против CE 11,5%,
 * 4 LLM-запроса/25 кандидатов — 36%, 8 — 40%.
 * Только витрины (маркетплейсы/агрегаторы/соцсети — стоп-лист), известные отрицательные
 * URL товара (mismatch/gone) повторно не заводим.
 */
class ListingDiscoverer
{
    public function __construct(
        private readonly SearchGateway $search,
        private readonly SearchQueryBuilder $queries,
    ) {
    }

    public static function make(): self
    {
        return new self(SearchGateway::make(), SearchQueryBuilder::make());
    }

    /**
     * @return array{status: string, queries: int, results: int, created: array<int,int>, errors?: int}
     *   status: ok | quota | not_configured | error (Яндекс не ответил ни на один запрос)
     */
    public function discover(MpProduct $product): array
    {
        if (!$this->search->isConfigured()) {
            return ['status' => 'not_configured', 'queries' => 0, 'results' => 0, 'created' => []];
        }
        $product->loadMissing('identifiers');

        $urls = [];
        $queries = 0;
        $errors = 0;
        $results = 0;
        $used = array_values((array) ($product->last_queries ?? []));
        $list = $this->queries($product);
        if ($list === []) {
            // «Дожим»: новых формулировок не осталось — заход пустой, итог подведёт refresh-coverage.
            $product->forceFill(['search_state' => 'searching', 'last_search_at' => now(),
                'search_attempts' => (int) $product->search_attempts + 1])->save();

            return ['status' => 'ok', 'queries' => 0, 'results' => 0, 'created' => [], 'errors' => 0];
        }
        foreach ($list as $query) {
            $r = $this->search->search($query);
            if (!$r['quota']) {
                if ($queries === 0 && $errors === 0) {
                    return ['status' => 'quota', 'queries' => 0, 'results' => 0, 'created' => []];
                }
                break;
            }
            if (($r['error'] ?? null) !== null) {
                $errors++;
                continue;
            }
            $queries++;
            $used[] = $query;
            foreach (array_values($r['results']) as $pos => $hit) {
                $url = (string) ($hit['url'] ?? '');
                if ($url === '' || DomainPolicy::isBlocked($url)) {
                    continue;
                }
                $results++;
                $h = UrlCanonicalizer::hash($url);
                if (isset($urls[$h])) {
                    $urls[$h]['score'] += 1;
                    continue;
                }
                $urls[$h] = ['url' => $url, 'score' => 1 + max(0, 10 - $pos) / 10];
            }
        }
        if ($queries === 0) {
            // Ни одного ответа (429/5xx) — не «ничего не найдено»: поиск повторится.
            $product->forceFill(['search_state' => 'new', 'last_search_at' => now()])->save();

            return ['status' => 'error', 'queries' => 0, 'results' => 0, 'created' => [], 'errors' => $errors];
        }
        uasort($urls, fn ($a, $b) => $b['score'] <=> $a['score']);

        $created = $this->createListings($product, array_map(fn ($u) => $u['url'], $urls));

        $product->forceFill([
            'search_state' => 'searching',
            'last_search_at' => now(),
            'search_attempts' => (int) $product->search_attempts + 1,
            'last_queries' => array_slice(array_values(array_unique($used)), -40),
        ])->save();

        return ['status' => 'ok', 'queries' => $queries, 'results' => $results, 'created' => $created, 'errors' => $errors];
    }

    /** @return array<int,string> */
    public function queries(MpProduct $product): array
    {
        [$count] = self::depth($product);
        $exclude = (int) $product->search_level > 0 ? array_values((array) ($product->last_queries ?? [])) : [];

        return $this->queries->build($product, $count, $exclude);
    }

    /**
     * Глубина захода: ступень 0 — пресет агрессивности; ступени «дожима» — monitoring.search.escalation
     * (не меньше пресета).
     *
     * @return array{0: int, 1: int} [запросов, кандидатов]
     */
    public static function depth(MpProduct $product): array
    {
        return self::depthFor((int) $product->search_level, MonitoringSettings::get('search_queries'),
            MonitoringSettings::get('search_candidates'), (array) config('monitoring.search.escalation', []));
    }

    /**
     * @param array<int, array{0:int,1:int}> $ladder
     * @return array{0: int, 1: int}
     */
    public static function depthFor(int $level, int $presetQueries, int $presetCandidates, array $ladder): array
    {
        if ($level <= 0 || $ladder === []) {
            return [$presetQueries, $presetCandidates];
        }
        $step = $ladder[min($level, count($ladder)) - 1];

        return [max($presetQueries, (int) $step[0]), max($presetCandidates, (int) $step[1])];
    }

    /**
     * @param array<string,string> $candidates url_hash => url, по убыванию балла
     * @return array<int,int> id созданных листингов
     */
    private function createListings(MpProduct $product, array $candidates): array
    {
        [, $maxCandidates] = self::depth($product);
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
