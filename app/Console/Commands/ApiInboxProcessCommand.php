<?php

namespace App\Console\Commands;

use App\Jobs\Api\InboxProcessingWorker;
use Illuminate\Console\Command;

/**
 * Запускает одну волну обработки api_inbox (синхронно по текущей настройке
 * QUEUE_CONNECTION). Self-rescheduling внутри джоба.
 *
 * Использование:
 *   php artisan api:inbox:process
 *   php artisan api:inbox:process --queue  # положить в очередь вместо sync-запуска
 */
class ApiInboxProcessCommand extends Command
{
    protected $signature = 'api:inbox:process {--queue : Dispatch to queue instead of running synchronously}';
    protected $description = 'Запустить одну волну InboxProcessingWorker для api_inbox';

    public function handle(): int
    {
        if ($this->option('queue')) {
            InboxProcessingWorker::dispatch();
            $this->info('Queued.');
            return self::SUCCESS;
        }

        InboxProcessingWorker::dispatchSync();
        $this->info('Processed inbox wave (sync).');
        return self::SUCCESS;
    }
}
