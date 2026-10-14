<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\DiscoverProductJob;
use App\Models\Monitoring\MonitoringProgram;
use App\Models\Monitoring\MpProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер поиска листингов (Ф1): товары активных программ в состоянии new
 * (или повтор по next_search_at), не больше discovery_rate программы в сутки.
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
        foreach ($programs as $program) {
            // Сколько товаров программы уже искали сегодня — суточный лимит discovery_rate.
            $today = (int) DB::table('program_items as pi')->join('mp_products as p', 'p.id', '=', 'pi.product_id')
                ->where('pi.program_id', $program->id)->where('p.last_search_at', '>=', now()->startOfDay())->count();
            $budget = max(0, (int) $program->discovery_rate - $today);
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
                ->orderByRaw("p.search_state = 'new' DESC")->orderByDesc('pi.priority')->orderBy('pi.id')
                ->limit($budget)->distinct()->pluck('p.id');

            if ($ids->isNotEmpty()) {
                // Отметка «в поиске», чтобы следующий тик не продублировал.
                MpProduct::query()->whereIn('id', $ids)->update(['search_state' => 'searching', 'last_search_at' => now()]);
            }
            foreach ($ids as $id) {
                DiscoverProductJob::dispatch((int) $id);
            }
            $total += $ids->count();
        }

        $this->info("Dispatched discovery: {$total}");

        return self::SUCCESS;
    }
}
