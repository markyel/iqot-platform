<?php

namespace App\Services\Generate;

use App\Services\Discovery\YandexSearchClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Предрассылочный таргетинг (#4, фаза 4a): по позициям батча ищем в Яндексе сайты,
 * где товар реально представлен, и делим выбранный пул поставщиков на две группы:
 *
 *   - Группа A — сайт поставщика нашёлся в выдаче по позициям батча. Им шлём письмо
 *     со ссылками-«намёками» (нашли похожее у вас на сайте, помогите проработать).
 *   - Группа B — не нашлись. Шлём как раньше.
 *
 * Параллельно собираем НОВЫЕ домены (не в пуле и не среди существующих поставщиков) —
 * кандидаты для discovery (SupplierDiscoveryService добавит их после анализа сайта).
 *
 * 1 Яндекс-запрос на позицию (много результатов). Бюджет ограничен дневным потолком.
 */
class SupplierTargetingService
{
    private const CONN = 'reports';

    /** Маркетплейсы/соцсети/агрегаторы/каталоги — не поставщики (порт n8n Extract Base URLs). */
    private const BLOCKED = [
        'wildberries.ru', 'ozon.ru', 'market.yandex.ru', 'avito.ru', 'sbermegamarket.ru',
        'kazanexpress.ru', 'aliexpress.', 'alibaba.', 'made-in-china.', 'amazon.', 'ebay.',
        'youtube.', 'wikipedia.', 'facebook.', 'instagram.', 'vk.com', 'tiktok.', 'twitter.',
        'linkedin.', 'ok.ru', 'dzen.ru', 'tiu.ru', 'pulscen.ru', '2gis.ru', 'yell.ru', 'zoon.ru',
        'cataloxy.ru', 'rusprofile.ru', 'list-org.com', 'forum.', 'otvet.mail.ru', 'pikabu.ru',
        // Переводчики-прокси (Яндекс/Google) и агрегаторы инструкций — не поставщики.
        'tr-page.', 'translate.', 'translated.turbopages', 'turbopages.org', 'manualslib.',
    ];

    private const ALLOWED_TLD = ['.ru', '.рф', '.su'];

    public function __construct(private readonly YandexSearchClient $search)
    {
    }

    public static function make(): self
    {
        $cfg = config('services.yandex_search');

        return new self(new YandexSearchClient(
            endpoint: (string) ($cfg['endpoint'] ?? ''),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            folderId: (string) ($cfg['folder_id'] ?? ''),
            resultsPerQuery: (int) config('services.email_pretarget.results_per_query', 20),
            timeout: (int) ($cfg['timeout'] ?? 30),
        ));
    }

    /**
     * @param array<int,array<string,mixed>> $items позиции батча (request_items)
     * @param array<int> $poolSupplierIds id поставщиков выбранного пула
     * @return array{
     *   groupA: array<int,array<int,array{url:string,item_id:int,item_name:string}>>,
     *   candidates: array<string,array{domain:string,url:string,item_id:int,product_type_id:?int,domain_id:?int}>,
     *   tier1: array<int>, tier2: array<int>,
     *   searched_items: int
     * }
     */
    public function target(array $items, array $poolSupplierIds): array
    {
        $empty = ['groupA' => [], 'candidates' => [], 'tier1' => [], 'tier2' => [], 'found' => [], 'searched_items' => 0];

        if (!(bool) config('services.email_pretarget.enabled', false) || !$this->search->isConfigured()) {
            return $empty;
        }

        // 1) Глобальный поиск позиций → домены (волна 1 + новые домены для discovery).
        $found = $this->searchItems($items, $searched);
        // 2) Матч с пулом: tier1 (кто ранжируется глобально по товару) + candidates.
        $match = $this->matchPool($found, $poolSupplierIds);
        $tier1 = $match['tier1'];

        // 3) Волна 2 «добор пула»: по пул-поставщикам НЕ из tier1 делаем site:-OR запрос
        //    (у кого из пула есть страница под контекст заявки — за пару запросов, не
        //    по одному на сайт). found_urls волны 2 цитируют ПОЗИЦИЮ заявки (item_name).
        $site = $this->matchPoolBySite($items, $poolSupplierIds, $tier1);

        // found_urls волны 2 добавляем в groupA (classifyByTier навесит их на tier2).
        $groupA = $match['groupA'];
        foreach ($site['groupA'] as $sid => $hits) {
            if (!isset($groupA[$sid])) {
                $groupA[$sid] = $hits;
            }
        }

        return [
            'groupA' => $groupA,
            'candidates' => $match['candidates'],
            'tier1' => $tier1,
            'tier2' => $site['tier2'],
            'found' => $found,
            'searched_items' => $searched,
        ];
    }

    /**
     * Волна 2 «добор пула»: по пул-поставщикам НЕ из tier1 (топ по confidence/rating, до
     * wave2_pool_cap) делаем Яндекс-запрос `<термин позиций> (site:d1 | … | site:dK)` —
     * за 1 запрос на чанк из K доменов находим, у кого из пула есть страница под контекст
     * заявки. Ссылку кладём в found_urls, но ЦИТИРУЕМ в письме позицию заявки (item_name).
     *
     * @param array<int,array<string,mixed>> $items
     * @param array<int> $poolSupplierIds
     * @param array<int> $excludeIds уже попавшие в волну 1 (tier1)
     * @return array{tier2:array<int>, groupA:array<int,array<int,array{url:string,item_id:int,item_name:string}>>}
     */
    private function matchPoolBySite(array $items, array $poolSupplierIds, array $excludeIds): array
    {
        $empty = ['tier2' => [], 'groupA' => []];
        if (!(bool) config('services.email_pool.waves_v2', false)) {
            return $empty;
        }
        $cap = max(0, (int) config('services.email_pool.wave2_pool_cap', 45));
        $chunkSize = max(1, (int) config('services.email_pool.wave2_site_chunk', 15));
        if ($cap <= 0) {
            return $empty;
        }

        $maxQueries = max(1, (int) config('services.email_pool.wave2_max_site_queries', 12));
        $maxItems = max(1, (int) config('services.email_pretarget.max_items_per_batch', 10));

        // Пул НЕ из волны 1, с доменом, топ по confidence/rating, до cap уникальных доменов.
        $exclude = array_flip(array_map('intval', $excludeIds));
        $ids = array_values(array_filter(
            array_map('intval', $poolSupplierIds),
            static fn ($id) => $id > 0 && !isset($exclude[$id])
        ));
        if ($ids === []) {
            return $empty;
        }
        $rows = DB::connection(self::CONN)->table('suppliers')
            ->whereIn('id', $ids)
            ->orderByDesc('profile_confidence')
            ->orderByDesc('rating')
            ->get(['id', 'website', 'email']);

        $domToId = [];
        $remaining = []; // host => true, ещё не совпавшие домены пула (по убыв. confidence)
        foreach ($rows as $r) {
            $host = $this->supplierHost($r);
            if ($host === '' || isset($domToId[$host])) {
                continue;
            }
            $domToId[$host] = (int) $r->id;
            $remaining[$host] = true;
            if (count($remaining) >= $cap) {
                break;
            }
        }
        if ($remaining === []) {
            return $empty;
        }

        // Per-позиция: термин КАЖДОЙ позиции гоним по ещё не совпавшим доменам. Матч →
        // цитируем ЭТУ позицию и убираем домен из дальнейших проходов. Потолок запросов.
        $tier2 = [];
        $groupA = [];
        $queries = 0;
        foreach (array_slice(array_values($items), 0, $maxItems) as $item) {
            if ($remaining === [] || $queries >= $maxQueries) {
                break;
            }
            $term = $this->siteSearchTerm($item);
            if ($term === '') {
                continue;
            }
            foreach (array_chunk(array_keys($remaining), $chunkSize) as $chunk) {
                if ($queries >= $maxQueries || !$this->reserveDailyBudget()) {
                    break;
                }
                $queries++;
                $siteOr = '(' . implode(' | ', array_map(static fn ($d) => 'site:' . $d, $chunk)) . ')';
                foreach ($this->search->multiSearch([$term . ' ' . $siteOr]) as $r) {
                    $host = $this->matchChunkHost(
                        $this->normHost($r['domain'] ?: (string) parse_url($r['url'], PHP_URL_HOST)),
                        $chunk
                    );
                    if ($host === null || !isset($remaining[$host])) {
                        continue;
                    }
                    $sid = $domToId[$host];
                    $groupA[$sid] = [[
                        'url' => (string) $r['url'],
                        'item_id' => (int) ($item['id'] ?? 0),
                        'item_name' => (string) ($item['name'] ?? ''),
                    ]];
                    $tier2[] = $sid;
                    unset($remaining[$host]); // совпал → в следующих позициях не проверяем
                }
            }
        }

        return ['tier2' => array_values(array_unique($tier2)), 'groupA' => $groupA];
    }

    /** Термин для site:-запроса из позиции: 2 значимых слова названия (без чисел/единиц). */
    private function siteSearchTerm(array $item): string
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return trim((string) ($item['brand'] ?? ''));
        }
        $words = preg_split('/[\s,;\/()]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sig = array_values(array_filter($words, static fn ($w) => mb_strlen($w) >= 4 && !preg_match('/^[\d.,]+$/u', $w)));
        $take = array_slice($sig !== [] ? $sig : $words, 0, 2);

        return trim(implode(' ', $take));
    }

    /**
     * Фри-почтовый домен (yandex.ru/mail.ru/gmail/...) — это ПРОВАЙДЕР почты, НЕ сайт
     * поставщика. Матчить/цитировать по нему нельзя: поставщик с email @yandex.ru иначе
     * ложно совпадает с ЛЮБЫМ yandex.ru-URL (напр. tr-page.yandex.ru — Яндекс-переводчик).
     * Точное сравнение домена (не подстрока) — чтобы «liftmail.ru» НЕ считался фри-почтой.
     */
    private function isFreeMailHost(string $h): bool
    {
        static $free = [
            'mail.ru', 'gmail.com', 'yandex.ru', 'ya.ru', 'yandex.by', 'yandex.kz',
            'bk.ru', 'list.ru', 'inbox.ru', 'internet.ru', 'rambler.ru', 'mail.com',
            'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com',
        ];
        return in_array($h, $free, true);
    }

    /** Домен поставщика для site:-запроса: website, иначе домен email (не фри-почта). */
    private function supplierHost(object $r): string
    {
        if (!empty($r->website)) {
            $h = $this->normHost((string) (parse_url((string) $r->website, PHP_URL_HOST) ?: $r->website));
            if ($h !== '') {
                return $h;
            }
        }
        if (!empty($r->email) && preg_match('/@([\w.-]+)$/', (string) $r->email, $m)) {
            $h = $this->normHost($m[1]);
            if ($h !== '' && !$this->isFreeMailHost($h)) {
                return $h;
            }
        }

        return '';
    }

    /** Хост результата → домен из чанка (точно или по базовому домену), иначе null. */
    private function matchChunkHost(string $host, array $chunk): ?string
    {
        if ($host === '') {
            return null;
        }
        if (in_array($host, $chunk, true)) {
            return $host;
        }
        $base = $this->baseDomain($host);
        if (in_array($base, $chunk, true)) {
            return $base;
        }

        return null;
    }

    /**
     * Поиск позиций в Яндексе → плоский список найденных допустимых доменов.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{host:string,url:string,item_id:int,item_name:string,product_type_id:?int,domain_id:?int}>
     */
    public function searchItems(array $items, ?int &$searched = 0): array
    {
        $searched = 0;
        $maxItems = max(1, (int) config('services.email_pretarget.max_items_per_batch', 10));
        $items = array_slice(array_values($items), 0, $maxItems);
        $found = [];

        foreach ($items as $item) {
            $query = $this->primaryQuery($item);
            if ($query === '' || !$this->reserveDailyBudget()) {
                continue;
            }
            $searched++;
            foreach ($this->search->multiSearch([$query]) as $r) {
                $host = $this->normHost($r['domain'] ?: (string) parse_url($r['url'], PHP_URL_HOST));
                if ($host === '' || !$this->allowedHost($host)) {
                    continue;
                }
                $found[] = [
                    'host' => $host,
                    'url' => $r['url'],
                    'item_id' => (int) ($item['id'] ?? 0),
                    'item_name' => (string) ($item['name'] ?? ''),
                    'kind' => 'primary',
                    'product_type_id' => isset($item['product_type_id']) ? (int) $item['product_type_id'] : null,
                    'domain_id' => isset($item['domain_id']) ? (int) $item['domain_id'] : null,
                ];
            }
        }

        return $found;
    }

    /**
     * Матч сохранённых найденных доменов с пулом → группа A + кандидаты (новые домены).
     * Переиспользуется на повторе (гейт качества) без нового Яндекс-поиска.
     *
     * @param array<int,array<string,mixed>> $found из searchItems()
     * @param array<int> $poolSupplierIds
     * @return array{groupA:array<int,array<int,mixed>>,candidates:array<string,array<string,mixed>>}
     */
    public function matchPool(array $found, array $poolSupplierIds): array
    {
        $poolHostToId = $this->poolHostMap($poolSupplierIds);
        $groupA = [];
        $primaryHit = [];      // sid => true, если совпал хотя бы по ОСНОВНОМУ запросу
        $candidateHosts = [];

        foreach ($found as $f) {
            $host = (string) ($f['host'] ?? '');
            if ($host === '') {
                continue;
            }
            $sid = $poolHostToId[$host] ?? ($poolHostToId[$this->baseDomain($host)] ?? null);
            if ($sid !== null) {
                $groupA[$sid][] = [
                    'url' => $f['url'],
                    'item_id' => (int) ($f['item_id'] ?? 0),
                    'item_name' => (string) ($f['item_name'] ?? ''),
                ];
                if (($f['kind'] ?? 'primary') === 'primary') {
                    $primaryHit[$sid] = true;
                }
            } elseif (!isset($candidateHosts[$host])) {
                $candidateHosts[$host] = [
                    'domain' => $host,
                    'url' => $f['url'],
                    'item_id' => (int) ($f['item_id'] ?? 0),
                    'product_type_id' => $f['product_type_id'] ?? null,
                    'domain_id' => $f['domain_id'] ?? null,
                ];
            }
        }

        $candidates = $this->dropExistingSupplierHosts($candidateHosts);
        foreach ($groupA as $sid => $hits) {
            $groupA[$sid] = $this->dedupByUrl($hits);
        }

        // Тиры «температуры»: tier1 — совпал по основному запросу (горячие); tier2 —
        // совпал ТОЛЬКО по облегчённым (тёплые). tier3 (холодные) — вызывающий считает
        // как пул минус tier1∪tier2. groupA (все совпавшие) — как раньше.
        $tier1 = array_map('intval', array_keys($primaryHit));
        $tier2 = array_values(array_diff(array_map('intval', array_keys($groupA)), $tier1));

        return ['groupA' => $groupA, 'candidates' => $candidates, 'tier1' => $tier1, 'tier2' => $tier2];
    }

    /** Основной запрос позиции (глобальный): бренд+артикул+название+коммерческий хвост.
     *  Ловит поставщиков, которые ХОРОШО ранжируются по точному товару → волна 1
     *  (tier1, горячие) + новые домены → discovery. Волну 2 «добор пула» даёт
     *  matchPoolBySite (site:-OR по доменам пула), а не облегчённые глобальные запросы. */
    private function primaryQuery(array $item): string
    {
        $parts = array_values(array_filter([
            trim((string) ($item['brand'] ?? '')),
            trim((string) ($item['article'] ?? '')),
            trim((string) ($item['name'] ?? '')),
        ], static fn ($v) => $v !== ''));

        return $parts === [] ? '' : implode(' ', $parts) . ' купить поставщик';
    }

    /** host => supplier_id по домену website И email пула (у многих website пуст,
     *  домен — в email). Поддомены website схлопываем к базовому. */
    private function poolHostMap(array $supplierIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $supplierIds), static fn ($v) => $v > 0));
        if ($ids === []) {
            return [];
        }

        $rows = DB::connection(self::CONN)->table('suppliers')
            ->whereIn('id', $ids)
            ->get(['id', 'website', 'email']);

        $map = [];
        foreach ($rows as $r) {
            // Домен website (host + базовый домен без поддомена).
            if ($r->website) {
                $host = $this->normHost((string) (parse_url((string) $r->website, PHP_URL_HOST) ?: $r->website));
                if ($host !== '') {
                    $map[$host] = (int) $r->id;
                    $base = $this->baseDomain($host);
                    if ($base !== '' && !isset($map[$base])) {
                        $map[$base] = (int) $r->id;
                    }
                }
            }
            // Домен email (для поставщиков без website). ФРИ-почту НЕ берём: это домен
            // почтового провайдера, а не сайт поставщика (иначе @yandex.ru ложно матчит
            // любой yandex.ru-URL, напр. tr-page.yandex.ru — переводчик Яндекса).
            if ($r->email && preg_match('/@([\w.-]+)$/', (string) $r->email, $m)) {
                $eh = $this->normHost($m[1]);
                if ($eh !== '' && !$this->isFreeMailHost($eh) && !isset($map[$eh])) {
                    $map[$eh] = (int) $r->id;
                }
            }
        }

        return $map;
    }

    /** Базовый домен (последние 2 метки): ropes.revator.ru → revator.ru. */
    private function baseDomain(string $host): string
    {
        $parts = explode('.', $host);
        return count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $host;
    }

    /**
     * Убрать из кандидатов домены, которые уже есть среди поставщиков (website/email host) —
     * это не «новые», их discovery не создаёт (в крайнем случае расширит scope сам).
     *
     * @param array<string,array<string,mixed>> $candidateHosts
     * @return array<string,array<string,mixed>>
     */
    private function dropExistingSupplierHosts(array $candidateHosts): array
    {
        if ($candidateHosts === []) {
            return [];
        }

        $hosts = array_keys($candidateHosts);
        $existing = DB::connection(self::CONN)->table('suppliers')
            ->where(function ($q) use ($hosts) {
                foreach ($hosts as $h) {
                    $q->orWhere('website', 'LIKE', '%' . $h . '%')
                        ->orWhere('email', 'LIKE', '%@' . $h);
                }
            })
            ->get(['website', 'email']);

        foreach ($existing as $row) {
            if ($row->website) {
                $h = $this->normHost((string) (parse_url((string) $row->website, PHP_URL_HOST) ?: $row->website));
                unset($candidateHosts[$h]);
            }
            if ($row->email && preg_match('/@([\w.-]+)$/', (string) $row->email, $m)) {
                unset($candidateHosts[$this->normHost($m[1])]);
            }
        }

        return $candidateHosts;
    }

    /** @param array<int,array{url:string,item_id:int,item_name:string}> $hits */
    private function dedupByUrl(array $hits): array
    {
        $seen = [];
        $out = [];
        foreach ($hits as $h) {
            if (isset($seen[$h['url']])) {
                continue;
            }
            $seen[$h['url']] = true;
            $out[] = $h;
        }

        return $out;
    }

    private function allowedHost(string $host): bool
    {
        foreach (self::BLOCKED as $b) {
            if (str_contains($host, $b)) {
                return false;
            }
        }
        foreach (self::ALLOWED_TLD as $tld) {
            if (str_ends_with($host, $tld)) {
                return true;
            }
        }

        return false;
    }

    private function normHost(?string $host): string
    {
        $host = mb_strtolower(trim((string) $host));

        return $host === '' ? '' : (preg_replace('/^www\./', '', $host) ?? $host);
    }

    /** Дневной бюджет Яндекс-запросов: атомарный счётчик в кэше. true — можно искать. */
    private function reserveDailyBudget(): bool
    {
        $cap = (int) config('services.email_pretarget.daily_query_cap', 0);
        if ($cap <= 0) {
            return true;
        }
        $key = 'pretarget:yandex:budget:' . now()->format('Ymd');
        $used = (int) Cache::get($key, 0);
        if ($used >= $cap) {
            return false;
        }
        Cache::put($key, $used + 1, now()->addDay());

        return true;
    }
}
