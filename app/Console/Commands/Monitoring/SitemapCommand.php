<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MpListing;
use App\Models\Monitoring\MpProduct;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Discovery\SitemapIndexer;
use App\Services\Monitoring\Discovery\SitemapMatcher;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: поиск по sitemap известных продавцов (без Яндекса).
 *
 *   monitoring:sitemap index --ce=<csv> --top=15        индекс sitemap топ-доменов эталона CE
 *   monitoring:sitemap index --domain=zaplift.ru         один домен
 *   monitoring:sitemap match --program=1 --ce=<csv>      кандидаты + оценка против CE (без записи)
 *   monitoring:sitemap match --program=1 --create        завести листинги found_via=sitemap и проверить
 */
class SitemapCommand extends Command
{
    protected $signature = 'monitoring:sitemap {action : index|match}
        {--domain=* : домены (иначе — топ эталона CE)}
        {--ce= : CSV эталона Content Engine (выбор доменов и оценка)}
        {--top=15 : сколько доменов эталона}
        {--skip=chipdip.ru : домены через запятую — не трогать}
        {--program=1}
        {--all : index — все сайты, где у нас есть цены; match — все проиндексированные сайты × все активные программы}
        {--create : завести листинги и поставить в проверку}';

    protected $description = 'Мониторинг цен: индекс sitemap сайтов продавцов и сопоставление с товарами (Ф2)';

    public function handle(): int
    {
        if ($this->option('all') && !config('monitoring.enabled')) {
            $this->warn('monitoring:sitemap --all выключен (MONITORING_ENABLED=false).');

            return self::SUCCESS;
        }
        $domains = $this->domains();
        if ($domains === []) {
            $this->error('Нет доменов: --domain=… или --ce=<csv>');

            return self::FAILURE;
        }

        return $this->argument('action') === 'index' ? $this->index($domains) : $this->matchAll($domains);
    }

    private function index(array $domains): int
    {
        $indexer = SitemapIndexer::make();
        foreach ($domains as $d) {
            $site = MpSite::forUrl('https://' . $d);
            if ($site->isBlocked()) {
                $this->line("{$d}: сайт заблокирован до {$site->blocked_until} — пропуск");
                continue;
            }
            $t = microtime(true);
            $r = $indexer->index($site);
            $this->line(sprintf('%s: %s, файлов %d, страниц %d, %.0f c', $d, $r['status'], $r['files'], $r['pages'], microtime(true) - $t));
        }

        return self::SUCCESS;
    }

    private function matchAll(array $domains): int
    {
        $programId = (int) $this->option('program');
        $programIds = $this->option('all')
            ? DB::table('monitoring_programs')->where('status', 'active')->pluck('id')->all()
            : [$programId];
        $ids = DB::table('program_items')->whereIn('program_id', $programIds)->where('active', true)
            ->where('match_status', 'matched')->whereNotNull('product_id')->distinct()->pluck('product_id');
        $products = MpProduct::query()->with('identifiers')->whereIn('id', $ids)->get();
        $ce = $this->option('ce') ? $this->loadCe($programId, (string) $this->option('ce')) : [];
        $matcher = new SitemapMatcher();

        $rows = [];
        $createdTotal = 0;
        foreach ($domains as $d) {
            $site = MpSite::query()->where('domain', $d)->first();
            if (!$site || (int) $site->sitemap_pages === 0) {
                $rows[] = [$d, 'нет индекса', '', '', '', '', '', ''];
                continue;
            }
            $t = microtime(true);
            $cands = $matcher->match($site->id, $products);
            $nCand = array_sum(array_map('count', $cands));
            $byVia = ['code' => 0, 'name' => 0];

            // Оценка против CE: пары (товар × этот домен) эталона — URL CE в индексе? среди кандидатов?
            $cePairs = 0;
            $ceInIndex = 0;
            $ceHit = 0;
            $ceHitVia = ['code' => 0, 'name' => 0];
            foreach ($ce as $pid => $byDomain) {
                if (!isset($byDomain[$d])) {
                    continue;
                }
                $cePairs++;
                $ceHashes = $byDomain[$d];
                if (DB::table('mp_site_pages')->whereIn('url_hash', array_keys($ceHashes))->exists()) {
                    $ceInIndex++;
                }
                foreach ($cands[$pid] ?? [] as $c) {
                    if (isset($ceHashes[UrlCanonicalizer::hash($c['url'])])) {
                        $ceHit++;
                        $ceHitVia[$c['via']]++;
                        break;
                    }
                }
            }
            foreach ($cands as $list) {
                foreach ($list as $c) {
                    $byVia[$c['via']]++;
                }
            }

            $created = 0;
            if ($this->option('create')) {
                $created = $this->createListings($site, $cands);
                $createdTotal += $created;
            }
            $rows[] = [
                $d, (int) $site->sitemap_pages, count($cands) . ' / ' . $nCand, "{$byVia['code']} / {$byVia['name']}",
                $ce ? $cePairs : '—', $ce ? $ceInIndex : '—', $ce ? "{$ceHit} ({$ceHitVia['code']}/{$ceHitVia['name']})" : '—',
                $this->option('create') ? $created : sprintf('%.1f c', microtime(true) - $t),
            ];
        }
        $this->table(
            ['домен', 'страниц в индексе', 'товаров / кандидатов', 'по артикулу / по названию', 'пар CE', 'URL CE в sitemap', 'URL CE найден (арт./назв.)', $this->option('create') ? 'листингов заведено' : 'время'],
            $rows
        );
        if ($this->option('create')) {
            $this->info("Заведено листингов: {$createdTotal} — проверит monitoring:dispatch-checks (кап на сайт за тик).");
        }

        return self::SUCCESS;
    }

