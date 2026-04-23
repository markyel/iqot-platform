<?php

namespace App\Jobs\Api;

use App\Models\Api\ApiClient;
use App\Models\Api\ApiInbox;
use App\Models\Api\ApiSubmission;
use App\Models\Api\ClientCategory;
use App\Models\Api\RequestItemStaging;
use App\Models\Api\RequestStaging;
use App\Models\BalanceHold;
use App\Services\Api\ClientCategoryClassifierService;
use App\Services\Api\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InboxProcessingWorker (спека §8).
 *
 * Алгоритм волны:
 *   1) Watchdog: возвращает `processing` c истёкшим locked_until в `pending` (retry++).
 *   2) Переводит в `failed` строки с retry_count >= max_retries.
 *   3) Забирает batch `pending` строк, ставит `processing`, `locked_until=+10min`.
 *   4) Для каждой строки: классифицирует позиции, создаёт request_staging + items,
 *      привязывает balance_holds к items, удаляет inbox-запись.
 *   5) Если остались `pending` — self-reschedule.
 *
 * Очередь: database (даже если глобально sync).
 */
class InboxProcessingWorker implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Идемпотентный lock timeout, чтобы watchdog мог «отжимать» зависшие. */
    public const LOCK_TTL_MINUTES = 10;
    public const MAX_RETRIES = 3;
    public const BATCH_LIMIT = 500;

    public $timeout = 300;
    public $tries = 1;              // retry мы ведём сами через retry_count в api_inbox

    public function __construct()
    {
        // Принудительно используем database-очередь вне зависимости от QUEUE_CONNECTION.
        $this->onConnection('database');
    }

    public function handle(ClientCategoryClassifierService $classifier): void
    {
        $this->runWatchdog();
        $this->markExceededAsFailed();
        $processed = $this->processBatch($classifier);

        if ($processed === 0) {
            return;
        }

        $remaining = ApiInbox::query()->where('status', 'pending')->count();
        if ($remaining > 0) {
            // Self-reschedule: закидываем следующую волну.
            self::dispatch();
        }
    }

    private function runWatchdog(): void
    {
        $affected = ApiInbox::query()
            ->where('status', 'processing')
            ->where('locked_until', '<', now())
            ->update([
                'status' => 'pending',
                'retry_count' => DB::raw('retry_count + 1'),
                'locked_until' => null,
            ]);
        if ($affected > 0) {
            Log::info('InboxProcessingWorker: watchdog released', ['count' => $affected]);
        }
    }

    private function markExceededAsFailed(): void
    {
        $affected = ApiInbox::query()
            ->where('status', 'pending')
            ->where('retry_count', '>=', self::MAX_RETRIES)
            ->update([
                'status' => 'failed',
                'last_error' => 'retry_exceeded',
            ]);
        if ($affected > 0) {
            Log::warning('InboxProcessingWorker: marked failed by retry limit', ['count' => $affected]);
        }
    }

    /**
     * @return int количество обработанных inbox-строк за эту волну
     */
    private function processBatch(ClientCategoryClassifierService $classifier): int
    {
        // Берём IDs одной транзакцией, чтобы избежать повторного локинга.
        $ids = DB::transaction(function () {
            $rows = ApiInbox::query()
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(self::BATCH_LIMIT)
                ->lockForUpdate()
                ->get(['id']);

            if ($rows->isEmpty()) {
                return [];
            }

            $ids = $rows->pluck('id')->all();
            ApiInbox::query()->whereIn('id', $ids)->update([
                'status' => 'processing',
                'locked_until' => now()->addMinutes(self::LOCK_TTL_MINUTES),
            ]);
            return $ids;
        });

        $processed = 0;
        foreach ($ids as $inboxId) {
            try {
                DB::transaction(function () use ($inboxId, $classifier) {
                    $this->processInboxRow($inboxId, $classifier);
                });
                $processed++;
            } catch (\Throwable $e) {
                Log::error('InboxProcessingWorker: row failed', [
                    'inbox_id' => $inboxId,
                    'error' => $e->getMessage(),
                ]);
                ApiInbox::query()->where('id', $inboxId)->update([
                    'status' => 'pending',
                    'locked_until' => null,
                    'last_error' => substr($e->getMessage(), 0, 1000),
                ]);
            }
        }
        return $processed;
    }

    private function processInboxRow(int $inboxId, ClientCategoryClassifierService $classifier): void
    {
        /** @var ApiInbox|null $inbox */
        $inbox = ApiInbox::query()->lockForUpdate()->find($inboxId);
        if (!$inbox || $inbox->status !== 'processing') {
            return;
        }

        $submission = ApiSubmission::query()->find($inbox->api_submission_id);
        if (!$submission) {
            throw new \RuntimeException('Submission not found for inbox row');
        }

        $payload = is_array($inbox->raw_payload) ? $inbox->raw_payload : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        // Создаём/находим request_staging.
        $staging = RequestStaging::firstOrCreate(
            ['api_submission_id' => $submission->id],
            ['stage' => 'awaiting_moderation']
        );

        // Индекс клиентских категорий по external_code для быстрого маппинга в staging item.
        $categoryIds = $this->clientCategoryIndex($submission->api_client_id, $items);

        // Собираем holds submission'а для попозиционной привязки.
        $holds = BalanceHold::query()
            ->where('api_submission_id', $submission->id)
            ->whereNull('request_items_staging_id')
            ->orderBy('id')
            ->get();

        // Индекс holds — очередь по порядку, каждый относится к очередной "платной" позиции.
        $holdsQueue = $holds->all();

        foreach ($items as $pos => $item) {
            $catCode = $item['client_category']['code'] ?? null;
            $clientCategoryId = $catCode ? ($categoryIds[$catCode] ?? null) : null;

            $classification = $classifier->classify($item, $clientCategoryId);

            /** @var RequestItemStaging $stagingItem */
            $stagingItem = RequestItemStaging::create([
                'request_staging_id' => $staging->id,
                'client_item_ref' => $item['client_ref'] ?? null,
                'position_number' => $pos + 1,
                'name' => (string) ($item['name'] ?? ''),
                'article' => $item['article'] ?? null,
                'brand' => $item['brand'] ?? null,
                'quantity' => (string) ($item['quantity'] ?? '0'),
                'unit' => (string) ($item['unit'] ?? ''),
                'description' => $item['description'] ?? null,
                'client_category_id' => $clientCategoryId,
                'product_type_id' => $classification['product_type_id'] ?? null,
                'domain_id' => $classification['domain_id'] ?? null,
                'type_confidence' => $classification['type_confidence'] ?? null,
                'domain_confidence' => $classification['domain_confidence'] ?? null,
                'classification_source' => $classification['classification_source'] ?? null,
                'needs_review' => (bool) ($classification['needs_review'] ?? true),
                'trust_level' => (string) ($classification['trust_level'] ?? 'red'),
                'item_status' => 'classified',
            ]);

            // Привязываем очередной hold (если есть) к этому staging item.
            /** @var BalanceHold|null $hold */
            $hold = array_shift($holdsQueue);
            if ($hold) {
                $hold->update([
                    'request_items_staging_id' => $stagingItem->id,
                ]);
                $stagingItem->update(['balance_hold_id' => $hold->id]);
            }
        }

        // Обновляем агрегаты submission и переводим в stage='awaiting_moderation'.
        $submission->update([
            'stage' => 'awaiting_moderation',
            'status' => 'processing',
            'status_changed_at' => now(),
        ]);

        // Удаляем inbox запись (после успешной классификации, §2.3).
        $inbox->delete();

        // Авто-приём green-позиций по флагу api_clients.auto_approve_green.
        // Если все позиции были green — submission будет финализирована автоматом
        // через ModerationService::maybeFinalize().
        $client = ApiClient::find($submission->api_client_id);
        if ($client && $client->auto_approve_green) {
            try {
                $approved = app(ModerationService::class)->approveGreenBatch($submission);
                if ($approved > 0) {
                    Log::info('InboxProcessingWorker: auto-approved green items', [
                        'submission_id' => $submission->id,
                        'api_client_id' => $client->id,
                        'approved' => $approved,
                    ]);
                }
            } catch (\Throwable $e) {
                // Авто-приём — best-effort: не валим обработку inbox-строки.
                Log::warning('InboxProcessingWorker: auto-approve failed', [
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Строит индекс external_code → client_category_id по коду клиента.
     *
     * @param int $apiClientId
     * @param array<int,array<string,mixed>> $items
     * @return array<string,int>
     */
    private function clientCategoryIndex(int $apiClientId, array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            $code = $item['client_category']['code'] ?? null;
            if (is_string($code) && $code !== '') {
                $codes[$code] = true;
            }
        }
        if (empty($codes)) {
            return [];
        }
        return ClientCategory::query()
            ->where('api_client_id', $apiClientId)
            ->whereIn('external_code', array_keys($codes))
            ->pluck('id', 'external_code')
            ->all();
    }
}
