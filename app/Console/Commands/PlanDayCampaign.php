<?php

namespace App\Console\Commands;

use App\Support\Senders\SenderPool;
use App\Jobs\DiscoverFromCampaignJob;
use App\Services\AliasExp\AliasBranch;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\Batch;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignEmailBuilder;
use App\Services\Generate\CampaignItemGrouper;
use App\Services\Generate\CampaignPersister;
use App\Services\Generate\CampaignSenderAssigner;
use App\Services\Generate\CampaignSupplierSelector;
use App\Services\Generate\CampaignTokenGenerator;
use App\Services\Generate\DayPlanAssigner;
use App\Services\Generate\PositionAffinity;
use App\Services\Generate\PositionCoverage;
use App\Services\Generate\SupplierTargetingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Фаза 2 (v2), утренний прогон: строит и рендерит ДНЕВНОЙ ПЛАН рассылки.
 *
 * Задача РАСПРЕДЕЛЕНИЯ: два набора — позиции (остаточный пул = таксономия минус «уже
 * слали эту позицию», релевантность из Яндекса ПО ВСЕМ позициям с кэшем, AI-совмести-
 * мость, срочность) × поставщики (дневной лимит «конвертов» на получателя, деф. 10).
 * DayPlanAssigner раскладывает позиции по конвертам раундами (равномерное покрытие,
 * релевантные вперёд, подсадка в открытые конверты); позиция не ограничивается
 * искусственно — только исчерпание её пула либо ёмкости дня (хвост уйдёт завтра).
 *
 * Рендер: письма плана группируются по (sender_id, набор позиций) — группа = один
 * батч (1 ящик, 1 тело AI, N поставщиков с одинаковым набором позиций) → token/body/
 * EmailBuilder(wave=1)/Persister → email_queue. Дедуп — request_item_responses
 * (persister пишет позицию×поставщика). --dry-run строит и показывает план без
 * записи. За флагом EMAILS_DAYPLAN_ENABLED.
 */
class PlanDayCampaign extends Command
{
    private const CONN = 'reports';
    private const HELD = '2037-01-01 00:00:00';

    protected $signature = 'emails:plan-day
        {--dry-run : Построить и показать план без записи}
        {--no-yandex : Без Яндекс-релевантности (быстрый прогон)}
        {--request= : Только эта заявка (тест)}
        {--limit=0 : Потолок писем за прогон, 0 = весь план (тест)}
        {--hold : (тест) сразу после persist уводить письма в held — диспетчер не заберёт}
        {--alias-only : (тест) только ветка «ящик на батч», без основного плана}
        {--alias-items= : (тест) в ветку «ящик на батч» — только эти позиции (через запятую), без проверки доли}
        {--alias-limit= : Потолок батчей ветки «ящик на батч» за прогон (по умолчанию — дневной бюджет ящиков)}
        {--alias-force : (ручной запуск) в ветку и позиции вне доли и уже разосланные части пула — письма только остаточному пулу (≥ --alias-min-pool)}
        {--alias-min-pool=20 : С --alias-force — минимум ещё не опрошенных поставщиков у позиции}
        {--force : Запустить при выключенном флаге}';

    protected $description = 'Фаза 2: построить дневной план рассылки (позиции→пулы→Яндекс→аффинность→назначатель)';