    /** @param array<int, array<int, array{url: string, score: float, via: string}>> $cands */
    private function createListings(MpSite $site, array $cands): int
    {
        $created = 0;
        foreach ($cands as $pid => $list) {
            foreach ($list as $c) {
                $listing = MpListing::query()->firstOrCreate(
                    ['product_id' => $pid, 'url_hash' => UrlCanonicalizer::hash($c['url'])],
                    ['site_id' => $site->id, 'url' => UrlCanonicalizer::canonicalize($c['url']), 'status' => MpListing::STATUS_ACTIVE,
                        'found_via' => 'sitemap', 'bench' => false, 'next_check_at' => now()]
                );
                // Проверку не ставим напрямую: тысячи джобов на один домен разом только
                // переносятся по слоту (бинлог MySQL). next_check_at=now → темп задаёт
                // monitoring:dispatch-checks (кап на сайт за тик).
                if ($listing->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /** @return array<int,string> */
    private function domains(): array
    {
        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));
        $domains = array_values(array_filter(array_map('trim', (array) $this->option('domain'))));
        if ($domains === [] && $this->option('all')) {
            $domains = $this->argument('action') === 'index'
                // Продавцы, у которых мы уже нашли хоть одну цену (не стенд).
                ? DB::table('mp_sites as s')->whereExists(fn ($q) => $q->from('mp_listings as l')->whereColumn('l.site_id', 's.id')
                    ->where('l.bench', false)->where('l.status', 'active')->whereNotNull('l.price'))
                    ->where(fn ($q) => $q->whereNull('s.sitemap_indexed_at')->orWhere('s.sitemap_indexed_at', '<', now()->subDays(6)))
                    ->pluck('s.domain')->all()
                : DB::table('mp_sites')->where('sitemap_pages', '>', 0)->pluck('domain')->all();

            return array_values(array_filter($domains, fn ($d) => !in_array($d, $skip, true) && !DomainPolicy::isBlocked($d)));
        }
        if ($domains === [] && $this->option('ce')) {
            $count = [];
            foreach ($this->readCe((string) $this->option('ce')) as $r) {
                $d = UrlCanonicalizer::registrableDomain($r['url']);
                $count[$d] = ($count[$d] ?? 0) + 1;
            }
            arsort($count);
            $domains = array_keys($count);
        }
        $domains = array_values(array_filter($domains, fn ($d) => !in_array($d, $skip, true) && !DomainPolicy::isBlocked($d)));

        return $this->option('domain') ? $domains : array_slice($domains, 0, (int) $this->option('top'));
    }

    /** @return array<int, array<string, array<string,bool>>> product_id => domain => [url_hash => true] */
    private function loadCe(int $programId, string $csv): array
    {
        $productByRef = DB::table('program_items')->where('program_id', $programId)->whereNotNull('product_id')->pluck('product_id', 'client_ref')->all();
        $out = [];
        foreach ($this->readCe($csv) as $r) {
            $pid = $productByRef[$r['lw_code']] ?? null;
            if ($pid) {
                $out[(int) $pid][UrlCanonicalizer::registrableDomain($r['url'])][UrlCanonicalizer::hash($r['url'])] = true;
            }
        }

        return $out;
    }

    /** @return \Generator<int, array<string,string>> строки CE с ценой */
    private function readCe(string $csv): \Generator
    {
        $fh = fopen($csv, 'r');
        $h = fgetcsv($fh, 0, ',', '"', '\\');
        while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($r) === count($h)) {
                $r = array_combine($h, $r);
                if ((float) $r['price'] > 0) {
                    yield $r;
                }
            }
        }
        fclose($fh);
    }
}
