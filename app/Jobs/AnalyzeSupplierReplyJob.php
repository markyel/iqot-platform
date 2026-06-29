<?php

namespace App\Jobs;

use App\Services\Analysis\DocumentTextExtractor;
use App\Services\Analysis\EmailBodyCleaner;
use App\Services\Analysis\HeadlessPageRenderer;
use App\Services\Analysis\SupplierReplyAnalyzer;
use App\Services\Analysis\SupplierReplyPersister;
use App\Services\Analysis\WebPageFetcher;
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
 * AI-анализ одного входящего письма поставщика — порт ветки n8n «Process Email
 * Conversations» (Get Unprocessed → AI Agent → Save*).
 *
 * Собирает контекст (письмо, диалог, поставщик, батч, запрошенные позиции, вложения),
 * чистит тело (EmailBodyCleaner), извлекает текст КП локально (DocumentTextExtractor),
 * прогоняет через AI (SupplierReplyAnalyzer, 2-шаговый сёрфинг по ссылкам) и пишет
 * офферы/вопросы/классификацию (SupplierReplyPersister).
 *
 * Идемпотентность: вставки multi/questions НЕ идемпотентны на уровне БД, поэтому
 * двойную обработку отсекаем Cache::lock + повторной проверкой ai_processed=0.
 */
class AnalyzeSupplierReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(private readonly int $messageId)
    {
        $this->onQueue('analyze');
    }

    public function handle(): void
    {
        $lock = Cache::lock("analyze:msg:{$this->messageId}", 170);
        if (!$lock->get()) {
            return;
        }

        try {
            $message = $this->loadMessage();
            if ($message === null) {
                return; // уже обработано или не подходит под условия
            }

            $batchId = (int) $message->batch_id;
            $attachments = $this->loadAttachments();
            $items = $this->loadRequestItems($batchId);

            $bodyCleaner = new EmailBodyCleaner();
            $body = $bodyCleaner->clean($message->body_text ?? null, $message->body_html ?? null);

            $ec = config('services.email_analysis');
            $documentText = (new DocumentTextExtractor((int) ($ec['doc_max_chars'] ?? 30000)))
                ->extractFromAttachments($attachments);

            $analyzer = $this->makeAnalyzer($ec);

            $classification = $analyzer->analyze([
                'sender_name' => $message->supplier_name ?? null,
                'sender_email' => $message->from_email ?? null,
                'subject' => $message->subject ?? null,
                'body' => $body,
                'document_text' => $documentText,
                'items' => $items,
                'has_documents' => $attachments !== [],
            ]);

            (new SupplierReplyPersister())->persist([
                'message_id' => (int) $message->message_id,
                'conversation_id' => (int) $message->conversation_id,
                'supplier_id' => (int) $message->supplier_id,
                'batch_id' => $batchId,
            ], $classification);

            // Негативный сигнал покрытия при отказе: накопление → авто-снятие категории.
            if (($classification['email_type'] ?? '') === 'rejection') {
                (new \App\Services\Analysis\SupplierCategorySignalService())->recordRejection(
                    (int) $message->supplier_id,
                    $batchId,
                    $classification['rejection_reason'] ?? null
                );
            }

            Log::info('AnalyzeSupplierReplyJob: processed', [
                'message_id' => $this->messageId,
                'email_type' => $classification['email_type'] ?? null,
                'offers' => count($classification['offers'] ?? []),
                'questions' => count($classification['questions'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('AnalyzeSupplierReplyJob: failed', [
                'message_id' => $this->messageId,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Порт «Get Unprocessed Messages», но точечно по id + повторная проверка
     * ai_processed=0 внутри лока (claim).
     */
    private function loadMessage(): ?object
    {
        return DB::connection('reports')->table('email_messages as em')
            ->join('email_conversations as ec', 'em.conversation_id', '=', 'ec.id')
            ->join('suppliers as s', 'ec.supplier_id', '=', 's.id')
            ->where('em.id', $this->messageId)
            ->where('em.direction', 'incoming')
            ->where('em.ai_processed', 0)
            ->whereNotIn('ec.status', ['complete', 'rejected', 'no_response'])
            ->first([
                'em.id as message_id',
                'em.conversation_id',
                'em.subject',
                'em.body_text',
                'em.body_html',
                'em.from_email',
                'ec.batch_id',
                'ec.supplier_id',
                's.name as supplier_name',
            ]);
    }

    /**
     * Порт «Get Attachments». Возвращает строки для DocumentTextExtractor.
     *
     * @return array<int,object>
     */
    private function loadAttachments(): array
    {
        return DB::connection('reports')->table('email_attachments')
            ->where('email_message_id', $this->messageId)
            ->get(['file_name', 'mime_type', 'local_path'])
            ->all();
    }

    /**
     * Порт «Get Batch Data» + «Get Request Items Details»: request_items батча —
     * JSON-массив id; тянем детали позиций в исходном порядке.
     *
     * @return array<int,object>
     */
    private function loadRequestItems(int $batchId): array
    {
        $batch = DB::connection('reports')->table('email_batches')
            ->where('id', $batchId)
            ->value('request_items');

        $ids = [];
        if (is_string($batch) && $batch !== '') {
            $decoded = json_decode($batch, true);
            if (is_array($decoded)) {
                $ids = array_values(array_filter(array_map('intval', $decoded), static fn ($v) => $v > 0));
            }
        }

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return DB::connection('reports')->table('request_items')
            ->whereIn('id', $ids)
            ->orderByRaw("FIELD(id, {$placeholders})", $ids)
            ->get(['id as item_id', 'position_number', 'name', 'brand', 'article', 'quantity', 'unit', 'category', 'description'])
            ->all();
    }

    /**
     * @param array<string,mixed> $ec блок config('services.email_analysis')
     */
    private function makeAnalyzer(array $ec): SupplierReplyAnalyzer
    {
        $cfg = config('services.openai_classifier');

        $client = new OpenAIClassifierClient(
            baseUrl: rtrim((string) ($cfg['base_url'] ?? ''), '/'),
            apiKey: (string) ($cfg['api_key'] ?? ''),
            proxyKey: (string) ($cfg['proxy_key'] ?? ''),
            modelMini: (string) ($cfg['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($cfg['model_full'] ?? 'gpt-4o'),
            timeout: (int) ($ec['timeout'] ?? 120),
        );

        $headless = ($ec['headless_enabled'] ?? true)
            ? new HeadlessPageRenderer(
                (string) ($ec['headless_chrome_path'] ?? '/usr/bin/google-chrome-stable'),
                (string) ($ec['headless_home'] ?? ''),
                (int) ($ec['headless_timeout'] ?? 30),
            )
            : null;

        $fetcher = new WebPageFetcher(
            (int) ($ec['fetch_chars'] ?? 8000),
            (int) ($ec['fetch_timeout'] ?? 15),
            $headless,
            (int) ($ec['http_min_chars'] ?? 200),
        );

        return new SupplierReplyAnalyzer($client, $fetcher, [
            'model' => (string) ($ec['model'] ?? 'gpt-4o'),
            'max_tokens' => (int) ($ec['max_tokens'] ?? 4096),
            'fetch_urls' => (bool) ($ec['fetch_urls'] ?? true),
            'fetch_max' => (int) ($ec['fetch_max'] ?? 3),
        ]);
    }
}
