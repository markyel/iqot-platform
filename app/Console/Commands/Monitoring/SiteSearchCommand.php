<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\SiteSearchProductJob;
use App\Models\Monitoring\MpSite;
use App\Services\Monitoring\Discovery\SiteSearch;
use App\Support\Monitoring\DomainPolicy;
use App\Support\Monitoring\UrlCanonicalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: поиск по сайтам продавцов.
 *
 *   monitoring:site-search profile --domain=revator.ru         подобрать шаблон поиска (проверка на известных страницах)
 *   monitoring:site-search profile --ce=<csv> --top=15          для топ-доменов эталона CE
 *   monitoring:site-search run --program=1 --domain=revator.ru  поставить в очередь поиск товаров программы,
 *                                                                 у которых на сайте ещё нет листинга
 */
class SiteSearchCommand extends Command
{
    protected $signature = 'monitoring:site-search {action : profile|run}
        {--domain=*} {--ce=} {--top=15} {--skip=chipdip.ru}
        {--program=1} {--limit=0 : не больше N товаров на сайт (0 — все)}';

    protected $description = 'Мониторинг цен: поиск по сайтам продавцов — профилирование шаблонов и запуск (Ф2)';

    public function handle(): int
    {
        $domains = $this->domains();
        if ($domains === []) {
            $this->error('Нет доменов: --domain=… или --ce=<csv>');

            return self::FAILURE;
        }

        return $this->argument('action') === 'profile' ? $this->profile($domains) : $this->run($domains);
    }

    private function profile(array $domains): int
    {
        $ss = SiteSearch::make();
        $rows = [];
        foreach ($domains as $d) {
            $site = MpSite::forUrl('https://' . $d);
            if ($site->isBlocked()) {
                $rows[] = [$d, '—', "сайт заблокирован до {$site->blocked_until}"];
                continue;
            }
            $r = $ss->profile($site);
            $rows[] = [$d, $r['template'] ?? '—', $r['note'] . " (запросов {$r['tried']})"];
        }
        $this->table(['домен', 'шаблон поиска', 'проверка'], $rows);

        return self::SUCCESS;
    }

    private function run(array $domains): int
    {
        $programId = (int) $this->option('program');
        $productIds = DB::table('program_items')->where('program_id', $programId)->where('active', true)
            ->where('match_status', 'matched')->whereNotNull('product_id')->distinct()->pluck('product_id')->all();
        $limit = (int) $this->option('limit');
        $rows = [];
        foreach ($domains as $d) {
            $site = MpSite::query()->where('domain', $d)->first();
            if (!$site || !$site->search_url_template || $site->isBlocked()) {
                $rows[] = [$d, '—', 0];
                continue;
            }
            // Товары без листинга на этом сайте (любого статуса — отрицательные тоже не ищем заново).
            $have = DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)
                ->whereIn('product_id', $productIds)->distinct()->pluck('product_id')->flip();
            $n = 0;
            foreach ($productIds as $pid) {
                if (isset($have[$pid]) || Cache::has(SiteSearchProductJob::doneKey($site->id, (int) $pid))) {
                    continue;
                }
                SiteSearchProductJob::dispatch($site->id, (int) $pid);
                if (++$n >= $limit && $limit > 0) {
                    break;
                }
            }
            $rows[] = [$d, $site->search_url_template, $n];
        }
        $this->table(['домен', 'шаблон', 'поставлено поисков'], $rows);

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function domains(): array
    {
        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));
        $domains = array_values(array_filter(array_map('trim', (array) $this->option('domain'))));
        if ($domains === [] && $this->option('ce')) {
            $count = [];
            $fh = fopen((string) $this->option('ce'), 'r');
            $h = fgetcsv($fh, 0, ',', '"', '\\');
            while (($r = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
                if (count($r) === count($h)) {
                    $r = array_combine($h, $r);
                    if ((float) $r['price'] > 0) {
                        $d = UrlCanonicalizer::registrableDomain($r['url']);
                        $count[$d] = ($count[$d] ?? 0) + 1;
                    }
                }
            }
            fclose($fh);
            arsort($count);
            $domains = array_slice(array_keys($count), 0, (int) $this->option('top') + count($skip));
        }

        return array_values(array_filter($domains, fn ($d) => !in_array($d, $skip, true) && !DomainPolicy::isBlocked($d)));
    }
}
