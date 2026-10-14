<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\CheckListingJob;
use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\Discovery\SearchGateway;
use App\Services\Monitoring\LlmCostCalculator;
use App\Services\Monitoring\MonitoringGovernor;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Замер глубины поиска (пилот 14.10.2026): почему Content Engine находит в 3 раза больше
 * пар «товар × продавец». CE делает ~12 запросов на товар (8 LLM-запросов за прогон) и
 * берёт до 25 страниц; Ф1 — 2 запроса и 8 кандидатов.
 *
 * Прогон (по умолчанию): N товаров с наибольшим числом ненайденных пар CE → LLM составляет
 * K запросов (все варианты артикула, бренд+название, «купить/цена», 1 англ.) → Яндекс →
 * до M кандидатов (≤2 на домен) → листинги found_via=deep_test → проверка обычным
 * CheckListingJob. Снимок «до» и для каждого домена — номер первого запроса, в выдаче
 * которого он появился, — в storage/app/monitoring/pilot/depth-test.json.
 *
 * --report: после проверок — прирост полноты и вклад запросов по глубине k=1..K
 * (сколько пар CE с ценой дали бы первые k запросов).
 */
class SearchDepthTest extends Command
{
    protected $signature = 'monitoring:search-depth-test {program} {ce_csv}
        {--products=20} {--queries=8} {--candidates=25}
        {--report : отчёт по уже выполненному прогону}';

    protected $description = 'Мониторинг цен: замер глубины поиска (запросы/кандидаты) против эталона CE';

    private const STATE = 'monitoring/pilot/depth-test.json';

