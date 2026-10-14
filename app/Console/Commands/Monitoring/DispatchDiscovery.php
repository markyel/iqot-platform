<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\DiscoverProductJob;
use App\Models\Monitoring\MonitoringProgram;
use App\Models\Monitoring\MpProduct;
use App\Support\Monitoring\MonitoringSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер поиска листингов (Ф1): товары активных программ в состоянии new
 * (или повтор по next_search_at), не больше discovery_rate программы в сутки и общего
 * бюджета агрессивности (суточные запросы Яндекса / глубина поиска), равномерно по дню.
 * Флаг MONITORING_ENABLED, ручной прогон — --force; --product — точечно.
 */
class DispatchDiscovery extends Command
{
    protected $signature = 'monitoring:dispatch-discovery {--force} {--limit=} {--product=} {--program=}';

    protected $description = 'Мониторинг цен: поставить в очередь поиск листингов для товаров программ';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:dispatch-discovery выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }
        if ($pid = $this->option('product')) {
            DiscoverProductJob::dispatch((int) $pid);
            $this->info("Dispatched discovery for product {$pid}.");

            return self::SUCCESS;
        }

        $programs = MonitoringProgram::query()->where('status', MonitoringProgram::STATUS_ACTIVE)
            ->when($this->option('program'), fn ($q, $p) => $q->where('id', (int) $p))
            ->get();
        $total = 0;
        $global = $this->globalBudget();
        foreach ($programs as $program) {
            if ($global <= 0) {
                break;
            }
            // Сколько товаров программы уже искали сегодня — суточный лимит discovery_rate.
            $today = (int) DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
                ->where('pi.program_id', $program->id)->where('p.last_search_at', '>=', now()->startOfDay())->count();
            $budget = min($global, max(0, (int) $program->discovery_rate - $today));
            if ($this->option('limit')) {
                $budget = min($budget, (int) $this->option('limit'));
            }
            if ($budget <= 0) {
                continue;
            }

            $ids = DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
                ->where('pi.program_id', $program->id)->where('pi.active', true)->where('pi.match_status', 'matched')
                ->where(function ($q) {
                    $q->where('p.search_state', 'new')
                        ->orWhere(function ($q) {
                            $q->whereIn('p.search_state', ['found', 'not_found'])->whereNotNull('p.next_search_at')->where('p.next_search_at', '<=', now());
                        });
                })
                ->groupBy('p.id')
                ->orderByRaw("MAX(p.search_state = 'new') DESC, MAX(pi.priority) DESC, MIN(pi.id) ASC")
                ->limit($budget)->pluck('p.id');

            if ($ids->isNotEmpty()) {
                // Отметка «в поиске», чтобы следующий тик не продублировал.
                MpProduct::query()->whereIn('id', $ids)->update(['search_state' => 'queued', 'last_search_at' => now()]);
            }
            foreach ($ids as $id) {
                DiscoverProductJob::dispatch((int) $id);
            }
            $total += $ids->count();
            $global -= $ids->count();
        }

        $this->info("Dispatched discovery: {$total} (global budget left this tick: {$global})");

        return self::SUCCESS;
    }

    /**
     * Сколько товаров можно поставить в поиск на этом тике по агрессивности (MonitoringSettings):
     * остаток суточных запросов Яндекса / запросов на товар, минус уже стоящие в очереди;
     * равномерно по дню — остаток делится на оставшиеся 10-минутные тики (недобор догоняется).
     */
    private function globalBudget(): int
    {
        $perProduct = max(1, MonitoringSettings::get('search_queries'));
        $usedQueries = (int) DB::table('mp_budget_ledger')->where('day', now()->toDateString())
            ->where('resource', 'yandex_query')->sum('units');
        $left = max(0, MonitoringSettings::get('yandex_per_day') - $usedQueries);
        $inFlight = MpProduct::query()->where('search_state', 'queued')->count();
        $products = max(0, intdiv($left, $perProduct) - $inFlight);
        if ($this->option('limit') || $this->option('program')) {
            return $products; // ручной прогон — без растяжки по дню
        }
        $ticksLeft = max(1, (int) ceil(now()->diffInMinutes(now()->endOfDay()) / 10));

        return (int) ceil($products / $ticksLeft);
    }
}
