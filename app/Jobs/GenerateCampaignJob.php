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
use App\Services\Generate\RelatedItemsClusterer;
use App\Services\Generate\SenderDailyCapacity;
use App\Services\Generate\SupplierTargetingService;
use App\Services\Generate\WarmupBatchSplitter;
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

    /** Повтор = выпуск отсрочки по капасити (sender_capacity / ban_containment). */
    private bool $capacityRetry = false;

    /** Пин пула: генерить ТОЛЬКО этим поставщикам (отсрочка по капасити / контейнмент бана). */
    private ?array $onlySupplierIds = null;

    /** Волна пина (waves-v2): перегенерённые письма получают ЭТУ волну (В3 → held-резерв
     *  остаётся held, не превращается в немедленную В1). null → волна 1 (legacy). */
    private ?int $pinnedWave = null;

    /** Нарезка батча по остаткам дневных лимитов (Phase 3b, EMAILS_WARMUP_ENABLED). */
    private ?WarmupBatchSplitter $splitter = null;

    /** LLM-склейка родственных сирот к заявке-якорю (EMAILS_LOAD_CLUSTER_ENABLED). */
    private ?RelatedItemsClusterer $orphanClusterer = null;

    /** @var array<int,string> кэш названий доменов (application_domains). */
    private array $domainNameCache = [];

    /** @var array<int,string>|null кэш названий product_types. */
    private ?array $ptNames = null;

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
            $assigner = new CampaignSenderAssigner();
            $assigner->assign($batches);
            $this->splitter = new WarmupBatchSplitter($assigner, new SenderDailyCapacity());
            $this->orphanClusterer = $this->makeClusterer($client, $cfg);

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

        // Смежные категории: заявка-якорь притягивает родственные отложенные сироты
        // (LLM решает «с того же объекта»). До подбора поставщиков — чтобы объединённые
        // product_type попали в выборку пула. Только нормальный путь (не retry/пин/dry-run,
        // анонимные, NEW-routing). Сборка сирот ускоряет набор target и делает письмо
        // цельным RFQ на объект.
        $this->absorbRelatedOrphans($batch);

        $suppliers = $supplierSelector->select($batch);

        // Пин пула (повтор отсрочки по капасити / контейнмент бана): генерим ТОЛЬКО
        // тем поставщикам, чьи письма были отложены/сняты — без дублей уже отправленным.
        if ($this->onlySupplierIds !== null) {
            $this->pinSuppliers($batch);
            $suppliers = $batch->suppliers;
        }

        if ($suppliers === []) {
            Log::info('GenerateCampaignJob: нет профильных поставщиков, пропуск батча', [
                'request_ids' => $batch->requestIds,
                'category' => $batch->category,
                'pinned' => $this->onlySupplierIds !== null,
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

        // Waves-v2: делим пул на 3 волны по ТЕМПЕРАТУРЕ Яндекс-матча (tier1→горячая/сразу,
        // tier2→тёплая/+1день, tier3→холодная/held). До сплиттера — он режет по лимитам
        // только горячую волну 1 (tier2/tier3 держатся, лимит сегодня не расходуют).
        $this->classifyByTier($batch, $res);

        // «1 БАТЧ = 1 SENDER»: батч НЕ дробим по ящикам. Весь батч (все волны) генерим
        // с ОДНИМ назначенным отправителем (assigner round-robin). Дневной лимит держит
        // ДИСПЕТЧЕР: что не влезло в лимит ящика сегодня — остаётся pending и досылается
        // на след. день ТЕМ ЖЕ ящиком. Прежний WarmupBatchSplitter дробил волну-1 по
        // нескольким ящикам пропорц. остатку лимита → концентрировал объём на одном
        // высоколимитном ящике и ломал ротацию по получателю (поставщик получал пачку
        // писем с ОДНОГО ящика). Убрано ради «1 батч = 1 sender».
        $this->generateAndPersist($batch, $tokenGenerator, $bodyGenerator, $emailBuilder, $persister);
        return;
    }

    /**
     * Waves-v2: делит пул батча на 3 волны по «температуре» Яндекс-матча (результат
     * таргетинга $res: tier1/tier2). При выключенном флаге — no-op (деление по размеру
     * оставляет CampaignSupplierSelector). На входе $batch->suppliers = ВЕСЬ пул (select
     * при waves_v2 не режет). На выходе: suppliers=tier1 (горячие), expansionSuppliers=
     * tier2 (тёплые), coldSuppliers=tier3 (холодные, не совпали). found_urls (для
     * персонализации) навешиваются горячим и тёплым.
     */
    private function classifyByTier(Batch $batch, ?array $res): void
    {
        if (!(bool) config('services.email_pool.waves_v2', false) || $this->onlySupplierIds !== null) {
            return; // флаг off ИЛИ пин-набор (капасити/контейнмент) — деление не трогаем
        }

        $tier1 = array_flip(array_map('intval', (array) ($res['tier1'] ?? [])));
        $tier2 = array_flip(array_map('intval', (array) ($res['tier2'] ?? [])));
        $groupA = (array) ($res['groupA'] ?? []);

        $hot = [];
        $warm = [];
        $cold = [];
        foreach ($batch->suppliers as $sup) {
            $sid = (int) ($sup['id'] ?? 0);
            if ($sid > 0 && !empty($groupA[$sid])) {
                $sup['found_urls'] = $groupA[$sid];
            }
            if (isset($tier1[$sid])) {
                $hot[] = $sup;
            } elseif (isset($tier2[$sid])) {
                $warm[] = $sup;
            } else {
                $cold[] = $sup;
            }
        }

        $batch->suppliers = $hot;
        $batch->expansionSuppliers = $warm;
        $batch->coldSuppliers = $cold;

        Log::info('GenerateCampaignJob: waves-v2 тиры', [
            'request_ids' => $batch->requestIds,
            'hot' => count($hot), 'warm' => count($warm), 'cold' => count($cold),
        ]);
    }

    /**
     * Генерация и запись одного (под-)батча: токен и тело по стилю ЕГО отправителя,
     * per-supplier HTML, persist, затем discovery по кандидатам (нужен batch_id).
     */
    private function generateAndPersist(
        Batch $batch,
        CampaignTokenGenerator $tokenGenerator,
        CampaignBodyGenerator $bodyGenerator,
        CampaignEmailBuilder $emailBuilder,
        CampaignPersister $persister,
    ): void {
        $tokenGenerator->generate($batch);
        $bodyGenerator->generate($batch);

        // Волна 1 (сразу) + волна 2 (тёплые/расширение, +delay или held) + волна 3
        // (холодные, waves-v2, held до followup). Persister ставит scheduled_at по wave.
        // При пине (контейнмент/капасити) волну НЕ переклассифицируем — ставим сохранённую
        // (В3 остаётся held-резервом). Обычный путь — волна 1 (горячие/tier1).
        $suppliersWave = $this->onlySupplierIds !== null ? ($this->pinnedWave ?? 1) : 1;
        $emails = [];
        foreach ($batch->suppliers as $supplier) {
            $e = $emailBuilder->build($batch, $supplier);
            $e['wave'] = $suppliersWave;
            $emails[] = $e;
        }
        foreach ($batch->expansionSuppliers as $supplier) {
            $e = $emailBuilder->build($batch, $supplier);
            $e['wave'] = 2;
            $emails[] = $e;
        }
        foreach ($batch->coldSuppliers as $supplier) {
            $e = $emailBuilder->build($batch, $supplier);
            $e['wave'] = 3;
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
     * Оставить в батче только пин-набор поставщиков (only_supplier_ids отложенной
     * строки): повтор отсрочки по капасити / контейнмент бана шлёт только тем, кому
     * письмо было отложено или снято, — уже отправленным дублей не будет.
     */
    private function pinSuppliers(Batch $batch): void
    {
        $pin = array_flip(array_map('intval', $this->onlySupplierIds ?? []));

        $batch->suppliers = array_values(array_filter(
            $batch->suppliers,
            static fn ($s) => isset($pin[(int) ($s['id'] ?? 0)])
        ));
        $batch->expansionSuppliers = array_values(array_filter(
            $batch->expansionSuppliers,
            static fn ($s) => isset($pin[(int) ($s['id'] ?? 0)])
        ));
        $batch->supplierIds = [];
        foreach ($batch->suppliers as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0 && !in_array($id, $batch->supplierIds, true)) {
                $batch->supplierIds[] = $id;
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
            if ($this->deferredBatchId && !$this->loadRetry && !$this->capacityRetry) {
                // Повтор гейта качества: переиспользуем сохранённую Яндекс-выдачу (без
                // нового поиска), матчим с уже обогащённым пулом. Для recipient_load /
                // sender_capacity выдачи нет — таргетинг гоним СВЕЖИЙ (ветка else).
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
        // Waves-v2: гейт качества не нужен — «холодная» волна 3 (несовпавшие) держится и
        // релизится followup'ом при малом отклике, что и есть discovery-first по смыслу.
        if ($this->deferredBatchId || $this->dryRun
            || (bool) config('services.email_pool.waves_v2', false)
            || !(bool) config('services.email_pool.gate_enabled', false)) {
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

    /**
     * Отложить остаток батча по капасити (reason='sender_capacity',
     * status='accumulating'): на этих поставщиков не хватило остатков дневных лимитов
     * (или глобального потолка). Пин пула (only_supplier_ids) гарантирует, что повтор
     * (emails:process-capacity-deferred → retry) сгенерит письма ТОЛЬКО им — без
     * дублей уже обработанным. Позиции — все позиции батча (те же для всех адресатов).
     *
     * @param array<int,array<string,mixed>> $suppliers
     */
    private function deferBatchForCapacity(Batch $batch, array $suppliers, string $reason = 'sender_capacity', int $wave = 1): void
    {
        if ($this->dryRun) {
            return;
        }
        $supplierIds = [];
        foreach ($suppliers as $s) {
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0 && !in_array($id, $supplierIds, true)) {
                $supplierIds[] = $id;
            }
        }
        $itemIds = array_values(array_filter(array_map(static fn ($it) => (int) ($it['id'] ?? 0), $batch->items)));
        if ($supplierIds === [] || $itemIds === []) {
            return;
        }

        $deferredId = (int) DB::connection(self::CONN)->table('deferred_batches')->insertGetId([
            'request_ids' => json_encode($batch->requestIds, JSON_UNESCAPED_UNICODE),
            'item_ids' => json_encode($itemIds, JSON_UNESCAPED_UNICODE),
            'sender_id' => (int) ($batch->sender['id'] ?? 0) ?: null,
            'product_type_id' => !empty($batch->productTypeIds) ? (int) $batch->productTypeIds[0] : null,
            'domain_id' => !empty($batch->domainIds) ? (int) $batch->domainIds[0] : null,
            'only_supplier_ids' => json_encode($supplierIds, JSON_UNESCAPED_UNICODE),
            'wave' => $wave > 0 ? $wave : 1,
            'found_domains' => json_encode([], JSON_UNESCAPED_UNICODE),
            'candidates_total' => 0,
            'candidates_done' => 0,
            'status' => 'accumulating',
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('GenerateCampaignJob: batch deferred (capacity)', [
            'deferred_id' => $deferredId,
            'reason' => $reason,
            'suppliers' => count($supplierIds),
            'items' => count($itemIds),
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
     * Смежные категории: заявка-якорь притягивает родственные ОТЛОЖЕННЫЕ сироты того же
     * домена (LLM решает «с того же объекта»). Приклеенные позиции вливаются в батч
     * (items + itemsCount + requestIds ∪ + productTypeIds ∪ → шире пул поставщиков), а их
     * строки deferred_batches закрываются (accumulating→done). Сироты НЕ ждут друг друга —
     * цепляются к живым заявкам. За флагом; нормальный путь, анонимные, NEW-routing.
     */
    private function absorbRelatedOrphans(Batch $batch): void
    {
        if ($this->orphanClusterer === null || $this->dryRun || $this->deferredBatchId
            || $this->onlySupplierIds !== null || $batch->isCustomerRequest) {
            return;
        }
        $domainId = !empty($batch->domainIds) ? (int) $batch->domainIds[0] : 0;
        if ($domainId <= 0 || $batch->items === []) {
            return;
        }

        $orphanRows = DB::connection(self::CONN)->table('deferred_batches')
            ->where('reason', 'recipient_load')->where('status', 'accumulating')->where('domain_id', $domainId)
            ->get(['id', 'item_ids', 'request_ids']);
        if ($orphanRows->isEmpty()) {
            return;
        }

        // item_id → строки; исключаем позиции СВОИХ заявок (батч — это они и есть).
        $ownReq = array_flip(array_map('intval', $batch->requestIds));
        $itemToRow = [];
        $orphanItemIds = [];
        foreach ($orphanRows as $row) {
            $rReq = (array) (json_decode((string) $row->request_ids, true) ?: []);
            foreach ($rReq as $rq) {
                if (isset($ownReq[(int) $rq])) {
                    continue 2;
                }
            }
            foreach ((array) (json_decode((string) $row->item_ids, true) ?: []) as $iid) {
                $iid = (int) $iid;
                if ($iid > 0) { $orphanItemIds[] = $iid; $itemToRow[$iid][] = (int) $row->id; }
            }
        }
        $orphanItemIds = array_values(array_unique($orphanItemIds));
        if ($orphanItemIds === []) {
            return;
        }

        $typeNames = $this->productTypeNames();
        $cand = [];
        foreach ($this->loadItemsByIds($orphanItemIds) as $it) {
            $cand[] = ['id' => (int) $it['id'], 'name' => $it['name'] ?? '', 'brand' => $it['brand'] ?? '',
                'article' => $it['article'] ?? '', 'type' => $typeNames[(int) ($it['product_type_id'] ?? 0)] ?? ''];
        }
        if ($cand === []) {
            return;
        }
        $anchor = [];
        foreach ($batch->items as $it) {
            $anchor[] = ['name' => $it['name'] ?? '', 'brand' => $it['brand'] ?? '', 'article' => $it['article'] ?? '',
                'type' => $typeNames[(int) ($it['product_type_id'] ?? 0)] ?? ''];
        }

        $picked = $this->orphanClusterer->pick($anchor, $cand, $this->loadDomainName($domainId));
        if ($picked === []) {
            return;
        }

        // Строки-источники выбранных позиций — заклеймить (accumulating→done, анти-гонка).
        $rowsToClaim = [];
        foreach ($picked as $iid) {
            foreach ($itemToRow[$iid] ?? [] as $rid) {
                $rowsToClaim[$rid] = true;
            }
        }
        $claimed = [];
        foreach (array_keys($rowsToClaim) as $rid) {
            $ok = DB::connection(self::CONN)->table('deferred_batches')->where('id', $rid)->where('status', 'accumulating')
                ->update(['status' => 'done', 'reason' => 'recipient_load:clustered_into_new', 'updated_at' => now()]);
            if ($ok) {
                $claimed[] = $rid;
            }
        }
        if ($claimed === []) {
            return; // гонка — другой прогон забрал
        }

        // Позиции заклеймленных строк целиком (строки тонкие) → влить в батч.
        $absorbIds = [];
        foreach ($orphanRows as $row) {
            if (in_array((int) $row->id, $claimed, true)) {
                foreach ((array) (json_decode((string) $row->item_ids, true) ?: []) as $iid) {
                    $absorbIds[] = (int) $iid;
                }
            }
        }
        $absorbItems = $this->loadItemsByIds(array_values(array_unique(array_filter($absorbIds))));
        if ($absorbItems === []) {
            return;
        }

        $existing = array_flip(array_map(static fn ($x) => (int) ($x['id'] ?? 0), $batch->items));
        foreach ($absorbItems as $it) {
            $id = (int) $it['id'];
            if (!isset($existing[$id])) {
                $batch->items[] = $it;
                $existing[$id] = 1;
                $rq = (int) ($it['request_id'] ?? 0);
                if ($rq > 0 && !in_array($rq, $batch->requestIds, true)) $batch->requestIds[] = $rq;
                $pt = (int) ($it['product_type_id'] ?? 0);
                if ($pt > 0 && !in_array($pt, $batch->productTypeIds, true)) $batch->productTypeIds[] = $pt;
            }
        }
        $batch->itemsCount = count($batch->items);

        Log::info('GenerateCampaignJob: приклеены родственные сироты (смежные категории)', [
            'domain_id' => $domainId,
            'absorbed_items' => count($absorbItems),
            'claimed_rows' => $claimed,
            'items_count' => $batch->itemsCount,
            'product_types' => $batch->productTypeIds,
        ]);
    }

    /** @return array<int,string> product_type_id => name (кэш). */
    private function productTypeNames(): array
    {
        if ($this->ptNames === null) {
            $this->ptNames = DB::connection(self::CONN)->table('product_types')
                ->pluck('name', 'id')->map(static fn ($v) => (string) $v)->all();
        }
        return $this->ptNames;
    }

    private function loadDomainName(int $id): string
    {
        if (!array_key_exists($id, $this->domainNameCache)) {
            $this->domainNameCache[$id] = (string) (DB::connection(self::CONN)->table('application_domains')
                ->where('id', $id)->value('name') ?? '');
        }
        return $this->domainNameCache[$id];
    }

    /** @param array<string,mixed> $cfg */
    private function makeClusterer(OpenAIClassifierClient $client, array $cfg): ?RelatedItemsClusterer
    {
        if (!(bool) config('services.email_load_defer.cluster_enabled', false)) {
            return null;
        }
        $model = (string) config('services.email_load_defer.cluster_model', (string) ($cfg['token_model'] ?? 'gpt-4o-mini'));
        return new RelatedItemsClusterer($client, $model);
    }

    /**
     * Повтор отложенного батча (гейт качества, attempt 2): discovery уже обогатил пул,
     * переиспользуем сохранённую Яндекс-выдачу, генерим БЕЗ гейта.
     * Для reason='recipient_load' (loadRetry) — выпуск накопителя: свежий таргетинг,
     * строка уже заклеймлена командой process-load-deferred в 'processing'.
     * Для reason='sender_capacity'/'ban_containment' (capacityRetry) — выпуск отсрочки
     * по лимитам прогрева: свежий таргетинг, пин пула only_supplier_ids (письма только
     * отложенным/снятым адресатам), клейм командой process-capacity-deferred.
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
        $this->capacityRetry = in_array((string) $row->reason, ['sender_capacity', 'ban_containment'], true);

        if ($this->loadRetry || $this->capacityRetry) {
            // Строка уже заклеймлена командой (process-load-deferred /
            // process-capacity-deferred) в 'processing'.
            if ((string) $row->status !== 'processing') {
                return;
            }
            $this->retryFound = []; // выдачи нет — таргетинг пойдёт свежий
            if ($this->capacityRetry && !empty($row->only_supplier_ids)) {
                $pin = array_values(array_filter(array_map('intval', (array) (json_decode((string) $row->only_supplier_ids, true) ?: []))));
                $this->onlySupplierIds = $pin !== [] ? $pin : null;
                // Сохранённая волна пина: перегенерим той же волной (В3 остаётся held).
                $this->pinnedWave = isset($row->wave) && $row->wave !== null ? (int) $row->wave : null;
            }
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
            $assigner = new CampaignSenderAssigner();
            $assigner->assign($batches);
            $this->splitter = new WarmupBatchSplitter($assigner, new SenderDailyCapacity());
            $this->orphanClusterer = $this->makeClusterer($client, $cfg);

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
            // loadRetry/capacityRetry вернуть в 'accumulating' (иначе retry-deferred
            // подхватит как discovery).
            DB::connection(self::CONN)->table('deferred_batches')->where('id', $row->id)
                ->update(['status' => ($this->loadRetry || $this->capacityRetry) ? 'accumulating' : 'ready', 'updated_at' => now()]);
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
