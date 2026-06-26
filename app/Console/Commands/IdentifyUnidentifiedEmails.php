<?php

namespace App\Console\Commands;

use App\Jobs\IdentifyUnidentifiedEmailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер второго прохода идентификации неопознанных писем — замена крон-триггера
 * n8n «Process Unidentified Emails v4».
 *
 * Порт «Get Pending Unidentified»: status='pending', processing_attempts < max, по
 * дате создания; на каждое — job в очередь `identify`. Бэунсы (reason='bounce') в
 * выборку НЕ берём — это NDR от Mailer-Daemon, к беседе их привязать нельзя.
 *
 * Флаг EMAILS_IDENTIFY_ENABLED по умолчанию OFF: включать ТОЛЬКО после отключения
 * n8n-воркфлоу (миграция письма создаёт боевые строки — параллельная работа двух
 * систем плодит дубли). --force обходит флаг для ручного/точечного прогона.
 */
class IdentifyUnidentifiedEmails extends Command
{
    protected $signature = 'emails:identify-unidentified
        {--force : Запустить даже при выключенном флаге EMAILS_IDENTIFY_ENABLED}
        {--limit= : Переопределить лимит писем за тик}
        {--email= : Точечный прогон одного письма по unidentified_emails.id}';

    protected $description = 'Поставить идентификацию неопознанных писем (второй проход) в очередь identify';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_identify.enabled')) {
            $this->warn('emails:identify-unidentified выключен (EMAILS_IDENTIFY_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        if ($emailId = $this->option('email')) {
            IdentifyUnidentifiedEmailJob::dispatch((int) $emailId);
            $this->info("Dispatched identify job for unidentified email {$emailId}.");
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('services.email_identify.batch_limit', 50));
        $maxAttempts = (int) config('services.email_identify.max_attempts', 5);

        $ids = DB::connection('reports')->table('unidentified_emails')
            ->where('status', 'pending')
            ->where('processing_attempts', '<', $maxAttempts)
            ->where(function ($q) {
                $q->whereNull('reason')->orWhere('reason', '<>', 'bounce');
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->info('No pending unidentified emails.');
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            IdentifyUnidentifiedEmailJob::dispatch((int) $id);
        }

        $this->info("Dispatched identify jobs: {$ids->count()} email(s).");
        return self::SUCCESS;
    }
}
