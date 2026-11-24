<?php

namespace App\Console\Commands\Monitoring;

use App\Jobs\Monitoring\VatBackfillJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Досчёт НДС у найденных цен, где признак пуст (24.11.2026): по заданию на страницу (VatBackfillJob) — загрузка и
 * VatDetector, без ИИ. Новые проверки определяют НДС сами (ListingChecker).
 */
class VatBackfillCommand extends Command
{
    protected $signature = 'monitoring:vat-backfill {--limit=0 : не больше N страниц (0 — все)} {--dry-run}';

    protected $description = 'Мониторинг: досчитать признак НДС у найденных цен по тексту страниц';

    public function handle(): int
    {
        $q = DB::table('mp_listings')->where('bench', false)->whereIn('status', ['active', 'suspicious'])
            ->where('price', '>', 0)->whereNull('vat_included')->orderBy('id');
        if ((int) $this->option('limit') > 0) {
            $q->limit((int) $this->option('limit'));
        }
        $ids = $q->pluck('id')->all();
        if (!$this->option('dry-run')) {
            foreach ($ids as $id) {
                VatBackfillJob::dispatch((int) $id);
            }
        }
        $this->info('Страниц с ценой без признака НДС: ' . count($ids) . ($this->option('dry-run') ? ' (dry-run)' : ' — поставлено в очередь monitor'));

        return self::SUCCESS;
    }
}
