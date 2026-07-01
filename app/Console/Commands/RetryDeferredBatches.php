<?php

namespace App\Console\Commands;

use App\Jobs\GenerateCampaignJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Повтор отложенных гейтом качества батчей (discovery-first). Батч откладывается при
 * слабом пуле / малом % найденных Яндексом; discovery добирает поставщиков и, когда
 * все кандидаты обработаны, deferred_batches.status='ready'. Здесь диспатчим повторную
 * генерацию (GenerateCampaignJob в retry-режиме) — уже без гейта, с переиспользованием
 * сохранённой Яндекс-выдачи и обогащённым пулом.
 */
class RetryDeferredBatches extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:retry-deferred {--limit=50 : Максимум отложенных за тик}';

    protected $description = 'Повтор отложенных гейтом качества батчей, когда discovery готов';

    public function handle(): int
    {
        if (!(bool) config('services.email_pool.gate_enabled', false)) {
            $this->warn('Гейт качества выключен (EMAILS_POOL_GATE_ENABLED=false).');
            return self::SUCCESS;
        }

        $limit = max(1, (int) $this->option('limit'));
        $ready = DB::connection(self::CONN)->table('deferred_batches')
            ->where('status', 'ready')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($ready === []) {
            $this->info('Нет готовых отложенных батчей.');
            return self::SUCCESS;
        }

        foreach ($ready as $id) {
            GenerateCampaignJob::dispatch([], false, (int) $id);
        }

        $this->info('Отправлено на повтор: ' . count($ready) . ' отложенных батчей.');
        return self::SUCCESS;
    }
}
