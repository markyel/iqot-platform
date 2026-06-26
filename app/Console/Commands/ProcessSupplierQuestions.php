<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSupplierQuestionJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер триажа вопросов поставщиков — замена крон-триггера n8n «Process
 * Supplier Questions» (каждые 120 мин).
 *
 * Порт «Get Pending Questions»: supplier_questions.status='pending', по дате
 * создания, лимит; на каждый — job в очередь `questions`.
 *
 * Флаг EMAILS_QUESTIONS_ENABLED по умолчанию OFF: включать ТОЛЬКО после отключения
 * n8n-воркфлоу (вставки author_questions/question_consolidation/outgoing_replies не
 * идемпотентны — параллельная работа двух систем плодит дубли). --force обходит
 * флаг для ручного/точечного прогона.
 */
class ProcessSupplierQuestions extends Command
{
    protected $signature = 'emails:process-questions
        {--force : Запустить даже при выключенном флаге EMAILS_QUESTIONS_ENABLED}
        {--limit= : Переопределить лимит вопросов за тик}
        {--question= : Точечный прогон одного вопроса по supplier_questions.id}';

    protected $description = 'Поставить триаж pending-вопросов поставщиков в очередь questions';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_questions.enabled')) {
            $this->warn('emails:process-questions выключен (EMAILS_QUESTIONS_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        if ($questionId = $this->option('question')) {
            ProcessSupplierQuestionJob::dispatch((int) $questionId);
            $this->info("Dispatched questions job for question {$questionId}.");
            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: config('services.email_questions.batch_limit', 10));

        $ids = DB::connection('reports')->table('supplier_questions as sq')
            ->join('email_conversations as ec', 'sq.conversation_id', '=', 'ec.id')
            ->join('suppliers as s', 'sq.supplier_id', '=', 's.id')
            ->where('sq.status', 'pending')
            ->orderBy('sq.created_at')
            ->limit($limit)
            ->pluck('sq.id');

        if ($ids->isEmpty()) {
            $this->info('No pending supplier questions.');
            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            ProcessSupplierQuestionJob::dispatch((int) $id);
        }

        $this->info("Dispatched questions jobs: {$ids->count()} question(s).");
        return self::SUCCESS;
    }
}