    public function handle(): int
    {
        if (!$this->option('force') && !(bool) config('services.email_planner.dayplan_enabled', false)) {
            $this->warn('Дневной планировщик выключен (EMAILS_DAYPLAN_ENABLED=false). --force для прогона.');
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');

        $coverage = new PositionCoverage();
        $defaultTarget = (int) config('services.email_planner.offer_target_default', 4);
        $maxPerEmail = (int) config('services.email_planner.dayplan_max_per_email', 4);
        $freshDays = max(1, (int) config('services.email_planner.fresh_days', 7));

        // 1. Активные позиции по открытым свежим заявкам. --request — точечный тест
        // одной заявки (вне статуса/свежести).
        $reqQuery = DB::connection(self::CONN)->table('requests');
        $freshSince = now()->subDays($freshDays);
        $only = (int) $this->option('request');
        if ($only) {
            $reqQuery->where('id', $only);
        } else {
            // Окно свежести — по позициям (частичный промоушен API дописывает позиции
            // в уже созданную заявку), см. PositionCoverage::freshItemIds.
            $reqQuery->whereIn('status', ['draft', 'new', 'active', 'queued_for_sending', 'emails_sent', 'responses_received']);
            // Техническая заявка мониторинга (Ф3) — адресные проверочные письма, не пул.
            $reqQuery->where('source', '!=', 'monitoring');
            PositionCoverage::whereHasFreshItems($reqQuery, $freshSince);
        }
        $requests = $reqQuery->get(['id', 'offer_target', 'max_reach', 'is_customer_request', 'created_at']);

        $positions = [];   // itemId => мета
        $itemRows = [];    // itemId => строка позиции
        foreach ($requests as $r) {
            $target = $r->offer_target !== null ? (int) $r->offer_target : $defaultTarget;
            $maxReach = (bool) $r->max_reach;
            $active = $coverage->activeItemIds((int) $r->id, $target, $maxReach);
            if (!$only) {
                $active = $coverage->freshItemIds($active, $freshSince);
            }
            if ($active === []) {
                continue;
            }
            foreach ($this->loadItems((int) $r->id, $active) as $it) {
                $id = (int) $it['id'];
                $itemRows[$id] = $it;
                $positions[$id] = [
                    'request_id' => (int) $r->id,
                    'target' => $target,
                    'max_reach' => $maxReach,
                    'is_customer' => (int) ($it['is_customer_request'] ?? 0) === 1,
                    'created' => (string) $r->created_at,
                    'urgency' => 0.0,
                ];
            }
        }
        if ($positions === []) {
            $this->info('Активных позиций нет.');
            return self::SUCCESS;
        }

        // Срочность = дефицит*10 + клиентское*100 + возраст(дни).
        $priced = $coverage->pricedSupplierCounts(array_keys($positions));
        foreach ($positions as $id => &$m) {
            $deficit = max(0, (int) $m['target'] - (int) ($priced[$id] ?? 0));
            $ageDays = (int) floor((now()->timestamp - strtotime((string) $m['created'])) / 86400);
            $m['urgency'] = $deficit * 10 + ($m['is_customer'] ? 100 : 0) + $ageDays;
        }
        unset($m);

        // 2. Пулы на позицию: grouper по type/domain → selector (пул определяется
        // таксономией) → минус уже писавшие ПО ЭТОЙ позиции = остаточный пул.
        $categories = $this->loadCategories();
        $useNewRouting = (bool) DB::connection(self::CONN)->table('migration_flags')->where('flag_name', 'use_new_routing')->value('is_enabled');
        $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) config('services.email_generate.items_per_batch', 5));
        $selector = new CampaignSupplierSelector();

        $poolMap = [];        // itemId => [supplierId] (остаточный пул)
        $emailedCount = [];   // itemId => скольким поставщикам уже писали (ветка «ящик на батч» берёт только 0)
        $supplierEmail = [];  // supplierId => email

