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

        // 1) Поиск: собираем сырые найденные домены (для матчинга и переиспользования).
        $found = $this->searchItems($items, $searched);
        // 2) Матч с пулом.
        $match = $this->matchPool($found, $poolSupplierIds);

        return $match + ['found' => $found, 'searched_items' => $searched];
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
        $light = (bool) config('services.email_pool.waves_v2', false);
        $items = array_slice(array_values($items), 0, $maxItems);
        $found = [];

        foreach ($items as $item) {
            // Резервируем дневной бюджет ПОКАЖДО и берём только оплаченные запросы.
            $toRun = [];
            $kindByQuery = [];
            foreach ($this->buildQueries($item, $light) as $qk) {
                if (!$this->reserveDailyBudget()) {
                    break;
                }
                $toRun[] = $qk['q'];
                $kindByQuery[$qk['q']] = $qk['kind'];
            }
            if ($toRun === []) {
                continue;
            }
            $searched++; // счётчик — по ПОЗИЦИЯМ (для гейта «таргетинг отработал?»), не по запросам
            foreach ($this->search->multiSearch($toRun) as $r) {
                $host = $this->normHost($r['domain'] ?: (string) parse_url($r['url'], PHP_URL_HOST));
                if ($host === '' || !$this->allowedHost($host)) {
                    continue;
                }
                $found[] = [
                    'host' => $host,
                    'url' => $r['url'],
                    'item_id' => (int) ($item['id'] ?? 0),
                    'item_name' => (string) ($item['name'] ?? ''),
                    'kind' => $kindByQuery[$r['query'] ?? ''] ?? 'primary',
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

    /**
     * Поисковые запросы из позиции с пометкой «сила» матча:
     *   - primary: бренд+артикул+название (узкий, точный) — даёт tier1 (горячие).
     *   - light (за флагом waves_v2): только артикул / только название (широкий) —
     *     совпавшие ТОЛЬКО по ним = tier2 (тёплые). primary идёт ПЕРВЫМ: при дедупе URL
     *     в multiSearch совпавший и там и там URL сохранит kind=primary (→ tier1).
     *
     * @return array<int,array{q:string,kind:string}>
     */
    private function buildQueries(array $item, bool $light): array
    {
        $brand = trim((string) ($item['brand'] ?? ''));
        $article = trim((string) ($item['article'] ?? ''));
        $name = trim((string) ($item['name'] ?? ''));

        $primaryParts = array_values(array_filter([$brand, $article, $name], static fn ($v) => $v !== ''));
        $out = [];
        if ($primaryParts !== []) {
            $out[] = ['q' => implode(' ', $primaryParts) . ' купить поставщик', 'kind' => 'primary'];
        }
        if ($light) {
            if ($article !== '') {
                $out[] = ['q' => $article . ' купить', 'kind' => 'light'];
            }
            if ($name !== '') {
                $out[] = ['q' => $name . ' купить', 'kind' => 'light'];
            }
        }

        // Дедуп одинаковых строк запроса (первое вхождение — с его kind).
        $seen = [];
        $dedup = [];
        foreach ($out as $o) {
            if (isset($seen[$o['q']])) {
                continue;
            }
            $seen[$o['q']] = true;
            $dedup[] = $o;
        }

        return $dedup;
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
            // Домен email (для поставщиков без website — их большинство).
            if ($r->email && preg_match('/@([\w.-]+)$/', (string) $r->email, $m)) {
                $eh = $this->normHost($m[1]);
                if ($eh !== '' && !isset($map[$eh])) {
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
