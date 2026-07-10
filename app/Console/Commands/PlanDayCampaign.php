<?php

namespace App\Console\Commands;

use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\CampaignItemGrouper;
use App\Services\Generate\CampaignSupplierSelector;
use App\Services\Generate\DayPlanAssigner;
use App\Services\Generate\PositionAffinity;
use App\Services\Generate\PositionCoverage;
use App\Services\Generate\SupplierTargetingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Фаза 2 (v2), утренний прогон: строит ДНЕВНОЙ ПЛАН рассылки.
 *
 * позиции(активные) → пулы(профильные − уже писавшие) → Яндекс(релевантные) +
 * AI-аффинность → DayPlanAssigner → упорядоченный план (поставщик→позиции→ящик).
 *
 * Пока реализована ЧАСТЬ ПЛАНИРОВАНИЯ + --dry-run отчёт (для сверки с текущей
 * рассылкой). Рендер (запись в email_queue) — следующий шаг; без --dry-run команда
 * НИЧЕГО не пишет (предохранитель). За флагом EMAILS_DAYPLAN_ENABLED.
 */
class PlanDayCampaign extends Command
{
    private const CONN = 'reports';
    private const HELD = '2037-01-01 00:00:00';

    protected $signature = 'emails:plan-day
        {--dry-run : Построить и показать план без записи}
        {--no-yandex : Без Яндекс-релевантности (быстрый прогон)}
        {--force : Запустить при выключенном флаге}';

    protected $description = 'Фаза 2: построить дневной план рассылки (позиции→пулы→Яндекс→аффинность→назначатель)';

    public function handle(): int
    {
        if (!$this->option('force') && !(bool) config('services.email_planner.dayplan_enabled', false)) {
            $this->warn('Дневной планировщик выключен (EMAILS_DAYPLAN_ENABLED=false). --force для прогона.');
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        if (!$dry) {
            $this->warn('Рендер плана ещё не реализован — запусти с --dry-run (пока команда только строит и показывает план).');
            return self::SUCCESS;
        }

        $coverage = new PositionCoverage();
        $defaultTarget = (int) config('services.email_planner.offer_target_default', 4);
        $maxPerEmail = (int) config('services.email_planner.dayplan_max_per_email', 4);
        $freshDays = max(1, (int) config('services.email_planner.fresh_days', 7));

        // 1. Активные позиции по открытым свежим заявкам.
        $requests = DB::connection(self::CONN)->table('requests')
            ->whereIn('status', ['draft', 'new', 'active', 'queued_for_sending', 'emails_sent', 'responses_received'])
            ->where('created_at', '>=', now()->subDays($freshDays))
            ->get(['id', 'offer_target', 'max_reach', 'is_customer_request', 'created_at']);

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

        // 2. Пулы + Яндекс-релевантность (по батчам группировки type/domain).
        $categories = $this->loadCategories();
        $useNewRouting = (bool) DB::connection(self::CONN)->table('migration_flags')->where('flag_name', 'use_new_routing')->value('is_enabled');
        $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) config('services.email_generate.items_per_batch', 5));
        $selector = new CampaignSupplierSelector();
        $yandexOn = !$this->option('no-yandex')
            && (bool) config('services.email_planner.dayplan_yandex', true)
            && (bool) config('services.email_pretarget.enabled', false);

        $poolMap = [];        // itemId => [supplierId]
        $relevantMap = [];    // itemId => [supplierId]
        $supplierEmail = [];  // supplierId => email
        $yandexQueriedBatches = 0;

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
            $relevant = [];
            if ($yandexOn && $supIds !== []) {
                try {
                    $res = SupplierTargetingService::make()->target($batch->items, $supIds);
                    $relevant = array_values(array_unique(array_merge(
                        array_map('intval', (array) ($res['tier1'] ?? [])),
                        array_map('intval', (array) ($res['tier2'] ?? [])),
                    )));
                    $yandexQueriedBatches++;
                } catch (\Throwable $e) {
                    // Яндекс упал — позиции батча пойдут через фазу добора (без релевантных).
                }
            }
            $relFlip = array_flip($relevant);
            foreach ($batch->items as $it) {
                $iid = (int) ($it['id'] ?? 0);
                if ($iid <= 0 || !isset($positions[$iid])) {
                    continue;
                }
                $emailed = $coverage->emailedSuppliersPerItem([$iid])[$iid] ?? [];
                $remaining = $coverage->remainingPool($supIds, $emailed);
                $poolMap[$iid] = $remaining;
                $relevantMap[$iid] = array_values(array_filter($remaining, static fn ($s) => isset($relFlip[(int) $s])));
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
        $affinity = (new PositionAffinity($affinityAi, (string) config('services.email_generate.token_model', 'gpt-4o-mini')))->compute($affInput);

        // 4. Ёмкость ящиков.
        $senderCaps = $this->senderCaps();
        // 5. Ёмкость получателей.
        $recipientCaps = $this->recipientCaps(array_values(array_unique(array_filter($supplierEmail))));

        // 6. План.
        $plan = (new DayPlanAssigner())->plan($positions, $poolMap, $relevantMap, $affinity, $supplierEmail, $senderCaps, $recipientCaps, $maxPerEmail);

        // 7. Отчёт (dry-run).
        $this->reportPlan($plan, $positions, $priced, $yandexOn, $yandexQueriedBatches, count($senderCaps));
        return self::SUCCESS;
    }

    /**
     * @param array<int,array<string,mixed>> $plan
     * @param array<int,array<string,mixed>> $positions
     * @param array<int,int> $priced
     */
    private function reportPlan(array $plan, array $positions, array $priced, bool $yandexOn, int $yandexBatches, int $senders): void
    {
        $emails = count($plan);
        $byPhase = ['relevant' => 0, 'fill' => 0];
        $recips = [];
        $sendersUsed = [];
        $sizes = [];
        $coverAdd = [];
        foreach ($plan as $p) {
            $byPhase[$p['phase']] = ($byPhase[$p['phase']] ?? 0) + 1;
            $recips[$p['supplier_id']] = true;
            $sendersUsed[$p['sender_id']] = true;
            $sizes[] = count($p['item_ids']);
            foreach ($p['item_ids'] as $it) {
                $coverAdd[$it] = ($coverAdd[$it] ?? 0) + 1;
            }
        }
        sort($sizes);
        $med = $sizes ? $sizes[intdiv(count($sizes), 2)] : 0;
        $avg = $sizes ? array_sum($sizes) / count($sizes) : 0;

        // покрытие: сколько позиций план доводит до target
        $activeCnt = count($positions);
        $willReach = 0;
        foreach ($positions as $id => $m) {
            $after = (int) ($priced[$id] ?? 0) + (int) ($coverAdd[$id] ?? 0);
            if ($m['max_reach'] || $after >= (int) $m['target']) {
                $willReach++;
            }
        }

        $this->info('=== ДНЕВНОЙ ПЛАН (dry-run) ===');
        $this->line("  Яндекс: " . ($yandexOn ? "вкл (батчей опрошено {$yandexBatches})" : 'выкл') . " | ящиков с ёмкостью: {$senders}");
        $this->line("  Активных позиций: {$activeCnt}");
        $this->line("  Писем в плане: {$emails}  (релевантные {$byPhase['relevant']} / добор {$byPhase['fill']})");
        $this->line("  Уникальных получателей: " . count($recips) . " | задействовано ящиков: " . count($sendersUsed));
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