        foreach ($grouper->group(array_values($itemRows)) as $batch) {
            $suppliers = $selector->select($batch);
            $supIds = [];
            foreach ($suppliers as $s) {
                $sid = (int) ($s['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $supIds[] = $sid;
                $supplierEmail[$sid] = mb_strtolower(trim((string) ($s['email'] ?? '')));
            }
            foreach ($batch->items as $it) {
                $iid = (int) ($it['id'] ?? 0);
                if ($iid <= 0 || !isset($positions[$iid])) {
                    continue;
                }
                $emailed = $coverage->emailedSuppliersPerItem([$iid])[$iid] ?? [];
                $emailedCount[$iid] = count($emailed);
                $poolMap[$iid] = $coverage->remainingPool($supIds, $emailed);
            }
        }

        // 2b. Яндекс-релевантность: по ВСЕМ позициям (стадия планирования!), с кэшем
        // найденных доменов на позицию — повторные утра не дёргают Яндекс по той же
        // позиции. Матч кэшированных доменов с ТЕКУЩИМ остаточным пулом — всегда свежий.
        $yandexOn = !$this->option('no-yandex')
            && (bool) config('services.email_planner.dayplan_yandex', true)
            && (bool) config('services.email_pretarget.enabled', false);
        $relevantMap = [];    // itemId => [supplierId] ⊆ пула
        $discoveryCands = []; // host => {url, product_type_id, domain_id, request_id}
        $yandexQueried = 0;
        $yandexCached = 0;
        if ($yandexOn) {
            $svc = SupplierTargetingService::make();
            $ttlDays = max(0, (int) config('services.email_planner.dayplan_yandex_cache_days', 3));
            $failStreak = 0;
            foreach ($itemRows as $iid => $it) {
                $poolIds = $poolMap[$iid] ?? [];
                if ($poolIds === [] || $failStreak >= 3) {
                    continue;
                }
                $key = 'dayplan:yx:item:' . $iid;
                $found = $ttlDays > 0 ? Cache::get($key) : null;
                if (!is_array($found)) {
                    try {
                        $searched = 0;
                        $found = $svc->searchItems([$it], $searched);
                        $yandexQueried++;
                        $failStreak = 0;
                        // Кэшируем только реальный поиск: при исчерпанном дневном бюджете
                        // searchItems молча возвращает [] — такой «пустой» результат на
                        // 3 дня лишал бы позицию релевантности.
                        if ($ttlDays > 0 && $searched > 0) {
                            Cache::put($key, $found, now()->addDays($ttlDays));
                        }
                    } catch (\Throwable $e) {
                        $failStreak++; // 3 подряд → Яндекс лежит, остальных не мучаем
                        continue;
                    }
                } else {
                    $yandexCached++;
                }
                if ($found === []) {
                    continue;
                }
                $match = $svc->matchPool($found, $poolIds);
                $rel = array_map('intval', array_merge((array) ($match['tier1'] ?? []), (array) ($match['tier2'] ?? [])));
                if ($rel !== []) {
                    $relFlip = array_flip($rel);
                    $relevantMap[$iid] = array_values(array_filter($poolIds, static fn ($s) => isset($relFlip[(int) $s])));
                }
                // Новые домены из выдачи (не в базе поставщиков) → кандидаты discovery.
                // matchPool уже отсёк существующих; диспатчим ПОСЛЕ рендера (не в dry).
                foreach ((array) ($match['candidates'] ?? []) as $host => $cand) {
                    if (!isset($discoveryCands[$host]) && (int) ($cand['product_type_id'] ?? 0) > 0) {
                        $cand['request_id'] = (int) ($positions[$iid]['request_id'] ?? 0);
                        $discoveryCands[$host] = $cand;
                    }
                }
            }
        }

        // 2c. Ветка «ящик на батч» (docs/alias-mailbox-design.md): позиции, отданные ей раньше, основная ветка
        // не берёт; доля новых позиций уходит в неё сегодня, пока хватает дневного бюджета ящиков.
        $aliasGroups = [];
        $aliasRows = [];
        $aliasOnly = (bool) $this->option('alias-only');
        foreach (AliasBranch::claimedItemIds() as $cid => $_) {
            unset($positions[$cid], $poolMap[$cid], $itemRows[$cid], $relevantMap[$cid]);
        }
        if (AliasBranch::enabled() || $aliasOnly || $this->option('alias-limit') !== null || $this->option('alias-items') || $this->option('alias-force')) {
            $budget = AliasBranch::budgetToday();
            if ($this->option('alias-limit') !== null) {
                $budget = min($budget, max(0, (int) $this->option('alias-limit')));
            }
            $onlyAlias = $this->option('alias-items') ? array_map('intval', explode(',', (string) $this->option('alias-items'))) : null;
            $cands = $this->option('alias-force')
                ? AliasBranch::forcedCandidates($positions, $poolMap, $onlyAlias, max(1, (int) $this->option('alias-min-pool')))
                : AliasBranch::candidates($positions, $poolMap, $emailedCount, $onlyAlias);
            $aliasGroups = AliasBranch::group($cands, $itemRows, (int) config('services.alias_exp.items_per_batch', 2));
            if ($this->option('alias-force')) {
                // Получатели пары — объединение остаточных пулов: поставщик, которому одну из позиций уже писали,
                // получил бы её повторно. Пары — только из позиций, о которых ещё никому не писали.
                $split = [];
                foreach ($aliasGroups as $g) {
                    if (count($g) > 1 && array_filter($g, fn ($i) => ($emailedCount[$i] ?? 0) > 0)) {
                        foreach ($g as $i) {
                            $split[] = [$i];
                        }
                    } else {
                        $split[] = $g;
                    }
                }
                $aliasGroups = $split;
            }
            $aliasGroups = array_slice($aliasGroups, 0, $budget);
            foreach ($aliasGroups as $g) {
                foreach ($g as $iid) {
                    $aliasRows[$iid] = $itemRows[$iid];
                }
            }
            $this->line(sprintf('  [ВЕТКА ящик-на-батч] кандидатов %d, бюджет ящиков %d, батчей %d (позиций %d, получателей ~%d)',
                count($cands), $budget, count($aliasGroups), count($aliasRows),
                array_sum(array_map(fn ($g) => count(array_unique(array_merge(...array_map(fn ($i) => $poolMap[$i] ?? [], $g)))), $aliasGroups))));
            foreach ($aliasGroups as $g) {
                $this->line(sprintf('    · %s → получателей %d', implode(' + ', array_map(fn ($i) => "#{$i} " . mb_substr((string) ($itemRows[$i]['name'] ?? ''), 0, 60)
                    . ' (уже писали ' . ($emailedCount[$i] ?? 0) . ')', $g)), count(array_unique(array_merge(...array_map(fn ($i) => $poolMap[$i] ?? [], $g))))));
            }
        }
        $aliasPool = array_intersect_key($poolMap, $aliasRows);
        $aliasRelevant = array_intersect_key($relevantMap, $aliasRows);
        foreach (array_keys($aliasRows) as $aid) {
            unset($positions[$aid], $poolMap[$aid], $itemRows[$aid], $relevantMap[$aid]);
        }
        if ($aliasOnly) {
            if (!$dry && $aliasGroups !== []) {
                $this->renderAlias($aliasGroups, $aliasRows, $aliasPool, $aliasRelevant, $useNewRouting);
            }
            return self::SUCCESS;
        }

        // 3. Аффинность.
        $affInput = [];
        foreach ($itemRows as $it) {
            $affInput[] = [
                'id' => (int) $it['id'], 'request_id' => (int) $it['request_id'],
                'name' => (string) ($it['name'] ?? ''), 'brand' => (string) ($it['brand'] ?? ''),
                'article' => (string) ($it['article'] ?? ''), 'type' => (string) ($it['category'] ?? ''),
                'domain_id' => (int) ($it['domain_id'] ?? 0),
            ];
        }
        $affinityAi = (bool) config('services.email_planner.dayplan_affinity_ai', true) ? $this->makeClient() : null;
        // Модель посильнее (gpt-4o): mini на avoid-парах слишком строгая — метила «не
        // мешать» обычные разнотипные комплектующие одной закупки, письма распадались.
        $affinity = (new PositionAffinity($affinityAi, (string) config('services.email_planner.dayplan_affinity_model', 'gpt-4o')))->compute($affInput);

        // 4. Ёмкость ящиков.
        $senderCaps = $this->senderCaps();
        // 5. Ёмкость получателей (конвертов на поставщика сегодня).
        $recipientCaps = $this->recipientCaps(array_values(array_unique(array_filter($supplierEmail))));

        // 6. История «ящик→получатель» за окно ротации: конвертам одного получателя —
        // разные ящики, и не те, что писали ему недавно (иначе персистер молча срежет).
        $rotDays = max(0, (int) config('services.email_generate.sender_recipient_days', 7));
        $senderRecent = [];
        if ($rotDays > 0) {
            foreach (DB::connection(self::CONN)->table('email_queue')
                ->where('created_at', '>=', now()->subDays($rotDays))
                ->whereIn('status', ['pending', 'sending', 'sent', 'opened', 'replied', 'reply_processed', 'in_conversation', 'completed'])
                ->selectRaw('DISTINCT sender_id, LOWER(to_email) e')->get() as $r) {
                $senderRecent[((int) $r->sender_id) . '|' . (string) $r->e] = true;
            }
        }
        // Паузы по отписке (ящик / наш домен × получатель) — для назначателя как «ящик писал недавно».
        $senderRecent += \App\Services\Senders\SenderPauses::pairs(
            (new \App\Services\Senders\SenderPauses())->active(array_values(array_unique(array_map('mb_strtolower', array_filter($supplierEmail))))),
            \App\Services\Senders\SenderPauses::senderEmails(array_keys($senderCaps)));

        // 7. Распределение позиций по конвертам поставщиков.
        $dropped = 0;
        $assignStats = [];
        $densify = (bool) config('services.email_planner.dayplan_densify', false);
        $adaptiveK = (bool) config('services.email_planner.dayplan_adaptive_per_email', false);
        // Однотипные позиции (тип + бренд + название без цифр: кнопки 1–6 этажей) — в одно письмо
        // до dayplan_max_homogeneous, даже при письме на одну позицию (решение владельца 16.10.2026).
        $maxHomogeneous = (int) config('services.email_planner.dayplan_max_homogeneous', 6);
        $homogeneous = [];
        foreach ($itemRows as $id => $it) {
            $key = \App\Services\Generate\HomogeneousKey::of((int) ($it['product_type_id'] ?? 0) ?: null, $it['brand'] ?? null, $it['name'] ?? null);
            if ($key !== null) {
                $homogeneous[(int) $id] = $key;
            }
        }
        $plan = (new DayPlanAssigner())->plan($positions, $poolMap, $relevantMap, $affinity, $supplierEmail, $senderCaps, $recipientCaps, $senderRecent, $maxPerEmail, $dropped, $assignStats, $densify, $adaptiveK, $homogeneous, $maxHomogeneous);
        $groupSizes = array_count_values($homogeneous);
        $this->line(sprintf('  [ДИАГ] однотипные группы: %d позиций в %d группах по 2+ (потолок %d в письме)',
            array_sum(array_filter($groupSizes, fn ($n) => $n > 1)), count(array_filter($groupSizes, fn ($n) => $n > 1)), $maxHomogeneous));

        // 8. Отчёт по плану (и в dry-run, и перед рендером).
        $this->reportPlan($plan, $positions, $priced, $yandexOn, $yandexQueried, $yandexCached, count($senderCaps), $assignStats);
        if ($dry) {
            return self::SUCCESS;
        }

        // 9. Рендер: группировка писем плана по (sender_id, набор позиций) → батчи.
        $limit = max(0, (int) $this->option('limit'));
        $toRender = $limit > 0 ? array_slice($plan, 0, $limit) : $plan;
        $res = $this->renderPlan($toRender, $itemRows, $useNewRouting);

        $this->info(sprintf(
            'Рендер: батчей %d, писем в email_queue %d (дедуп-скипов %d)%s. batch_ids: %s',
            $res['batches'],
            $res['queued'],
            $res['skipped'],
            $this->option('hold') ? ' [HELD]' : '',
            $res['batch_ids'] ? implode(',', $res['batch_ids']) : '—',
        ));

        if ($aliasGroups !== []) {
            $this->renderAlias($aliasGroups, $aliasRows, $aliasPool, $aliasRelevant, $useNewRouting);
        }

        // 10. Discovery новых доменов из Яндекс-выдачи (только боевой прогон): анализ
        // сайта + авто-добавление поставщика с таксономией — DiscoverFromCampaignJob
        // (очередь default). Найденных дошлёт top-up plan-render / завтрашний план.
        $dispatched = 0;
        if ((bool) config('services.email_planner.dayplan_discovery', true) && $discoveryCands !== []) {
            $maxDisc = max(0, (int) config('services.email_planner.dayplan_discovery_max', 30));
            foreach (array_slice($discoveryCands, 0, $maxDisc ?: null) as $cand) {
                DiscoverFromCampaignJob::dispatch(
                    (string) $cand['url'],
                    (int) $cand['product_type_id'],
                    isset($cand['domain_id']) && (int) $cand['domain_id'] > 0 ? (int) $cand['domain_id'] : null,
                    null,
                    (int) ($cand['request_id'] ?? 0) ?: null,
                );
                $dispatched++;
            }
            $this->info("Discovery: кандидатов {$this->cntArr($discoveryCands)}, отправлено в анализ {$dispatched}.");
        }

        Log::info('PlanDayCampaign: рендер', [
            'plan_emails' => count($plan), 'rendered_batches' => $res['batches'],
            'queued' => $res['queued'], 'skipped' => $res['skipped'], 'batch_ids' => $res['batch_ids'],
            'discovery_candidates' => count($discoveryCands), 'discovery_dispatched' => $dispatched,
            'assign' => $assignStats,
        ]);
        return self::SUCCESS;
    }

    private function renderAlias(array $groups, array $rows, array $pool, array $relevant, bool $useNewRouting): void
    {
        $r = app(AliasBranch::class)->render($groups, $rows, $pool, $relevant,
            fn (array $items) => $this->makeBatch($items, $useNewRouting), $this->makeClient(), (bool) $this->option('hold'));
        $this->info(sprintf('Ветка «ящик на батч»: батчей %d, писем %d, сбоев %d. Ящики: %s', $r['batches'], $r['queued'], $r['errors'],
            implode(', ', array_map(fn ($b, $m) => "{$m} (batch {$b})", array_keys($r['mailboxes']), $r['mailboxes'])) ?: '—'));
        Log::info('PlanDayCampaign: ветка alias', $r);
    }

    private function cntArr(array $a): int
    {
        return count($a);
    }

    /**
     * Рендер плана. Группа = (sender_id, отсортированный набор item_ids): один батч,
     * одно тело AI, N поставщиков с одинаковым набором позиций. Батч собирается ПОД
     * ПРОИЗВОЛЬНЫЙ набор позиций (НЕ через grouper — тот бьёт по типу/домену и разорвал
     * бы бандлы аффинности).
     *
     * @param array<int,array{supplier_id:int,sender_id:int,item_ids:array<int,int>,order:int,phase:string}> $plan
     * @param array<int,array<string,mixed>> $itemRows itemId => строка позиции
     * @return array{batches:int,queued:int,skipped:int,batch_ids:array<int,int>}
     */
    private function renderPlan(array $plan, array $itemRows, bool $useNewRouting): array
    {
        $groups = [];
        foreach ($plan as $p) {
            $itemIds = array_values(array_unique(array_map('intval', (array) $p['item_ids'])));
            sort($itemIds);
            $key = (int) $p['sender_id'] . '|' . implode(',', $itemIds);
            if (!isset($groups[$key])) {
                $groups[$key] = ['sender_id' => (int) $p['sender_id'], 'item_ids' => $itemIds, 'supplier_ids' => []];
            }
            if (!in_array((int) $p['supplier_id'], $groups[$key]['supplier_ids'], true)) {
                $groups[$key]['supplier_ids'][] = (int) $p['supplier_id'];
            }
        }
        if ($groups === []) {
            return ['batches' => 0, 'queued' => 0, 'skipped' => 0, 'batch_ids' => []];
        }

        // Профили поставщиков всех групп одним запросом (поля как у selector'а).
        $allSupIds = [];
        foreach ($groups as $g) {
            $allSupIds = array_merge($allSupIds, $g['supplier_ids']);
        }
        $supRows = [];
        foreach (DB::connection(self::CONN)->table('suppliers')
            ->whereIn('id', array_values(array_unique($allSupIds)))
            ->get(['id', 'name', 'email', 'contact_person', 'categories']) as $r) {
            $supRows[(int) $r->id] = (array) $r;
        }

        $cfg = config('services.email_generate');
        $client = $this->makeClient();
        $assigner = new CampaignSenderAssigner();
        $tokenGenerator = new CampaignTokenGenerator($client, (string) ($cfg['token_model'] ?? 'gpt-4o-mini'), (bool) ($cfg['token_use_ai'] ?? true));
        $bodyGenerator = new CampaignBodyGenerator($client, (string) ($cfg['body_model'] ?? 'gpt-4o'), (float) ($cfg['body_temperature'] ?? 0.7), (int) ($cfg['max_tokens'] ?? 1500));
        $emailBuilder = new CampaignEmailBuilder();
        $persister = new CampaignPersister();
        $hold = (bool) $this->option('hold');

        $batches = 0;
        $queued = 0;
        $skipped = 0;
        $batchIds = [];
        foreach ($groups as $g) {
            try {
                $items = [];
                foreach ($g['item_ids'] as $iid) {
                    if (isset($itemRows[$iid])) {
                        $items[] = $itemRows[$iid];
                    }
                }
                $suppliers = [];
                foreach ($g['supplier_ids'] as $sid) {
                    if (isset($supRows[$sid])) {
                        $suppliers[] = $supRows[$sid];
                    }
                }
                if ($items === [] || $suppliers === []) {
                    continue;
                }

                $batch = $this->makeBatch($items, $useNewRouting);
                $batch->sender = $assigner->fullSender($g['sender_id']);
                if (empty($batch->sender['email'])) {
                    Log::warning('PlanDayCampaign: у ящика нет профиля/email — группа пропущена', ['sender_id' => $g['sender_id']]);
                    continue;
                }
                $suppliers = $persister->writableSuppliers($suppliers, array_map('intval', $g['item_ids']), (int) $g['sender_id']);
                if ($suppliers === []) {
                    $skipped++;
                    continue; // всех отсеет дедуп/ротация — не платим за текст и не создаём пустой батч
                }
                $batch->suppliers = $suppliers;
                $batch->supplierIds = array_map(static fn ($s) => (int) $s['id'], $suppliers);

                $tokenGenerator->generate($batch);
                $bodyGenerator->generate($batch);
                $emails = [];
                foreach ($suppliers as $sup) {
                    $e = $emailBuilder->build($batch, $sup);
                    $e['wave'] = 1; // дневной план: всё уходит сейчас, held-пула нет
                    $emails[] = $e;
                }
                $res = $persister->persist($batch, $emails);

                // Тестовый предохранитель: сразу увести из-под диспетчера (окно рассылки
                // может быть открыто). Дальше строки инспектируются и cancelled руками.
                if ($hold && $res['queue_ids'] !== []) {
                    DB::connection(self::CONN)->table('email_queue')
                        ->whereIn('id', $res['queue_ids'])
                        ->update(['scheduled_at' => self::HELD]);
                }

                $batches++;
                $queued += count($res['queue_ids']);
                $skipped += count($emails) - count($res['queue_ids']);
                $batchIds[] = (int) $res['batch_id'];
            } catch (\Throwable $e) {
                Log::error('PlanDayCampaign: батч упал', [
                    'sender_id' => $g['sender_id'], 'item_ids' => $g['item_ids'], 'error' => $e->getMessage(),
                ]);
                $this->error("Батч (sender {$g['sender_id']}, items " . implode(',', $g['item_ids']) . ") упал: {$e->getMessage()}");
            }
        }

        return ['batches' => $batches, 'queued' => $queued, 'skipped' => $skipped, 'batch_ids' => $batchIds];
    }

    /**
     * Batch DTO под произвольный набор позиций (зеркалит поля grouper'а).
     * Именной батч — только если ВСЕ позиции из одной именной заявки; смешанный
     * бандл считается анонимным (customer-блок в теле не рисуем).
     *
     * @param array<int,array<string,mixed>> $items строки позиций (loadItems)
     */
    private function makeBatch(array $items, bool $useNewRouting): Batch
    {
        $batch = new Batch();
        $batch->items = array_values($items);
        $batch->itemsCount = count($items);
        $batch->useNewRouting = $useNewRouting;

        $reqIds = [];
        $reqNums = [];
        $cats = [];
        $ptIds = [];
        $dmIds = [];
        $allCustomer = true;
        foreach ($items as $it) {
            $rid = (int) ($it['request_id'] ?? 0);
            if ($rid > 0 && !in_array($rid, $reqIds, true)) {
                $reqIds[] = $rid;
            }
            $num = $it['request_number'] ?? null;
            if ($num !== null && $num !== '' && !in_array($num, $reqNums, true)) {
                $reqNums[] = $num;
            }
            $cat = trim((string) ($it['category'] ?? ''));
            if ($cat !== '' && !in_array($cat, $cats, true)) {
                $cats[] = $cat;
            }
            $pt = (int) ($it['product_type_id'] ?? 0);
            if ($pt > 0 && !in_array($pt, $ptIds, true)) {
                $ptIds[] = $pt;
            }
            $dm = (int) ($it['domain_id'] ?? 0);
            if ($dm > 0 && !in_array($dm, $dmIds, true)) {
                $dmIds[] = $dm;
            }
            if (empty($it['is_customer_request'])) {
                $allCustomer = false;
            }
        }
        $batch->requestIds = $reqIds;
        $batch->requestNumbers = $reqNums;
        $batch->productTypeIds = $ptIds;
        $batch->domainIds = $dmIds;
        $batch->category = count($cats) === 1 ? $cats[0] : (count($cats) > 1 ? 'mixed' : 'Другое');
        $batch->targetCategories = $useNewRouting ? ['NEW_ROUTING'] : [];

        if ($allCustomer && count($reqIds) === 1) {
            $first = $batch->items[0];
            $batch->isCustomerRequest = true;
            $batch->clientOrganizationId = !empty($first['client_organization_id']) ? (int) $first['client_organization_id'] : null;
            $batch->customerCompany = $first['customer_company'] ?? null;
            $batch->customerContactPerson = $first['customer_contact_person'] ?? null;
            $batch->customerEmail = $first['customer_email'] ?? null;
            $batch->customerPhone = $first['customer_phone'] ?? null;
        }

        return $batch;
    }

    /**
     * @param array<int,array<string,mixed>> $plan
     * @param array<int,array<string,mixed>> $positions
     * @param array<int,int> $priced
     */
    private function reportPlan(array $plan, array $positions, array $priced, bool $yandexOn, int $yandexQueried, int $yandexCached, int $senders, array $assignStats = []): void
    {
        $emails = count($plan);
        $byPhase = ['relevant' => 0, 'fill' => 0];
        $envPerRecipient = [];
        $sendersUsed = [];
        $sizes = [];
        $coverAdd = [];
        $renderGroups = [];
        foreach ($plan as $p) {
            $byPhase[$p['phase']] = ($byPhase[$p['phase']] ?? 0) + 1;
            $envPerRecipient[$p['supplier_id']] = ($envPerRecipient[$p['supplier_id']] ?? 0) + 1;
            $sendersUsed[$p['sender_id']] = true;
            $sizes[] = count($p['item_ids']);
            $ids = array_map('intval', $p['item_ids']);
            sort($ids);
            $renderGroups[$p['sender_id'] . '|' . implode(',', $ids)] = true;
            foreach ($p['item_ids'] as $it) {
                $coverAdd[$it] = ($coverAdd[$it] ?? 0) + 1;
            }
        }
        sort($sizes);
        $med = $sizes ? $sizes[intdiv(count($sizes), 2)] : 0;
        $avg = $sizes ? array_sum($sizes) / count($sizes) : 0;

        // Покрытие: скольким позициям план полностью раздаёт остаточный пул сегодня
        // (остальным не хватило ёмкости — хвост пула уйдёт завтра) + добор до target.
        $activeCnt = count($positions);
        $willReach = 0;
        foreach ($positions as $id => $m) {
            $after = (int) ($priced[$id] ?? 0) + (int) ($coverAdd[$id] ?? 0);
            if ($m['max_reach'] || $after >= (int) $m['target']) {
                $willReach++;
            }
        }

        $this->info('=== ДНЕВНОЙ ПЛАН ' . ($this->option('dry-run') ? '(dry-run)' : '(к рендеру)') . ' ===');
        $this->line("  Яндекс: " . ($yandexOn ? "вкл (запросов {$yandexQueried}, из кэша {$yandexCached})" : 'выкл') . " | ящиков с ёмкостью: {$senders}");
        $this->line("  Активных позиций: {$activeCnt}");
        $this->line("  Писем (конвертов) в плане: {$emails}  (с релевантными {$byPhase['relevant']} / добор {$byPhase['fill']}) | рендер-групп (тел AI): " . count($renderGroups));
        $recipients = count($envPerRecipient);
        $this->line(sprintf(
            "  Получателей: %d | конвертов на получателя: avg=%.1f max=%d | задействовано ящиков: %d",
            $recipients,
            $recipients ? $emails / $recipients : 0,
            $envPerRecipient ? max($envPerRecipient) : 0,
            count($sendersUsed),
        ));
        $this->line(sprintf("  Позиций в письме: median=%d avg=%.1f max=%d  (v1 было ~1)", $med, $avg, $sizes ? max($sizes) : 0));
        $this->line(sprintf("  Позиций дойдут до target: %d из %d (%d%%)", $willReach, $activeCnt, $activeCnt ? round(100 * $willReach / $activeCnt) : 0));
        if ($assignStats !== []) {
            $sk = $assignStats['skips'] ?? [];
            $this->line(sprintf(
                "  [ДИАГ] раундов=%d | ёмкость ящиков: %d/%d исп. | конвертов этап1=%d, +densify=%d, дропнуто-без-ящика=%d, оставлено=%d",
                $assignStats['rounds'] ?? 0,
                ($assignStats['capacity_total'] ?? 0) - ($assignStats['capacity_left'] ?? 0),
                $assignStats['capacity_total'] ?? 0,
                $assignStats['stage1_envelopes'] ?? 0,
                $assignStats['densify_added'] ?? 0,
                $assignStats['dropped_no_sender'] ?? 0,
                $assignStats['kept'] ?? 0,
            ));
            $this->line(sprintf(
                "  [ДИАГ] недобрали пул: %d из %d позиций | отложено на завтра слотов=%d | скипы: пустой-email=%d, кап-получателя=%d, ёмкость=%d",
                $assignStats['positions_partial'] ?? 0,
                $assignStats['positions_total'] ?? 0,
                $assignStats['deferred_slots'] ?? 0,
                $sk['empty_email'] ?? 0,
                $sk['recipient_cap'] ?? 0,
                $sk['capacity'] ?? 0,
            ));
            $fmt = static fn (array $d): string => $d === [] ? '—' : implode(', ', array_map(static fn ($k, $v) => "{$k}:{$v}", array_keys($d), $d));
            $this->line(sprintf(
                "  [ДИАГ] адаптивный размер письма: %s | писем по числу позиций {%s} | поставщиков по K {%s}",
                !empty($assignStats['adaptive_per_email']) ? 'ВКЛ' : 'выкл',
                $fmt($assignStats['envelope_size_dist'] ?? []),
                $fmt($assignStats['supplier_k_dist'] ?? []),
            ));
        }
    }

    /** @return array<int,int> senderId => остаток дневной ёмкости */
    private function senderCaps(): array
    {
        $today = now()->toDateString();
        $inFlight = DB::connection(self::CONN)->table('email_queue')->where('status', 'sending')
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->pluck('n', 'sender_id');
        $caps = [];
        foreach (SenderPool::onlyMain(DB::connection(self::CONN)->table('senders'))
            ->where('is_active', 1)->where(function ($q) {
                $q->whereNull('sending_disabled')->orWhere('sending_disabled', 0);
            })->where(function ($q) {
                $q->whereNull('blocked_until')->orWhereRaw('blocked_until <= NOW()');
            })->get(['id', 'daily_limit', 'emails_sent_today', 'last_send_date']) as $s) {
            $limit = (int) $s->daily_limit;
            if ($limit <= 0) {
                continue;
            }
            $sent = (substr((string) $s->last_send_date, 0, 10) === $today) ? (int) $s->emails_sent_today : 0;
            $rem = $limit - $sent - (int) ($inFlight[$s->id] ?? 0);
            if ($rem > 0) {
                $caps[(int) $s->id] = $rem;
            }
        }
        // Суточный потолок аккаунта провайдера (REG.RU 3000/24 ч) — с учётом уже запланированного на сегодня.
        return array_filter(\App\Support\Senders\SmtpServerCaps::clipByDb($caps, true), fn ($rem) => $rem > 0);
    }

    /**
     * @param array<int,string> $emails
     * @return array<string,int> email => остаток дневного cap
     */
    private function recipientCaps(array $emails): array
    {
        // Лимит канала «заявки» (каналы изолированы — RecipientChannels): письма эксперимента и мониторинга его не едят.
        return (new \App\Services\Dispatch\RecipientChannels())->remaining($emails, \App\Services\Dispatch\RecipientChannels::MAIN);
    }

    /** @param array<int,int>|null $onlyItemIds @return array<int,array<string,mixed>> */
    private function loadItems(int $requestId, ?array $onlyItemIds = null): array
    {
        $rows = DB::connection(self::CONN)->table('request_items as ri')
            ->join('requests as r', 'ri.request_id', '=', 'r.id')
            ->where('r.id', $requestId)
            ->when($onlyItemIds !== null, fn ($q) => $q->whereIn('ri.id', $onlyItemIds))
            ->orderBy('ri.position_number', 'asc')->orderBy('ri.id', 'asc')
            ->get([
                'ri.id', 'ri.request_id', 'ri.position_number', 'ri.name', 'ri.brand', 'ri.article',
                'ri.quantity', 'ri.unit', 'ri.category', 'ri.description', 'ri.product_type_id', 'ri.domain_id',
                'r.is_customer_request', 'r.client_organization_id', 'r.request_number',
                'r.customer_company', 'r.customer_contact_person', 'r.customer_email', 'r.customer_phone',
            ]);
        return array_map(static fn ($row) => (array) $row, $rows->all());
    }

    /** @return array<int,array<string,mixed>> */
    private function loadCategories(): array
    {
        return array_map(static fn ($row) => (array) $row, DB::connection(self::CONN)->table('categories')
            ->where('is_active', 1)->get(['id', 'name', 'description', 'routing'])->all());
    }

    private function makeClient(): OpenAIClassifierClient
    {
        $oc = config('services.openai_classifier');
        return new OpenAIClassifierClient(
            baseUrl: rtrim((string) ($oc['base_url'] ?? ''), '/'),
            apiKey: (string) ($oc['api_key'] ?? ''),
            proxyKey: (string) ($oc['proxy_key'] ?? ''),
            modelMini: (string) ($oc['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($oc['model_full'] ?? 'gpt-4o'),
            timeout: 60,
        );
    }
}
