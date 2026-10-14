<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\IndexSitePagesJob;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ф2: диспетчер обхода карточек по sitemap (индекс заголовков/артикулов).
 * Каждый тик — не больше одной порции на сайт в работе (сайт всё равно идёт с паузой
 * 4 с), сайты по ценности (листинги с ценой), в пределах суточной квоты fetch_index
 * (агрессивность). К обходу: ещё не скачанные, изменившиеся по lastmod, старше 30 дней.
 * Страницы, уже ставшие листингами, пропускаем — их и так скачивает проверка цены.
 */
class DispatchIndexPages extends Command
{
    protected $signature = 'monitoring:index-pages {--force} {--domain=*} {--chunk=100} {--max-sites=30}';

    protected $description = 'Мониторинг цен: обход карточек сайтов по sitemap — индекс заголовков и артикулов (Ф2)';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:index-pages выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }
        $used = (int) DB::table('mp_budget_ledger')->where('day', now()->toDateString())->where('resource', 'fetch_index')->sum('units');
        $inFlight = (int) DB::table('mp_site_pages')->whereNotNull('index_queued_at')->where('index_queued_at', '>', now()->subHours(2))->count();
        $budget = MonitoringSettings::get('index_per_day') - $used - $inFlight;
        if ($budget <= 0) {
            $this->info("Квота обхода исчерпана (сегодня {$used}, в работе {$inFlight}).");

            return self::SUCCESS;
        }

        $sites = DB::table('mp_sites as s')
            ->where('s.sitemap_pages', '>', 0)
            ->where(fn ($q) => $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<', now()))
            ->when($this->option('domain'), fn ($q, $d) => $q->whereIn('s.domain', (array) $d))
            ->selectRaw('s.id, s.domain, (SELECT COUNT(*) FROM mp_listings l WHERE l.site_id = s.id AND l.status = "active" AND l.price IS NOT NULL) priced')
            ->orderByDesc('priced')->limit((int) $this->option('max-sites'))->get();

        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;
        foreach ($sites as $site) {
            if ($budget <= 0) {
                break;
            }
            $busy = DB::table('mp_site_pages')->where('site_id', $site->id)->whereNotNull('index_queued_at')
                ->where('index_queued_at', '>', now()->subHours(2))->exists();
            if ($busy) {
                continue;
            }
            $ids = DB::table('mp_site_pages as p')
                ->where('p.site_id', $site->id)
                ->where(fn ($q) => $q->whereNull('p.fetched_at')
                    ->orWhereColumn('p.lastmod', '>', 'p.fetched_at')
                    ->orWhere('p.fetched_at', '<', now()->subDays(30)))
                ->whereNotExists(fn ($q) => $q->from('mp_listings as l')->whereColumn('l.url_hash', 'p.url_hash'))
                ->orderByRaw('p.fetched_at IS NOT NULL, p.id')
                ->limit(min($chunk, $budget))->pluck('p.id')->all();
            if ($ids === []) {
                continue;
            }
            DB::table('mp_site_pages')->whereIn('id', $ids)->update(['index_queued_at' => now()]);
            IndexSitePagesJob::dispatch((int) $site->id, array_map('intval', $ids));
            $budget -= count($ids);
            $total += count($ids);
        }
        $this->info("Поставлено в обход страниц: {$total}");

        return self::SUCCESS;
    }
}
