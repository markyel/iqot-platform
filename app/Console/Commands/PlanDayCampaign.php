<?php

namespace App\Console\Commands;

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
        if ($only = (int) $this->option('request')) {
            $reqQuery->where('id', $only);
        } else {
            $reqQuery->whereIn('status', ['draft', 'new', 'active', 'queued_for_sending', 'emails_sent', 'responses_received'])
                ->where('created_at', '>=', now()->subDays($freshDays));
        }
        $requests = $reqQuery->get(['id', 'offer_target', 'max_reach', 'is_customer_request', 'created_at']);

        $positions = [];   // itemId => мета
        $itemRows = [];    // itemId => строка позиции
        foreach ($requests as $r) {
            $target = $r->offer_target !== null ? (int) $r->offer_target : $defaultTarget;
            $maxReach = (bool) $r->max_reach;
            $active = $coverage->activeItemIds((int) $r->id, $target, $maxReach);
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
                        $found = $svc->searchItems([$it]);
                        $yandexQueried++;
                        $failStreak = 0;
                        if ($ttlDays > 0) {
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
            }
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

        // 7. Распределение позиций по конвертам поставщиков.
        $plan = (new DayPlanAssigner())->plan($positions, $poolMap, $relevantMap, $affinity, $supplierEmail, $senderCaps, $recipientCaps, $senderRecent, $maxPerEmail);

        // 8. Отчёт по плану (и в dry-run, и перед рендером).
        $this->reportPlan($plan, $positions, $priced, $yandexOn, $yandexQueried, $yandexCached, count($senderCaps));
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
        Log::info('PlanDayCampaign: рендер', [
            'plan_emails' => count($plan), 'rendered_batches' => $res['batches'],
            'queued' => $res['queued'], 'skipped' => $res['skipped'], 'batch_ids' => $res['batch_ids'],
        ]);
        return self::SUCCESS;
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
    private function reportPlan(array $plan, array $positions, array $priced, bool $yandexOn, int $yandexQueried, int $yandexCached, int $senders): void
    {
        $emails = count($plan);
        $byPhase = ['relevant' => 0, 'fill' => 0];
        $envPerRecipient = [];
        $sendersUsed = [];
        $sizes = [];
        $coverAdd = [];
        foreach ($plan as $p) {
            $byPhase[$p['phase']] = ($byPhase[$p['phase']] ?? 0) + 1;
            $envPerRecipient[$p['supplier_id']] = ($envPerRecipient[$p['supplier_id']] ?? 0) + 1;
            $sendersUsed[$p['sender_id']] = true;
            $sizes[] = count($p['item_ids']);
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
        $this->line("  Писем (конвертов) в плане: {$emails}  (с релевантными {$byPhase['relevant']} / добор {$byPhase['fill']})");
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
    }

    /** @return array<int,int> senderId => остаток дневной ёмкости */
    private function senderCaps(): array
    {
        $today = now()->toDateString();
        $inFlight = DB::connection(self::CONN)->table('email_queue')->where('status', 'sending')
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->pluck('n', 'sender_id');
        $caps = [];
        foreach (DB::connection(self::CONN)->table('senders')
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
        return $caps;
    }

    /**
     * @param array<int,string> $emails
     * @return array<string,int> email => остаток дневного cap
     */
    private function recipientCaps(array $emails): array
    {
        if ($emails === []) {
            return [];
        }
        $baseCap = (int) config('services.email_dispatch.recipient_daily_cap', 10);
        $today = Carbon::now('Europe/Moscow')->toDateString();
        $pending = DB::connection(self::CONN)->table('email_queue')
            ->whereIn(DB::raw('LOWER(to_email)'), $emails)
            ->where('status', 'pending')->where('scheduled_at', '<', self::HELD)
            ->selectRaw('LOWER(to_email) r, COUNT(*) c')->groupBy(DB::raw('LOWER(to_email)'))->pluck('c', 'r');
        $caps = [];
        $rows = DB::connection(self::CONN)->table('recipient_mailboxes')->whereIn('email', $emails)
            ->get(['email', 'daily_cap', 'daily_sent_count', 'daily_sent_date', 'is_blocked']);
        $meta = [];
        foreach ($rows as $r) {
            $meta[$r->email] = $r;
        }
        foreach ($emails as $e) {
            $r = $meta[$e] ?? null;
            if ($r && (int) $r->is_blocked === 1) {
                $caps[$e] = 0;
                continue;
            }
            $cap = ($r && $r->daily_cap !== null) ? (int) $r->daily_cap : $baseCap;
            $sentToday = ($r && (string) $r->daily_sent_date === $today) ? (int) $r->daily_sent_count : 0;
            $used = $sentToday + (int) ($pending[$e] ?? 0);
            $caps[$e] = max(0, $cap - $used);
        }
        return $caps;
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
