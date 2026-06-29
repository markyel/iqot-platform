<?php

namespace App\Console\Commands;

use App\Services\Questions\QuestionContextLoader;
use App\Services\Questions\ReplyEmailBuilder;
use App\Services\Questions\SupplierQuestionPersister;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Авто-закрытие зависших вопросов к автору. Если вопрос направлен автору
 * (supplier_questions.status='forwarded_to_author', author_answer пуст) и висит
 * дольше N дней — отправляем поставщику вежливый ответ «информации нет» и
 * закрываем вопрос (status='auto_answered'), чтобы он не висел вечно.
 *
 * Флаг EMAILS_AUTOCLOSE_ENABLED по умолчанию OFF — включать осознанно (на бэклоге
 * может быть много зависших). --limit ограничивает объём за прогон (дозированный
 * слив бэклога), --dry-run печатает кандидатов без отправки.
 */
class CloseStaleQuestions extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:auto-close-questions
        {--days=4 : Через сколько дней без ответа автора закрывать}
        {--limit=150 : Максимум вопросов за прогон}
        {--force : Запустить при выключенном флаге EMAILS_AUTOCLOSE_ENABLED}
        {--dry-run : Показать кандидатов без отправки}';

    protected $description = 'Закрыть зависшие вопросы к автору авто-ответом поставщику «информации нет»';

    private const ANSWER = 'К сожалению, запрошенной информации у нас нет. Если возможно, просим ориентироваться на данные из заявки. Будем признательны за ваше коммерческое предложение по доступным позициям.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        if (!$dry && !$this->option('force') && !config('services.email_questions.autoclose_enabled', false)) {
            $this->warn('emails:auto-close-questions выключен (EMAILS_AUTOCLOSE_ENABLED=false). --force/--dry-run для ручного.');
            return self::SUCCESS;
        }

        $days = max(1, (int) ($this->option('days') ?: 4));
        $limit = max(1, (int) ($this->option('limit') ?: 150));

        $stale = DB::connection(self::CONN)->table('supplier_questions as sq')
            ->join('email_conversations as ec', 'ec.id', '=', 'sq.conversation_id')
            ->where('sq.status', 'forwarded_to_author')
            ->whereNull('sq.author_answer')
            ->where('sq.created_at', '<', now()->subDays($days))
            ->whereNotIn('ec.status', ['complete', 'rejected', 'no_response'])
            ->orderBy('sq.created_at')
            ->limit($limit)
            ->get(['sq.id', 'sq.conversation_id', 'sq.supplier_id', 'sq.batch_id', 'sq.created_at']);

        if ($stale->isEmpty()) {
            $this->info('Зависших вопросов нет.');
            return self::SUCCESS;
        }

        $loader = new QuestionContextLoader(15);
        $done = 0;
        $failed = 0;

        foreach ($stale as $sq) {
            if ($dry) {
                $this->line("would close q#{$sq->id} (created {$sq->created_at})");
                $done++;
                continue;
            }
            try {
                $batch = $loader->loadBatch((int) $sq->batch_id);
                $sender = $batch !== null ? $loader->loadSender((int) $batch->sender_id) : null;
                $originalMessage = $loader->loadOriginalMessage((int) $sq->conversation_id);
                $templateId = $sender !== null ? (int) ($sender->preferred_template_id ?: $sender->template_id ?: 0) : 0;
                $template = $loader->loadTemplate($templateId > 0 ? $templateId : null);

                $context = [
                    'question_id' => (int) $sq->id,
                    'conversation_id' => (int) $sq->conversation_id,
                    'sender_id' => $batch !== null ? (int) $batch->sender_id : 0,
                    'supplier_id' => (int) $sq->supplier_id,
                    'sender_email' => $sender->email ?? null,
                    'sender_full_name' => $sender->sender_full_name ?? null,
                    'sender_phone' => $sender->phone ?? null,
                    'sender_position' => null,
                    'sender_greeting' => $sender->email_greeting ?? 'Здравствуйте',
                    'organization_name' => $sender->organization_name ?? null,
                    'tracking_token' => $batch->tracking_token ?? null,
                    'answer_text' => self::ANSWER,
                    'original_reply_id' => null,
                    'has_files_to_copy' => false,
                ];

                $reply = (new ReplyEmailBuilder())->build($context, $originalMessage, $template);
                (new SupplierQuestionPersister())->persistAuto($reply); // outgoing_replies(pending) + status=auto_answered

                DB::connection(self::CONN)->table('supplier_questions')->where('id', $sq->id)
                    ->update(['answered_at' => now()]);
                DB::connection(self::CONN)->table('author_questions')->where('supplier_question_id', $sq->id)
                    ->update(['status' => 'answered']);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('CloseStaleQuestions: failed', ['question_id' => $sq->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        $this->info("auto-closed: $done failed: $failed" . ($dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
