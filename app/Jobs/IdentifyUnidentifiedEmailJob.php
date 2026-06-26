<?php

namespace App\Jobs;

use App\Services\Analysis\DocumentTextExtractor;
use App\Services\Analysis\EmailBodyCleaner;
use App\Services\Identify\CandidateBatchLoader;
use App\Services\Identify\IdentificationAnalyzer;
use App\Services\Identify\IdentifiedEmailPersister;
use App\Services\Identify\MailboxTokenMatcher;
use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Идентификация одного неопознанного письма (второй проход) — порт n8n-воркфлоу
 * «Process Unidentified Emails v4» (Loop Over Emails → Update Attempt → Get Tokens →
 * Match Token → Find Batches → AI Agent → Parse → Update as Identified / Migrate).
 *
 * Письма с потерянным/искажённым токеном, которые IncomingEmailRouter не смог
 * привязать на приёме (unidentified_emails.status='pending'). Пытаемся идентифицировать
 * сильнее: мягкий матч токена (MailboxTokenMatcher), кандидаты по домену поставщика
 * (CandidateBatchLoader), AI-сопоставление товаров по названию (IdentificationAnalyzer),
 * запись результата (IdentifiedEmailPersister).
 *
 * Идемпотентность: claim через Cache::lock + повторная проверка status='pending' и
 * processing_attempts < max внутри лока; email_messages деду­плицируется по message_id;
 * вложения мигрируются только при создании нового сообщения.
 */
class IdentifyUnidentifiedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(private readonly int $emailId)
    {
        $this->onQueue('identify');
    }

    public function handle(): void
    {
        $lock = Cache::lock("identify:ue:{$this->emailId}", 170);
        if (!$lock->get()) {
            return;
        }

        try {
            $ic = config('services.email_identify');
            $maxAttempts = (int) ($ic['max_attempts'] ?? 5);

            $email = $this->claimEmail($maxAttempts);
            if ($email === null) {
                return; // уже обработано / превышены попытки / не pending
            }

            // Счётчик попыток (порт «Update Attempt Counter»).
            DB::connection('reports')->table('unidentified_emails')
                ->where('id', $this->emailId)
                ->update([
                    'processing_attempts' => (int) $email->processing_attempts + 1,
                    'last_processed_at' => now(),
                ]);

            $lookbackDays = (int) ($ic['lookback_days'] ?? 60);
            $mailbox = (string) ($email->to_email ?? '');
            $fromEmail = (string) ($email->from_email ?? '');

            // 1. Мягкий матч токена в теме/теле.
            $tokenMatch = (new MailboxTokenMatcher($lookbackDays))
                ->match($mailbox, (string) ($email->subject ?? ''), $email->body_text ?? null);

            // 2. Кандидат-заявки по домену/токену.
            $candidates = (new CandidateBatchLoader($lookbackDays, (int) ($ic['candidate_limit'] ?? 50)))
                ->load($mailbox, $fromEmail, $tokenMatch);

            $persister = new IdentifiedEmailPersister();

            if ($candidates === []) {
                $persister->persistNoCandidates($this->emailId);
                Log::info('IdentifyUnidentifiedEmailJob: no candidates', ['email_id' => $this->emailId]);
                return;
            }

            // 3. Контекст для AI: очищенное тело + текст вложений.
            $body = (new EmailBodyCleaner())->clean($email->body_text ?? null, $email->body_html ?? null);
            $documentText = (new DocumentTextExtractor((int) ($ic['doc_max_chars'] ?? 30000)))
                ->extractFromAttachments($this->loadAttachments());

            // 4. AI-идентификация.
            $decision = $this->makeAnalyzer($ic)->analyze(
                $fromEmail,
                $mailbox,
                $email->subject ?? null,
                $body,
                $documentText,
                $candidates,
                $tokenMatch,
            );

            // 5. Запись результата.
            if ($decision['validation_passed'] && $decision['identified_batch_id'] !== null) {
                // Опознано (в т.ч. запрос реквизитов): мигрируем в беседу — дальше
                // emails:analyze-replies извлечёт вопрос/оффер, emails:process-questions
                // при запросе реквизитов автоответит нашими данными организации.
                $persister->persistIdentified($email, $decision);
                Log::info('IdentifyUnidentifiedEmailJob: identified', [
                    'email_id' => $this->emailId,
                    'queue_id' => $decision['identified_queue_id'],
                    'batch_id' => $decision['identified_batch_id'],
                    'email_type' => $decision['email_type'],
                    'confidence' => $decision['confidence'],
                ]);
            } elseif ($decision['email_type'] === 'auto_reply') {
                // Автоответ/приветствие без действий → spam (не ручной разбор, не ретраим).
                $persister->persistSpam($this->emailId, $decision);
                Log::info('IdentifyUnidentifiedEmailJob: spam (auto_reply)', [
                    'email_id' => $this->emailId,
                ]);
            } else {
                // Осмысленный ответ, но не опознан (отказ/вопрос без совпадения) → ручной разбор.
                $persister->persistManualReview($this->emailId, $decision);
                Log::info('IdentifyUnidentifiedEmailJob: manual_review', [
                    'email_id' => $this->emailId,
                    'email_type' => $decision['email_type'],
                    'confidence' => $decision['confidence'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('IdentifyUnidentifiedEmailJob: failed', [
                'email_id' => $this->emailId,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Повторная проверка статуса внутри лока (claim): письмо ещё pending и не
     * исчерпало попытки. Возвращает строку unidentified_emails либо null.
     */
    private function claimEmail(int $maxAttempts): ?object
    {
        return DB::connection('reports')->table('unidentified_emails')
            ->where('id', $this->emailId)
            ->where('status', 'pending')
            ->where('processing_attempts', '<', $maxAttempts)
            ->first([
                'id',
                'from_email',
                'to_email',
                'subject',
                'body_text',
                'body_html',
                'message_id',
                'received_at',
                'processing_attempts',
            ]);
    }

    /**
     * Вложения письма для DocumentTextExtractor (читает с диска по local_path).
     *
     * @return array<int,object>
     */
    private function loadAttachments(): array
    {
        return DB::connection('reports')->table('unidentified_email_attachments')
            ->where('unidentified_email_id', $this->emailId)
            ->get(['file_name', 'mime_type', 'local_path'])
            ->all();
    }

    /**
     * @param array<string,mixed> $ic блок config('services.email_identify')
     */
    private function makeAnalyzer(array $ic): IdentificationAnalyzer
    {
        $cfg = config('services.openai_classifier');

        $client = new OpenAIClassifierClient(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            proxyKey: (string) ($cfg['proxy_key'] ?? ''),
            modelMini: (string) ($cfg['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($cfg['model_full'] ?? 'gpt-4o'),
            timeout: (int) ($ic['timeout'] ?? 120),
        );

        return new IdentificationAnalyzer(
            $client,
            (string) ($ic['model'] ?? 'gpt-4o'),
            (int) ($ic['max_tokens'] ?? 1024),
            (float) ($ic['min_confidence'] ?? 0.5),
        );
    }
}
