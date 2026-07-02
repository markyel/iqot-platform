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
    /** Повтор отложенного батча (гейт качества): id из deferred_batches. */
    private ?int $deferredBatchId = null;

    /** Сохранённая Яндекс-выдача для повтора (matchPool без нового поиска). */
    private array $retryFound = [];

    /** Повтор = выпуск накопителя по загрузке (recipient_load) → таргетинг СВЕЖИЙ. */
    private bool $loadRetry = false;

    public function __construct(
        private readonly array $requestIds,
        private readonly bool $dryRun = false,
        ?int $deferredBatchId = null,
    ) {
        $this->deferredBatchId = $deferredBatchId;
        $this->onQueue('generate');
    }

    public function handle(): void
    {
        // Повтор отложенного батча — отдельная ветка, нормальный путь не трогает.
        if ($this->deferredBatchId) {
            $this->handleRetry();
            return;
        }

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
        $res = $this->applyTargeting($batch);

        // Гейт качества волны 1 (discovery-first): слабый пул / мало найденных →
        // откладываем батч, гоним discovery, повторим когда он готов (retry-deferred).
        if ($this->shouldDefer($batch, $res)) {
            $this->deferBatch($batch, $res);
            return;
        }

        // Накопительная отсрочка по загрузке получателей: тонкий анонимный батч в
        // перегруженный пул откладываем — копим однородные позиции до target.
        if ($this->shouldDeferForLoad($batch)) {
            $this->deferBatchForLoad($batch);
            return;
        }

        $tokenGenerator->generate($batch);
        $bodyGenerator->generate($batch);

        // Волна 1 (шлём сразу) + волна 2 (пул расширения, держится до досыла).
        $emails = [];
        foreach ($batch->suppliers as $supplier) {
            $e = $emailBuilder->build($batch, $supplier);
            $e['wave'] = 1;
            $emails[] = $e;
        }
        foreach ($batch->expansionSuppliers as $supplier) {
            $e = $emailBuilder->build($batch, $supplier);
            $e['wave'] = 2;
            $emails[] = $e;
        }

        $persister->persist($batch, $emails);

        // Discovery новых доменов — после persist (есть batch_id). Найденные
        // поставщики привяжутся к батчу (campaign_discoveries) и обогатят волну 2.
        if (!$this->dryRun && $batch->batchId && $batch->discoveryCandidates !== []) {
            $requestId = (int) ($batch->requestIds[0] ?? 0);
            foreach ($batch->discoveryCandidates as $cand) {
                DiscoverFromCampaignJob::dispatch(
                    (string) $cand['url'],
                    (int) $cand['product_type_id'],
                    isset($cand['domain_id']) ? (int) $cand['domain_id'] : null,
                    (int) $batch->batchId,
                    $requestId ?: null,
                );
            }
        }
    }

    /**
     * #4 фаза 4a/4b: Яндекс-поиск позиций батча → группа A (сайт нашёлся) получает
     * found_urls для письма со ссылками-намёками; новые домены-кандидаты уходят в
     * фоновый discovery (анализ сайта + авто-добавление поставщика с таксономией).
     */
    private function applyTargeting(Batch $batch): ?array
    {
        if (!(bool) config('services.email_pretarget.enabled', false)) {
            return null;
        }

        try {
            if ($this->deferredBatchId && !$this->loadRetry) {
                // Повтор гейта качества: переиспользуем сохранённую Яндекс-выдачу (без
                // нового поиска), матчим с уже обогащённым пулом. Для recipient_load
                // (loadRetry) выдачи нет — таргетинг гоним СВЕЖИЙ (ветка else).
                $svc = SupplierTargetingService::make();
                $res = $svc->matchPool($this->retryFound, $batch->supplierIds)
                    + ['found' => $this->retryFound, 'searched_items' => count($this->retryFound) > 0 ? 1 : 0];
            } else {
                $res = SupplierTargetingService::make()->target($batch->items, $batch->supplierIds);
            }
        } catch (\Throwable $e) {
            Log::warning('GenerateCampaignJob: targeting failed', ['error' => $e->getMessage()]);
            return null;
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

        // Discovery — новые домены с известным product_type. Диспатч ПОСЛЕ persist
        // (нужен batch_id) — складываем кандидатов на батч.
        if ((bool) config('services.email_pretarget.discovery_enabled', true) && !empty($res['candidates'])) {
            foreach ($res['candidates'] as $cand) {
                if (!empty($cand['product_type_id'])) {
                    $batch->discoveryCandidates[] = $cand;
                }
            }
        }

        Log::info('GenerateCampaignJob: targeting applied', [
            'group_a' => count($res['groupA'] ?? []),
            'candidates' => count($res['candidates'] ?? []),
            'searched_items' => $res['searched_items'] ?? 0,
        ]);

        return $res;
    }

    /**
     * Гейт качества волны 1: откладывать ли батч (слабый пул / мало найденных Яндексом).
     * Только при включённом гейте И реальном таргетинге (иначе нет данных о match-rate).
     *
     * @param array<string,mixed>|null $res результат таргетинга
     */
    private function shouldDefer(Batch $batch, ?array $res): bool
    {
        // Повтор (attempt 2) — гейт НЕ применяем (генерим без оглядки на порог).
        if ($this->deferredBatchId || $this->dryRun || !(bool) config('services.email_pool.gate_enabled', false)) {
            return false;
        }
        if (!is_array($res) || (int) ($res['searched_items'] ?? 0) === 0) {
            return false; // таргетинг не отработал — не по чему судить, не откладываем
        }

        $pool = count($batch->suppliers) + count($batch->expansionSuppliers);
        if ($pool === 0) {
            return false;
        }
        $groupA = count($res['groupA'] ?? []);
        $rate = (int) round(100 * $groupA / $pool);

        $minPool = (int) config('services.email_pool.gate_min_pool', 30);
        $minRate = (int) config('services.email_pool.gate_min_match_rate', 25);

        return $pool < $minPool || $rate < $minRate;
    }

    /**
     * Отложить батч: сохранить в deferred_batches (с найденными доменами для повтора),
     * запустить discovery по кандидатам. Повтор сделает emails:retry-deferred, когда
     * discovery готов, уже без гейта.
     *
     * @param array<string,mixed> $res
     */
    private function deferBatch(Batch $batch, array $res): void
    {
        $itemIds = array_values(array_filter(array_map(static fn ($it) => (int) ($it['id'] ?? 0), $batch->items)));
        $candidates = [];
        foreach (($res['candidates'] ?? []) as $cand) {
            if (!empty($cand['product_type_id'])) {
                $candidates[] = $cand;
            }
        }
        $total = count($candidates);
        $pool = count($batch->suppliers) + count($batch->expansionSuppliers);

        $deferredId = (int) DB::connection(self::CONN)->table('deferred_batches')->insertGetId([
            'request_ids' => json_encode($batch->requestIds, JSON_UNESCAPED_UNICODE),
            'item_ids' => json_encode($itemIds, JSON_UNESCAPED_UNICODE),
            'sender_id' => (int) ($batch->sender['id'] ?? 0) ?: null,
            'found_domains' => json_encode($res['found'] ?? [], JSON_UNESCAPED_UNICODE),
            'candidates_total' => $total,
            'candidates_done' => 0,
            // Нет кандидатов на discovery → сразу готов к повтору.
            'status' => $total === 0 ? 'ready' : 'pending',
            'reason' => 'pool=' . $pool . ' groupA=' . count($res['groupA'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Discovery по кандидатам — с deferredId (счётчик готовности).
        foreach ($candidates as $cand) {
            DiscoverFromCampaignJob::dispatch(
                (string) $cand['url'],
                (int) $cand['product_type_id'],
                isset($cand['domain_id']) ? (int) $cand['domain_id'] : null,
                null,
                (int) ($batch->requestIds[0] ?? 0) ?: null,
                $deferredId,
            );
        }

        Log::info('GenerateCampaignJob: batch deferred (quality gate)', [
            'deferred_id' => $deferredId,
            'pool' => $pool,
            'group_a' => count($res['groupA'] ?? []),
            'candidates' => $total,
            'items' => count($itemIds),
        ]);
    }

    /**
     * Откладывать ли анонимный батч по загрузке получателей: тонкий (< target позиций)
     * И пул заметно перегружен (доля адресатов с pending >= loaded_pending превышает
     * loaded_fraction_pct%). Именные заявки НЕ откладываем. В retry-режиме — тоже нет.
     */
    private function shouldDeferForLoad(Batch $batch): bool
    {
        if ($this->deferredBatchId || $this->dryRun) {
            return false;
        }
        if ($batch->isCustomerRequest) {
            return false; // именные — клиентский приоритет, шлём сразу
        }
        if (!(bool) config('services.email_load_defer.enabled', false)) {
            return false;
        }
        $target = max(1, (int) config('services.email_load_defer.target_items', 3));
        if ((int) $batch->itemsCount >= $target) {
            return false; // батч уже «полный» — не откладываем
        }

        $emails = $this->poolEmails($batch->suppliers);
        if ($emails === []) {
            return false;
        }
        $loaded = $this->countLoadedRecipients($emails);
        $fraction = 100 * $loaded / count($emails);
        $minFraction = (int) config('services.email_load_defer.loaded_fraction_pct', 10);

        return $fraction > $minFraction;
    }

    /**
     * Отложить анонимный батч как накопитель по загрузке (reason='recipient_load',
     * status='accumulating'). Позже emails:process-load-deferred сгруппирует накопители
     * по (product_type_id, domain_id) и выпустит, когда набралось target / пул разгружен /
     * истёк max_hold.
     */
    private function deferBatchForLoad(Batch $batch): void
    {
        $itemIds = array_values(array_filter(array_map(static fn ($it) => (int) ($it['id'] ?? 0), $batch->items)));
        if ($itemIds === []) {
            return;
        }
        $ptid = !empty($batch->productTypeIds) ? (int) $batch->productTypeIds[0] : null;
        $did = !empty($batch->domainIds) ? (int) $batch->domainIds[0] : null;

        $deferredId = (int) DB::connection(self::CONN)->table('deferred_batches')->insertGetId([
            'request_ids' => json_encode($batch->requestIds, JSON_UNESCAPED_UNICODE),
            'item_ids' => json_encode($itemIds, JSON_UNESCAPED_UNICODE),
            'sender_id' => (int) ($batch->sender['id'] ?? 0) ?: null,
            'product_type_id' => $ptid,
            'domain_id' => $did,
            'found_domains' => json_encode([], JSON_UNESCAPED_UNICODE),
            'candidates_total' => 0,
            'candidates_done' => 0,
            'status' => 'accumulating',
            'reason' => 'recipient_load',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('GenerateCampaignJob: batch deferred (recipient_load)', [
            'deferred_id' => $deferredId,
            'items' => count($itemIds),
            'product_type_id' => $ptid,
            'domain_id' => $did,
        ]);
    }

    /** Уникальные нормализованные email пула поставщиков. @param array<int,array<string,mixed>> $suppliers @return array<int,string> */
    private function poolEmails(array $suppliers): array
    {
        $emails = [];
        foreach ($suppliers as $s) {
            $e = mb_strtolower(trim((string) ($s['email'] ?? '')));
            if ($e !== '' && !in_array($e, $emails, true)) {
                $emails[] = $e;
            }
        }
        return $emails;
    }

    /** Сколько адресатов из пула «загружены» (pending >= loaded_pending). @param array<int,string> $emails */
    private function countLoadedRecipients(array $emails): int
    {
        $threshold = max(1, (int) config('services.email_load_defer.loaded_pending', 10));
        return DB::connection(self::CONN)->table('email_queue')
            ->whereIn('to_email', $emails)
            ->where('status', 'pending')
            ->groupBy('to_email')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get(['to_email'])
            ->count();
    }

    /**
     * Повтор отложенного батча (гейт качества, attempt 2): discovery уже обогатил пул,
     * переиспользуем сохранённую Яндекс-выдачу, генерим БЕЗ гейта.
     * Для reason='recipient_load' (loadRetry) — выпуск накопителя: свежий таргетинг,
     * строка уже заклеймлена командой process-load-deferred в 'processing'.
     */
    private function handleRetry(): void
    {
        $row = DB::connection(self::CONN)->table('deferred_batches')
            ->where('id', $this->deferredBatchId)
            ->first();
        if (!$row) {
            return;
        }

        $this->loadRetry = ((string) $row->reason === 'recipient_load');

        if ($this->loadRetry) {
            // Накопитель уже заклеймлен командой process-load-deferred в 'processing'.
            if ((string) $row->status !== 'processing') {
                return;
            }
            $this->retryFound = []; // выдачи нет — таргетинг пойдёт свежий
        } else {
            // Гейт качества: клеймим ready→processing (повторный тик не возьмёт тот же).
            if ((string) $row->status !== 'ready') {
                return;
            }
            $claimed = DB::connection(self::CONN)->table('deferred_batches')
                ->where('id', $row->id)->where('status', 'ready')
                ->update(['status' => 'processing', 'updated_at' => now()]);
            if ($claimed === 0) {
                return;
            }
            $this->retryFound = json_decode((string) $row->found_domains, true) ?: [];
        }

        try {
            $itemIds = json_decode((string) $row->item_ids, true) ?: [];
            $items = $this->loadItemsByIds(array_map('intval', $itemIds));
            if ($items === []) {
                DB::connection(self::CONN)->table('deferred_batches')->where('id', $row->id)->update(['status' => 'done', 'updated_at' => now()]);
                return;
            }

            $useNewRouting = $this->loadUseNewRouting();
            $categories = $this->loadCategories();
            $cfg = config('services.email_generate');
            $client = $this->makeClient($cfg);

            $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) ($cfg['items_per_batch'] ?? 5));
            $batches = $grouper->group($items);
            (new CampaignSenderAssigner())->assign($batches);

            $supplierSelector = new CampaignSupplierSelector();
            $tokenGenerator = new CampaignTokenGenerator($client, (string) ($cfg['token_model'] ?? 'gpt-4o-mini'), (bool) ($cfg['token_use_ai'] ?? true));
            $bodyGenerator = new CampaignBodyGenerator($client, (string) ($cfg['body_model'] ?? 'gpt-4o'), (float) ($cfg['body_temperature'] ?? 0.7), (int) ($cfg['max_tokens'] ?? 1500));
            $emailBuilder = new CampaignEmailBuilder();
            $persister = new CampaignPersister();

            $ok = 0;
            foreach ($batches as $batch) {
                try {
                    $this->processBatch($batch, $supplierSelector, $tokenGenerator, $bodyGenerator, $emailBuilder, $persister);
                    $ok++;
                } catch (\Throwable $e) {
                    Log::error('GenerateCampaignJob(retry): батч упал', ['deferred_id' => $row->id, 'error' => mb_substr($e->getMessage(), 0, 400)]);
                }
            }

            DB::connection(self::CONN)->table('deferred_batches')->where('id', $row->id)->update(['status' => 'done', 'updated_at' => now()]);
            Log::info('GenerateCampaignJob: deferred retried', ['deferred_id' => $row->id, 'batches' => count($batches), 'ok' => $ok]);
        } catch (\Throwable $e) {
            // loadRetry вернуть в 'accumulating' (иначе retry-deferred подхватит как discovery).
            DB::connection(self::CONN)->table('deferred_batches')->where('id', $row->id)
                ->update(['status' => $this->loadRetry ? 'accumulating' : 'ready', 'updated_at' => now()]);
            Log::error('GenerateCampaignJob(retry): фатально', ['deferred_id' => $row->id, 'error' => mb_substr($e->getMessage(), 0, 400)]);
        }
    }

    /** Позиции по списку id (для повтора отложенного батча). @param array<int,int> $ids */
    private function loadItemsByIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn ($v) => $v > 0));
        if ($ids === []) {
            return [];
        }
        $reqIds = DB::connection(self::CONN)->table('request_items')->whereIn('id', $ids)->distinct()->pluck('request_id')->map(fn ($v) => (int) $v)->all();

        return $this->loadItems($reqIds, $ids);
    }

    /**
     * Порт «Get All Items», ограниченный перехваченными заявками (без фильтра по
     * статусу — команда уже перевела их в queued_for_sending).
     *
     * @param array<int,int> $requestIds
     * @param array<int,int>|null $onlyItemIds ограничить набором позиций (повтор отложенного)
     * @return array<int,array<string,mixed>>
     */
    private function loadItems(array $requestIds, ?array $onlyItemIds = null): array
    {
        $rows = DB::connection(self::CONN)->table('request_items as ri')
            ->join('requests as r', 'ri.request_id', '=', 'r.id')
            ->whereIn('r.id', $requestIds)
            ->when($onlyItemIds !== null, fn ($q) => $q->whereIn('ri.id', $onlyItemIds))
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
