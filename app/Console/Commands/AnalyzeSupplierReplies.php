<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeSupplierReplyJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер AI-анализа ответов поставщиков — замена крон-триггера n8n «Process
 * Email Conversations».
 *
 * Порт «Get Unprocessed Messages»: входящие письма с ai_processed=0, диалог не в
 * терминальном статусе, по дате получения; на каждое — job в очередь `analyze`.
 *
 * Флаг EMAILS_ANALYZE_ENABLED по умолчанию OFF: включать ТОЛЬКО после отключения
 * n8n-воркфлоу (вставки multi/questions не идемпотентны — параллельная работа двух
 * систем плодит дубли). --force обходит флаг для ручного/точечного прогона.
 */
class AnalyzeSupplierReplies extends Command
{
    protected $signature = 'emails:analyze-replies
        {--force : Запустить даже при выключенном флаге EMAILS_ANALYZE_ENABLED}
        {--limit= : Переопределить лимит писем за тик}
        {--message= : Точечный прогон одного письма по email_messages.id}';

    protected $description = 'Поставить AI-анализ необработанных ответов поставщиков в очередь analyze';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_analysis.enabled')) {
            $this->warn('emails:analyze-replies выключен (EMAILS_ANALYZE_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        if ($messageId = $this->option('message')) {
            AnalyzeSupplierReplyJob::dispatch((int) $messageId);
            $this->info("Dispatched analyze job for message {$messageId}.");
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('services.email_analysis.batch_limit', 50));

        $ids = DB::connection('reports')->table('email_messages as em')
            ->join('email_conversations as ec', 'em.conversation_id', '=', 'ec.id')
            ->where('em.direction', 'incoming')
            ->where('em.ai_processed', 0)
            ->whereNotIn('ec.status', ['complete', 'rejected', 'no_response'])
            ->orderBy('em.received_at')
            ->limit($limit)
            ->pluck('em.id');

        if ($ids->isEmpty()) {
            $this->info('No unprocessed incoming messages.');
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            AnalyzeSupplierReplyJob::dispatch((int) $id);
        }

        $this->info("Dispatched analyze jobs: {$ids->count()} message(s).");
        return self::SUCCESS;
    }
}
