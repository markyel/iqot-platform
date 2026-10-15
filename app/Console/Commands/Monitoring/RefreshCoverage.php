<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MonitoringProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Подведение итогов поиска и покрытия (Ф1):
 *  - товар в «searching», чьи новые листинги проверены (или поиск старше 24 ч — на случай длинной очереди) →
 *    found (есть цена на активном листинге) / not_found; next_search_at — повтор;
 *  - счётчики листингов товара; last_priced_at / price_source позиций;
 *  - items_covered программ (позиции со свежей ценой в пределах target_freshness_days).
 */
class RefreshCoverage extends Command
{
    protected $signature = 'monitoring:refresh-coverage {--force}';

    protected $description = 'Мониторинг цен: итоги поиска листингов и покрытие программ';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:refresh-coverage выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }

        // 0. Цены-заглушки сайтов: одно значение у ≥10 товаров и ≥20% цен сайта («по запросу» под
        //    видом цены; snab-lift 15.10.2026: 1 500 ₽ — 41%, 15 000 ₽ — 23%). Список у сайта только
        //    пополняется (после очистки значение из статистики уйдёт, а заглушкой быть не перестанет);
        //    такие цены снимаются с листингов, позиции помечаются изменёнными (синхронизация API).
        $this->placeholderPrices();

        // 1. Счётчики листингов по товарам (без стенда).
        DB::statement("
            UPDATE mp_products p
            LEFT JOIN (
                SELECT product_id,
                       SUM(status = 'active') a,
                       SUM(status = 'active' AND price IS NOT NULL) pr
                FROM mp_listings WHERE bench = 0 GROUP BY product_id
            ) l ON l.product_id = p.id
            SET p.listings_active = COALESCE(l.a, 0), p.listings_priced = COALESCE(l.pr, 0)
        ");

        // 2. Итог поиска: проверки новых листингов отработали (или поиск давно).
        $foundDays = (int) config('monitoring.search.research_found_days', 30);
        $notFound = (array) config('monitoring.search.research_not_found_days', [30, 60, 90]);
        $rows = DB::select("
            SELECT p.id, p.listings_priced, p.search_attempts,
                   (SELECT COUNT(*) FROM mp_listings l WHERE l.product_id = p.id AND l.bench = 0
                        AND l.status = 'active' AND l.checked_at IS NULL) pending
            FROM mp_products p WHERE p.search_state = 'searching'
        ");
        $done = 0;
        foreach ($rows as $r) {
            $stale = DB::table('mp_products')->where('id', $r->id)->where('last_search_at', '<', now()->subHours(24))->exists();
            if ((int) $r->pending > 0 && !$stale) {
                continue;
            }
            $found = (int) $r->listings_priced > 0;
            $days = $found ? $foundDays : (int) ($notFound[min(max(0, (int) $r->search_attempts - 1), count($notFound) - 1)] ?? 90);
            DB::table('mp_products')->where('id', $r->id)->update([
                'search_state' => $found ? 'found' : 'not_found',
                'next_search_at' => now()->addDays($days),
                'updated_at' => now(),
            ]);
            $done++;
        }

        // 2b. Потерянные в очереди (джоб упал/убит) — вернуть в поиск.
        DB::table('mp_products')->where('search_state', 'queued')->where('last_search_at', '<', now()->subHours(12))
            ->update(['search_state' => 'new', 'updated_at' => now()]);

        // 3. Свежесть позиций программ: цена сайта или КП поставщика (Гибрид) — что свежее.
        //    price_source = site, если есть цена на сайте; kp — только КП (сайт «по запросу» / пул).
        DB::statement("
            UPDATE program_items pi
            LEFT JOIN (
                SELECT product_id, MAX(observed_at) last_obs
                FROM mp_listings WHERE bench = 0 AND status = 'active' AND price IS NOT NULL GROUP BY product_id
            ) l ON l.product_id = pi.product_id
            LEFT JOIN (
                SELECT product_id, MAX(received_at) last_kp FROM mp_kp_offers GROUP BY product_id
            ) k ON k.product_id = pi.product_id
            SET pi.last_priced_at = GREATEST(COALESCE(l.last_obs, k.last_kp), COALESCE(k.last_kp, l.last_obs)),
                pi.price_source = IF(l.last_obs IS NOT NULL, 'site', IF(k.last_kp IS NOT NULL, 'kp', 'none'))
        ");

        // 4. Покрытие программ.
        foreach (MonitoringProgram::query()->whereIn('status', ['active', 'paused'])->get() as $program) {
            $covered = DB::table('program_items')->where('program_id', $program->id)->where('active', true)
                ->where('last_priced_at', '>=', now()->subDays((int) $program->target_freshness_days))->count();
            $program->forceFill(['items_covered' => $covered])->save();
            $program->refreshCounters();
        }

        $this->info("Search finalized: {$done}");

        return self::SUCCESS;
    }

    private function placeholderPrices(): void
    {
        $rows = DB::select("
            SELECT l.site_id, l.price, COUNT(*) c, t.total
            FROM mp_listings l
            JOIN (SELECT site_id, COUNT(*) total FROM mp_listings
                  WHERE bench = 0 AND status = 'active' AND price IS NOT NULL GROUP BY site_id) t ON t.site_id = l.site_id
            WHERE l.bench = 0 AND l.status = 'active' AND l.price IS NOT NULL
            GROUP BY l.site_id, l.price, t.total
            HAVING c >= 10 AND c / t.total >= 0.2
        ");
        $bySite = [];
        foreach ($rows as $r) {
            $bySite[(int) $r->site_id][] = round((float) $r->price, 2);
        }
        foreach ($bySite as $siteId => $prices) {
            $site = \App\Models\Monitoring\MpSite::find($siteId);
            if (!$site) {
                continue;
            }
            $stats = (array) ($site->stats_30d ?? []);
            $known = array_map('floatval', (array) ($stats['placeholder_prices'] ?? []));
            $merged = array_values(array_unique(array_merge($known, $prices), SORT_REGULAR));
            if ($merged !== $known) {
                $stats['placeholder_prices'] = $merged;
                $site->forceFill(['stats_30d' => $stats])->save();
                $this->warn("Цены-заглушки {$site->domain}: " . implode(', ', $merged));
            }
        }

        // Снять заглушки с листингов (и при новых значениях, и для сайтов с уже известными).
        foreach (\App\Models\Monitoring\MpSite::query()->whereNotNull('stats_30d')->get(['id', 'stats_30d']) as $site) {
            $prices = (array) (($site->stats_30d ?? [])['placeholder_prices'] ?? []);
            if ($prices === []) {
                continue;
            }
            $affected = DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)
                ->whereIn('price', $prices)->pluck('product_id')->unique();
            if ($affected->isEmpty()) {
                continue;
            }
            DB::table('mp_listings')->where('site_id', $site->id)->where('bench', false)->whereIn('price', $prices)
                ->update(['price' => null, 'price_kind' => 'on_request', 'updated_at' => now()]);
            DB::table('program_items')->whereIn('product_id', $affected)->update(['prices_changed_at' => now()]);
        }
    }
}
