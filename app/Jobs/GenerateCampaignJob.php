<?php

namespace App\Jobs;

use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignEmailBuilder;
use App\Services\Generate\CampaignItemGrouper;
use App\Services\Generate\CampaignPersister;
use App\Services\Generate\CampaignSenderAssigner;
use App\Services\Generate\CampaignSupplierSelector;
use App\Services\Generate\CampaignTokenGenerator;
use App\Services\Generate\SupplierTargetingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Оркестратор генерации рассылки над набором заявок, перехваченных командой
 * (порт воркфлоу n8n «Create Email Queue v4 (AI)» целиком, кроме триггера/клейма).
 *
 * Заявки уже переведены командой в queued_for_sending (claim — гарантия
 * идемпотентности). Джоб грузит позиции этих заявок (Get All Items), кросс-заявочно
 * бьёт их на батчи (CampaignItemGrouper), назначает ящики-отправители глобально за
 * прогон (CampaignSenderAssigner) и на каждый батч (per-batch try/catch): подбирает
 * профильных поставщиков (CampaignSupplierSelector), генерит токен по стилю
 * отправителя (CampaignTokenGenerator) и тело письма (CampaignBodyGenerator), рендерит
 * уникальный HTML на каждого поставщика (CampaignEmailBuilder) и пишет строки в
 * email_batches/email_queue/request_item_responses (CampaignPersister).
 *
 * Анти-фингерпринтинг: шаблон/тон/стиль токена привязаны к отправителю (per-sender),
 * поэтому письма разных отправителей не похожи, а стиль каждого стабилен между
 * рассылками. Никакого единого генератора.
 *
 * Идемпотентность: email_batches/email_queue INSERT'ы НЕ идемпотентны; страховка —
 * claim заявок командой. Внутри — Cache::lock на каждую заявку (анти-двойной-дисптач).
 * Частично упавший батч логируется и не валит остальные; заявка остаётся
 * queued_for_sending (повторно не возьмётся).
 */
class GenerateCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CONN = 'reports';

    public int $timeout = 180;

    public int $tries = 1;

    /**
     * @param array<int,int> $requestIds перехваченные командой id заявок
     */
    public function __construct(
        private readonly array $requestIds,
        private readonly bool $dryRun = false,
    ) {
        $this->onQueue('generate');
    }

    public function handle(): void
    {
        $requestIds = array_values(array_filter(array_map('intval', $this->requestIds), static fn ($id) => $id > 0));
        if ($requestIds === []) {
            return;
        }

        // Cache::lock на каждую заявку — анти-двойной-дисптач. Берём только реально
        // залоченные id; остальные обрабатывает конкурентный джоб.
        $locks = [];
        $lockedIds = [];
        foreach ($requestIds as $id) {
            $lock = Cache::lock("generate:req:{$id}", 170);
            if ($lock->get()) {
                $locks[] = $lock;
                $lockedIds[] = $id;
            }
        }

        if ($lockedIds === []) {
            return;
        }

        try {
            $items = $this->loadItems($lockedIds);
            if ($items === []) {
                Log::info('GenerateCampaignJob: нет позиций для заявок', ['request_ids' => $lockedIds]);
                $this->flipRequestStatus($lockedIds);
                return;
            }

            $useNewRouting = $this->loadUseNewRouting();
            $categories = $this->loadCategories();

            $cfg = config('services.email_generate');
            $client = $this->makeClient($cfg);

            $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) ($cfg['items_per_batch'] ?? 5));
            $batches = $grouper->group($items);

            if ($batches === []) {
                $this->flipRequestStatus($lockedIds);
                return;
            }

            // Назначение ящиков-отправителей глобально за прогон (round-robin).
            (new CampaignSenderAssigner())->assign($batches);

            $supplierSelector = new CampaignSupplierSelector();
            $tokenGenerator = new CampaignTokenGenerator(
                $client,
                (string) ($cfg['token_model'] ?? 'gpt-4o-mini'),
                (bool) ($cfg['token_use_ai'] ?? true),
            );
            $bodyGenerator = new CampaignBodyGenerator(
                $client,
                (string) ($cfg['body_model'] ?? 'gpt-4o'),
                (float) ($cfg['body_temperature'] ?? 0.7),
                (int) ($cfg['max_tokens'] ?? 1500),
            );
            $emailBuilder = new CampaignEmailBuilder();
            $persister = new CampaignPersister();

            $generated = 0;
            foreach ($batches as $batch) {
                try {
                    $this->processBatch(
                        $batch,
                        $supplierSelector,
                        $tokenGenerator,
                        $bodyGenerator,
                        $emailBuilder,
                        $persister,
                    );
                    $generated++;
                } catch (\Throwable $e) {
                    Log::error('GenerateCampaignJob: батч упал', [
                        'request_ids' => $batch->requestIds,
                        'category' => $batch->category,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                }
            }

            // Порт «Update Request Status»: заявки → queued_for_sending (не в dry-run).
            $this->flipRequestStatus($lockedIds);

            Log::info('GenerateCampaignJob: завершено', [
                'request_ids' => $lockedIds,
                'batches' => count($batches),
                'batches_ok' => $generated,
                'dry_run' => $this->dryRun,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateCampaignJob: фатально', [
                'request_ids' => $lockedIds,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Полный цикл одного батча.
     */
    private function processBatch(
        Batch $batch,
        CampaignSupplierSelector $supplierSelector,
        CampaignTokenGenerator $tokenGenerator,
        CampaignBodyGenerator $bodyGenerator,
        CampaignEmailBuilder $emailBuilder,
        CampaignPersister $persister,
    ): void {
        if (empty($batch->sender)) {
            Log::warning('GenerateCampaignJob: батч без отправителя, пропуск', [
                'request_ids' => $batch->requestIds,
            ]);
            return;
        }

        $suppliers = $supplierSelector->select($batch);
        if ($suppliers === []) {
            Log::info('GenerateCampaignJob: нет профильных поставщиков, пропуск батча', [
                'request_ids' => $batch->requestIds,
                'category' => $batch->category,
            ]);
            return;
        }

        // #4 предрассылочный таргетинг: ищем позиции в Яндексе, помечаем поставщиков
        // пула найденными ссылками (группа A) и отдаём новые домены в discovery.
        $this->applyTargeting($batch);

        $tokenGenerator->generate($batch);
        $bodyGenerator->generate($batch);

        $emails = [];
        foreach ($batch->suppliers as $supplier) {
            $emails[] = $emailBuilder->build($batch, $supplier);
        }

        $persister->persist($batch, $emails);
    }

    /**
     * #4 фаза 4a/4b: Яндекс-поиск позиций батча → группа A (сайт нашёлся) получает
     * found_urls для письма со ссылками-намёками; новые домены-кандидаты уходят в
     * фоновый discovery (анализ сайта + авто-добавление поставщика с таксономией).
     */
    private function applyTargeting(Batch $batch): void
    {
        if (!(bool) config('services.email_pretarget.enabled', false)) {
            return;
        }

        try {
            $res = SupplierTargetingService::make()->target($batch->items, $batch->supplierIds);
        } catch (\Throwable $e) {
            Log::warning('GenerateCampaignJob: targeting failed', ['error' => $e->getMessage()]);
            return;
        }

        // Группа A — прикрепляем найденные ссылки к записям поставщиков пула.
        if (!empty($res['groupA'])) {
            foreach ($batch->suppliers as $i => $sup) {
                $sid = (int) ($sup['id'] ?? 0);
                if ($sid > 0 && !empty($res['groupA'][$sid])) {
                    $batch->suppliers[$i]['found_urls'] = $res['groupA'][$sid];
                }
            }
        }

        // Discovery — новые домены в фон (только с известным product_type).
        if ((bool) config('services.email_pretarget.discovery_enabled', true) && !empty($res['candidates'])) {
            foreach ($res['candidates'] as $cand) {
                if (empty($cand['product_type_id'])) {
                    continue;
                }
                DiscoverFromCampaignJob::dispatch(
                    (string) $cand['url'],
                    (int) $cand['product_type_id'],
                    isset($cand['domain_id']) ? (int) $cand['domain_id'] : null,
                );
            }
        }

        Log::info('GenerateCampaignJob: targeting applied', [
            'group_a' => count($res['groupA'] ?? []),
            'candidates' => count($res['candidates'] ?? []),
            'searched_items' => $res['searched_items'] ?? 0,
        ]);
    }

    /**
     * Порт «Get All Items», ограниченный перехваченными заявками (без фильтра по
     * статусу — команда уже перевела их в queued_for_sending).
     *
     * @param array<int,int> $requestIds
     * @return array<int,array<string,mixed>>
     */
    private function loadItems(array $requestIds): array
    {
        $rows = DB::connection(self::CONN)->table('request_items as ri')
            ->join('requests as r', 'ri.request_id', '=', 'r.id')
            ->whereIn('r.id', $requestIds)
            ->orderBy('r.is_customer_request', 'desc')
            ->orderBy('ri.position_number', 'asc')
            ->orderBy('r.id', 'asc')
            ->orderBy('ri.id', 'asc')
            ->get([
                'ri.id',
                'ri.request_id',
                'ri.position_number',
                'ri.name',
                'ri.brand',
                'ri.article',
                'ri.quantity',
                'ri.unit',
                'ri.category',
                'ri.description',
                'ri.product_type_id',
                'ri.domain_id',
                'r.is_customer_request',
                'r.client_organization_id',
                'r.request_number',
                'r.customer_company',
                'r.customer_contact_person',
                'r.customer_email',
                'r.customer_phone',
            ]);

        return array_map(static fn ($row) => (array) $row, $rows->all());
    }

    /**
     * Порт «Load Migration Flags»: use_new_routing.
     */
    private function loadUseNewRouting(): bool
    {
        $value = DB::connection(self::CONN)->table('migration_flags')
            ->where('flag_name', 'use_new_routing')
            ->value('is_enabled');

        return (bool) $value;
    }

    /**
     * Порт «Load Categories».
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadCategories(): array
    {
        $rows = DB::connection(self::CONN)->table('categories')
            ->where('is_active', 1)
            ->get(['id', 'name', 'description', 'routing']);

        return array_map(static fn ($row) => (array) $row, $rows->all());
    }

    /**
     * Порт «Update Request Status»: заявки → queued_for_sending. В dry-run НЕ флипаем
     * (заявка остаётся повторно-прогоняемой для инспекции).
     *
     * @param array<int,int> $requestIds
     */
    private function flipRequestStatus(array $requestIds): void
    {
        if ($this->dryRun || $requestIds === []) {
            return;
        }

        DB::connection(self::CONN)->table('requests')
            ->whereIn('id', $requestIds)
            ->update([
                'status' => 'queued_for_sending',
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string,mixed> $cfg блок config('services.email_generate')
     */
    private function makeClient(array $cfg): OpenAIClassifierClient
    {
        $oc = config('services.openai_classifier');

        return new OpenAIClassifierClient(
            baseUrl: rtrim((string) ($oc['base_url'] ?? ''), '/'),
            apiKey: (string) ($oc['api_key'] ?? ''),
            proxyKey: (string) ($oc['proxy_key'] ?? ''),
            modelMini: (string) ($oc['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($oc['model_full'] ?? 'gpt-4o'),
            timeout: (int) ($cfg['timeout'] ?? 60),
        );
    }
}
