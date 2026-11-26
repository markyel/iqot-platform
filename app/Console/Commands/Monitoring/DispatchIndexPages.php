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
 *
 * Лидеры (26.11.2026, шаг 3): сайты с ≥ monitoring.index.leader_min_priced нашими ценами идут первыми, все,
 * без ограничения числа сайтов; их страницы обновляются раз в неделю, а загруженные до того, как индекс начал
 * сохранять цену, — перегружаются. Прочие сайты — раз в 30 дней, на остаток квоты.
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

        $all = DB::table('mp_sites as s')
            ->where('s.sitemap_pages', '>', 0)
            ->where(fn ($q) => $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<', now()))
            ->when($this->option('domain'), fn ($q, $d) => $q->whereIn('s.domain', (array) $d))
            ->selectRaw('s.id, s.domain, s.fetch_mode, (SELECT COUNT(*) FROM mp_listings l WHERE l.site_id = s.id AND l.status = "active" AND l.price IS NOT NULL) priced')
            ->orderByDesc('priced')->get();
        $leaderMin = (int) config('monitoring.index.leader_min_priced', 30);
        $leaders = $all->filter(fn ($s) => (int) $s->priced >= $leaderMin);
        $sites = $leaders->concat($all->reject(fn ($s) => (int) $s->priced >= $leaderMin)->take((int) $this->option('max-sites')));
        $leaderSince = now()->subDays((int) config('monitoring.index.leader_refresh_days', 7));
        $otherSince = now()->subDays((int) config('monitoring.index.refresh_days', 30));
        $priceSince = (string) config('monitoring.index.price_since');
        $headlessOk = app(\App\Services\Monitoring\MonitoringGovernor::class)->available('fetch_index_headless');

        $chunk = max(1, (int) $this->option('chunk'));
        $total = 0;
        foreach ($sites as $site) {
            if ($budget <= 0) {
                break;
            }
            if ($site->fetch_mode === 'headless' && !$headlessOk) {
                continue; // квота обхода браузером на сегодня вышла
            }
            $leader = (int) $site->priced >= $leaderMin;
            $busy = DB::table('mp_site_pages')->where('site_id', $site->id)->whereNotNull('index_queued_at')
                ->where('index_queued_at', '>', now()->subHours(2))->exists();
            if ($busy) {
                continue;
            }
            $ids = DB::table('mp_site_pages as p')
                ->where('p.site_id', $site->id)
                ->where(fn ($q) => $q->whereNull('p.fetched_at')
                    ->orWhereColumn('p.lastmod', '>', 'p.fetched_at')
                    ->orWhere('p.fetched_at', '<', $leader ? $leaderSince : $otherSince)
                    ->when($leader, fn ($q) => $q->orWhere(fn ($q) => $q->whereNull('p.price')->where('p.fetched_at', '<', $priceSince))))
                ->whereNotExists(fn ($q) => $q->from('mp_listings as l')->whereColumn('l.url_hash', 'p.url_hash'))
                ->orderByRaw('p.fetched_at IS NOT NULL, p.fetched_at, p.id')
                ->limit(min($chunk, $budget))->pluck('p.id')->all();
            if ($ids === []) {
                continue;
            }
            DB::table('mp_site_pages')->whereIn('id', $ids)->update(['index_queued_at' => now()]);
            IndexSitePagesJob::dispatch((int) $site->id, array_map('intval', $ids));
            $budget -= count($ids);
            $total += count($ids);
        }
        $this->info("Поставлено в обход страниц: {$total} (лидеров: {$leaders->count()})");

        return self::SUCCESS;
    }
}
