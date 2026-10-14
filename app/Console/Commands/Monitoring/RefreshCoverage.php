<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MonitoringProgram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Подведение итогов поиска и покрытия (Ф1):
 *  - товар в «searching», чьи новые листинги проверены (или поиск старше 6 ч) →
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
            $stale = DB::table('mp_products')->where('id', $r->id)->where('last_search_at', '<', now()->subHours(6))->exists();
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

        // 3. Свежесть позиций программ.
        DB::statement("
            UPDATE program_items pi
            LEFT JOIN (
                SELECT product_id, MAX(observed_at) last_obs
                FROM mp_listings WHERE bench = 0 AND status = 'active' AND price IS NOT NULL GROUP BY product_id
            ) l ON l.product_id = pi.product_id
            SET pi.last_priced_at = l.last_obs,
                pi.price_source = IF(l.last_obs IS NULL, 'none', 'site')
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
}