    public function handle(): int
    {
        $path = storage_path('app/' . self::STATE);
        $ce = $this->loadCe((int) $this->argument('program'), (string) $this->argument('ce_csv'));

        if ($this->option('report')) {
            if (!is_file($path)) {
                $this->error('Нет прогона: ' . $path);

                return self::FAILURE;
            }

            return $this->report(json_decode((string) file_get_contents($path), true), $ce);
        }

        $nProducts = (int) $this->option('products');
        $nQueries = (int) $this->option('queries');
        $nCandidates = (int) $this->option('candidates');

        // Товары с наибольшим числом пар CE, не найденных IQOT с ценой.
        $priced = $this->pricedPairs(array_keys($ce));
        $missing = [];
        foreach ($ce as $pid => $domains) {
            $miss = count(array_diff_key($domains, $priced[$pid] ?? []));
            if ($miss > 0) {
                $missing[$pid] = $miss;
            }
        }
        arsort($missing);
        $productIds = array_slice(array_keys($missing), 0, $nProducts);

        $search = SearchGateway::make();
        $llm = OpenAIClassifierClient::fromConfig();
        $cost = new LlmCostCalculator();
        $governor = app(MonitoringGovernor::class);

        $state = ['started_at' => now()->toIso8601String(), 'queries' => $nQueries, 'candidates' => $nCandidates, 'products' => []];
        $totalQueries = 0;
        foreach ($productIds as $pid) {
            $product = MpProduct::query()->with('identifiers')->find($pid);
            $queries = $this->buildQueries($product, $nQueries, $llm, $cost, $governor);

            // Кандидаты как в CE: балл = встречаемость + позиция; домен → первый запрос.
            $urls = [];
            $firstQuery = [];
            foreach ($queries as $qi => $q) {
                $r = $search->search($q);
                if (!$r['quota']) {
                    $this->warn('Квота Яндекса исчерпана — прогон остановлен.');
                    break 2;
                }
                $totalQueries++;
                foreach ($r['results'] as $pos => $hit) {
                    $url = (string) ($hit['url'] ?? '');
                    if ($url === '' || DomainPolicy::isBlocked($url)) {
                        continue;
                    }
                    $domain = UrlCanonicalizer::registrableDomain($url);
                    $firstQuery[$domain] ??= $qi + 1;
                    $h = UrlCanonicalizer::hash($url);
                    if (isset($urls[$h])) {
                        $urls[$h]['score'] += 1;
                        continue;
                    }
                    $urls[$h] = ['url' => $url, 'domain' => $domain, 'score' => 1 + max(0, 10 - $pos) / 10, 'q' => $qi + 1];
                }
            }
            uasort($urls, fn ($a, $b) => $b['score'] <=> $a['score']);

            $known = MpListing::query()->where('product_id', $pid)->where('bench', false)->pluck('url_hash')->flip();
            $perDomain = [];
            $created = [];
            foreach ($urls as $h => $u) {
                if (count($created) >= $nCandidates) {
                    break;
                }
                if (isset($known[$h]) || ($perDomain[$u['domain']] ?? 0) >= 2) {
                    continue;
                }
                $site = MpSite::forUrl($u['url']);
                if (in_array($site->kind, ['marketplace', 'aggregator'], true)) {
                    continue;
                }
                $listing = MpListing::query()->firstOrCreate(
                    ['product_id' => $pid, 'url_hash' => $h],
                    ['site_id' => $site->id, 'url' => UrlCanonicalizer::canonicalize($u['url']), 'status' => MpListing::STATUS_ACTIVE,
                        'found_via' => 'deep_test', 'bench' => false, 'next_check_at' => now()]
                );
                if ($listing->wasRecentlyCreated) {
                    $created[] = $listing->id;
                    $perDomain[$u['domain']] = ($perDomain[$u['domain']] ?? 0) + 1;
                    CheckListingJob::dispatch($listing->id);
                }
            }

            $state['products'][$pid] = [
                'name' => $product->name,
                'queries' => $queries,
                'first_query_by_domain' => $firstQuery,
                'priced_before' => array_keys($priced[$pid] ?? []),
                'ce_domains' => array_keys($ce[$pid]),
                'created' => $created,
            ];
            $hitCe = count(array_intersect_key($ce[$pid], $firstQuery));
            $this->line(sprintf('#%d %s: запросов %d, ссылок %d, новых листингов %d; доменов CE в выдаче %d/%d',
                $pid, mb_substr((string) $product->name, 0, 40), count($queries), count($urls), count($created), $hitCe, count($ce[$pid])));
        }

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info("Запросов в Яндекс: {$totalQueries}. Листинги в очереди проверки. Отчёт после проверок: --report");

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function buildQueries(MpProduct $product, int $count, OpenAIClassifierClient $llm, LlmCostCalculator $cost, MonitoringGovernor $governor): array
    {
        $codes = [];
        foreach ($product->identifiers as $i) {
            if (in_array($i->kind, ['oem', 'mpn'], true)) {
                $codes[] = $i->value;
            }
        }
        $brand = trim((string) $product->brand);
        $system = 'Ты составляешь поисковые запросы для Яндекса, чтобы найти СТРАНИЦЫ ПРОДАВЦОВ этой запчасти с ценой '
            . '(интернет-магазины и поставщики лифтового/эскалаторного оборудования). Правила: артикулы/OEM — в кавычках; '
            . 'используй ВСЕ варианты артикула (с точками/без, короткие/длинные формы); сочетай бренд + артикул, бренд + название, '
            . 'название + ключевой параметр; добавляй «купить», «цена», «в наличии»; 1 запрос на английском, если бренд иностранный. '
            . 'Не ищи документацию и форумы. Отвечай строго JSON.';
        $user = "Товар: {$product->name}\nБренд: {$brand}\nOEM/артикулы: " . ($codes ? implode(', ', $codes) : '(нет)')
            . "\n\nJSON: {\"queries\": [\"…\"]} — ровно {$count} запросов, самые точные первыми.";
        $queries = [];
        try {
            $json = $llm->jsonCompletion((string) config('monitoring.llm.model', 'gpt-4o-mini'), $system, $user, 600, 0.0);
            $queries = array_values(array_filter(array_map(fn ($q) => is_string($q) ? trim($q) : '', (array) ($json['queries'] ?? []))));
            $u = $llm->lastUsage();
            $governor->record('llm_call', 1, $u ? $cost->usd($u['model'], $u['prompt_tokens'], $u['completion_tokens']) : 0.0);
        } catch (\Throwable) {
            // фолбэк ниже
        }
        if ($queries === []) {
            $a = $codes[0] ?? null;
            $queries = array_filter([
                $a ? "\"{$a}\" {$brand} цена" : null,
                $a ? "\"{$a}\" купить" : null,
                trim("{$brand} {$product->name}"),
                trim("{$product->name} купить"),
            ]);
        }

        return array_slice(array_values(array_unique($queries)), 0, $count);
    }

    private function report(array $state, array $ce): int
    {
        $pids = array_map('intval', array_keys($state['products']));
        // «После» = было до ∪ пары с ценой ИМЕННО с листингов замера (параллельные плановые
        // проверки этих товаров эффект замера не размывают).
        $priced = $this->pricedPairs($pids, 'deep_test');
        $k = (int) $state['queries'];

        $pending = MpListing::query()->where('found_via', 'deep_test')->whereNull('checked_at')->count();
        if ($pending > 0) {
            $this->warn("Не проверено ещё {$pending} листингов deep_test — отчёт предварительный.");
        }

        $tot = ['ce' => 0, 'before' => 0, 'after' => 0];
        $byK = array_fill(1, $k, 0);
        $deepStatus = [];
        foreach ($state['products'] as $pid => $p) {
            $ceDomains = array_flip($p['ce_domains']);
            $before = array_intersect_key(array_flip($p['priced_before']), $ceDomains);
            $after = $before + array_intersect_key($priced[(int) $pid] ?? [], $ceDomains);
            $tot['ce'] += count($ceDomains);
            $tot['before'] += count($before);
            $tot['after'] += count($after);
            // Новые пары с ценой — по первому запросу, в выдаче которого был домен.
            foreach (array_keys(array_diff_key($after, $before)) as $domain) {
                $q = $p['first_query_by_domain'][$domain] ?? null;
                if ($q !== null) {
                    for ($i = $q; $i <= $k; $i++) {
                        $byK[$i]++;
                    }
                }
            }
        }
        foreach (MpListing::query()->where('found_via', 'deep_test')->get(['status', 'price']) as $l) {
            $st = $l->status === 'active' ? ($l->price !== null ? 'active_priced' : 'active_no_price') : $l->status;
            $deepStatus[$st] = ($deepStatus[$st] ?? 0) + 1;
        }

        $pct = fn ($a, $b) => $b ? sprintf('%d/%d (%.1f%%)', $a, $b, 100 * $a / $b) : '—';
        $this->table(['метрика', 'значение'], [
            ['товаров в замере', count($pids)],
            ['пары CE этих товаров', $tot['ce']],
            ['ПОЛНОТА до (Ф1: 2 запроса, 8 кандидатов)', $pct($tot['before'], $tot['ce'])],
            ["ПОЛНОТА после ({$k} запросов, {$state['candidates']} кандидатов)", $pct($tot['after'], $tot['ce'])],
            ['листинги deep_test по статусу', json_encode($deepStatus)],
        ]);
        $rows = [];
        foreach ($byK as $i => $n) {
            $rows[] = [$i, $n, $pct($tot['before'] + $n, $tot['ce'])];
        }
        $this->table(['первых k запросов', 'новых пар CE с ценой', 'полнота'], $rows);

        return self::SUCCESS;
    }

    /**
     * Пары CE: product_id => [domain => true] (цена > 0).
     *
     * @return array<int, array<string,bool>>
     */
    private function loadCe(int $programId, string $csv): array
    {
        $productByRef = DB::table('program_items')->where('program_id', $programId)->where('active', true)
            ->whereNotNull('product_id')->pluck('product_id', 'client_ref')->all();
        $ce = [];
        $fh = fopen($csv, 'r');
        $h = fgetcsv($fh, 0, ',', '"', '\\');
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($r) !== count($h)) {
                continue;
            }
            $r = array_combine($h, $r);
            $pid = $productByRef[$r['lw_code']] ?? null;
            if ($pid && (float) $r['price'] > 0) {
                $ce[(int) $pid][UrlCanonicalizer::registrableDomain($r['url'])] = true;
            }
        }
        fclose($fh);

        return $ce;
    }

    /** @return array<int, array<string,bool>> product_id => [domain => true] с ценой на активном листинге */
    private function pricedPairs(array $productIds, ?string $foundVia = null): array
    {
        $out = [];
        foreach (array_chunk($productIds, 1000) as $chunk) {
            foreach (DB::table('mp_listings as l')->join('mp_sites as s', 's.id', '=', 'l.site_id')
                ->whereIn('l.product_id', $chunk)->where('l.bench', false)->where('l.status', 'active')->whereNotNull('l.price')
                ->when($foundVia !== null, fn ($q) => $q->where('l.found_via', $foundVia))
                ->get(['l.product_id', 's.domain']) as $r) {
                $out[(int) $r->product_id][$r->domain] = true;
            }
        }

        return $out;
    }
}
