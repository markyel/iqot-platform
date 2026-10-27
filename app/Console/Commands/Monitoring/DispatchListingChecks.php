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

        // Не больше, чем сайт успеет за тик (5 мин / пауза сайта): лишние джобы на один домен
        // только переносятся по занятому слоту — каждый перенос пишет строку jobs, бинлог
        // MySQL 14–15.10 вырос до ~1,2 ГБ/ч и забил диск прода (9 415 проверок sitemap разом).
        $tickMs = 300_000;
        $siteCap = DB::table('mp_sites')->pluck('rate_limit_ms', 'id')
            ->map(fn ($ms) => max(1, (int) ceil($tickMs / max(1000, (int) $ms))))->all();

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
            // Только товары, нужные хоть одной АКТИВНОЙ программе: программа на паузе (Пилот, решение владельца
            // 27.10.2026) не тратит загрузки, рендеры и ИИ на переобход. Товары общие — нужен активной, обходим.
            ->whereExists(fn ($q) => $q->from('program_items as pi')->join('monitoring_programs as mp', 'mp.id', '=', 'pi.program_id')
                ->whereColumn('pi.product_id', 'l.product_id')->where('pi.active', true)->where('mp.status', 'active'))
            ->orderByRaw('l.next_check_at IS NULL DESC, l.next_check_at ASC')
            ->limit($limit * 20)
            ->get(['l.id', 'l.site_id']);

        // Round-robin по сайтам, не больше капа сайта на тик.
        $bySite = [];
        foreach ($rows as $r) {
            if (count($bySite[$r->site_id] ?? []) < ($siteCap[$r->site_id] ?? 75)) {
                $bySite[$r->site_id][] = (int) $r->id;
            }
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
