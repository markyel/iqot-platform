<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\CheckListingJob;
use App\Models\Monitoring\MpListing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер проверок листингов: next_check_at <= now, статус active/suspicious/failed
 * (не blocked/gone/mismatch), не стенд. Round-robin по доменам, чтобы крупный
 * сайт не забивал очередь. Флаг MONITORING_ENABLED, ручной прогон — --force.
 */
class DispatchListingChecks extends Command
{
    protected $signature = 'monitoring:dispatch-checks {--limit=} {--force} {--listing=}';

    protected $description = 'Мониторинг цен: поставить в очередь проверки листингов, у которых подошёл срок';

    public function handle(): int
    {
        if (!$this->option('force') && !config('monitoring.enabled')) {
            $this->warn('monitoring:dispatch-checks выключен (MONITORING_ENABLED=false). --force для ручного запуска.');

            return self::SUCCESS;
        }

        if ($id = $this->option('listing')) {
            CheckListingJob::dispatch((int) $id);
            $this->info("Dispatched check for listing {$id}.");

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('monitoring.schedule.dispatch_batch', 500));

        $rows = DB::table('mp_listings as l')
            ->join('mp_sites as s', 's.id', '=', 'l.site_id')
            ->where('l.bench', false)
            ->whereIn('l.status', [MpListing::STATUS_ACTIVE, MpListing::STATUS_SUSPICIOUS, MpListing::STATUS_FAILED])
            ->where(function ($q) {
                $q->whereNull('l.next_check_at')->orWhere('l.next_check_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<=', now());
            })
            ->orderByRaw('l.next_check_at IS NULL DESC, l.next_check_at ASC')
            ->limit($limit * 3)
            ->get(['l.id', 'l.site_id']);

        // Round-robin по сайтам.
        $bySite = [];
        foreach ($rows as $r) {
            $bySite[$r->site_id][] = (int) $r->id;
        }
        $picked = [];
        while (count($picked) < $limit && $bySite !== []) {
            foreach ($bySite as $site => &$ids) {
                if ($ids === []) {
                    unset($bySite[$site]);
                    continue;
                }
                $picked[] = array_shift($ids);
                if (count($picked) >= $limit) {
                    break;
                }
            }
            unset($ids);
        }

        // Отметка «взят в работу» — повторный тик его не продублирует (джоб сам выставит след. срок).
        if ($picked !== []) {
            MpListing::query()->whereIn('id', $picked)->update(['next_check_at' => now()->addHours(6)]);
        }
        foreach ($picked as $id) {
            CheckListingJob::dispatch($id);
        }

        $this->info('Dispatched listing checks: ' . count($picked));

        return self::SUCCESS;
    }
}
