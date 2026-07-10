<?php

namespace App\Console\Commands;

use App\Services\Api\OpenAIClassifierClient;
use App\Services\Generate\CampaignBodyGenerator;
use App\Services\Generate\CampaignEmailBuilder;
use App\Services\Generate\CampaignItemGrouper;
use App\Services\Generate\CampaignPersister;
use App\Services\Generate\CampaignSenderAssigner;
use App\Services\Generate\CampaignSupplierSelector;
use App\Services\Generate\CampaignTokenGenerator;
use App\Services\Generate\PositionCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Фаза 2, Шаг 4: ПЛАНИРОВЩИК-РЕНДЕР. Потребляет send_intents(backlog) под ёмкость и
 * ЛЕНИВО рендерит письма только тем, кто влезает в дневной cap получателя СЕЙЧАС.
 *
 * Отличия от старого генератора:
 *   - drive от backlog (persistent спрос), а не one-shot генерация заявки;
 *   - только АКТИВНЫЕ позиции (закрытые выпадают, освобождая место);
 *   - capacity-gate по получателю — НЕ рендерим пачку на переполненного адресата;
 *   - НЕТ волн/held-пула: всё, что рендерим — волна 1 (уходит сейчас); backlog сам
 *     себе «холодный резерв» (нерендеренные интенты ждут ёмкости);
 *   - разнос по получателям: 1 рендер на получателя за прогон.
 *
 * Переиспользует весь render-путь (SenderAssigner/Selector/Token/Body/EmailBuilder/
 * Persister). За флагом EMAILS_PLANNER_ENABLED. Включать вместе с выключением
 * EMAILS_GENERATE_ENABLED (иначе оба обрабатывают заявки).
 *
 * РЕЖИМ TOP-UP (автоматически при EMAILS_DAYPLAN_ENABLED=true): полный backlog раздаёт
 * утренний emails:plan-day, а эта команда днём дорабатывает ТОЛЬКО НОВЫХ поставщиков
 * (появились из discovery после утреннего плана: suppliers.created_at свежее
 * dayplan_topup_new_hours) по ещё-открытым позициям.
 */
class PlanRenderIntents extends Command
{
    private const CONN = 'reports';
    private const HELD = '2037-01-01 00:00:00';

    protected $signature = 'emails:plan-render
        {--limit=300 : Потолок писем за прогон}
        {--request= : Только эта заявка (тест)}
        {--dry-run : Отбор+capacity-gate без рендера/persist (безопасный тест)}
        {--force : Запустить при выключенном флаге}';

    protected $description = 'Фаза 2: рендер писем из backlog send_intents под ёмкость получателей (лениво)';

    public function handle(): int
    {
        if (!$this->option('force') && !(bool) config('services.email_planner.enabled', false)) {
            $this->warn('Планировщик выключен (EMAILS_PLANNER_ENABLED=false). --force для ручного прогона.');
            return self::SUCCESS;
        }

        $budget = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');
        $coverage = new PositionCoverage();
        $defaultTarget = (int) config('services.email_planner.offer_target_default', 4);

        // Режим top-up при включённом дневном планировщике: полный backlog раздаёт
        // утренний plan-day, здесь рендерим только НОВЫХ поставщиков (из discovery).
        $topupSince = null;
        if ((bool) config('services.email_planner.dayplan_enabled', false)) {
            $topupSince = now()->subHours(max(1, (int) config('services.email_planner.dayplan_topup_new_hours', 24)));
        }

        // Заявки с backlog-интентами, ранжированные: клиентские вперёд, затем старые.
        $reqQuery = DB::connection(self::CONN)->table('send_intents as si')
            ->join('requests as r', 'r.id', '=', 'si.request_id')
            ->where('si.status', 'backlog');
        if ($topupSince !== null) {
            $reqQuery->whereExists(function ($q) use ($topupSince) {
                $q->selectRaw('1')->from('suppliers as sup')
                    ->whereColumn('sup.id', 'si.supplier_id')
                    ->where('sup.created_at', '>=', $topupSince);
            });
        }
        if ($only = (int) $this->option('request')) {
            $reqQuery->where('si.request_id', $only);
        }
        $requestIds = $reqQuery->groupBy('si.request_id')
            ->orderByRaw('MAX(r.is_customer_request) DESC')
            ->orderByRaw('MIN(r.created_at) ASC')
            ->limit(200)
            ->pluck('si.request_id')->map(static fn ($v) => (int) $v)->all();

        if ($requestIds === []) {
            $this->info('Backlog пуст.');
            return self::SUCCESS;
        }

        // Ёмкость получателей: caps + НЕ-held pending (near-term outbox) по всем адресам backlog.
        [$caps, $pending] = $this->recipientCapacity($requestIds);
        $baseCap = (int) config('services.email_dispatch.recipient_daily_cap', 10);

        $categories = $this->loadCategories();
        $useNewRouting = $this->loadUseNewRouting();
        $cfg = config('services.email_generate');
        $client = $this->makeClient($cfg);

        $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) ($cfg['items_per_batch'] ?? 5));
        $selector = new CampaignSupplierSelector();
        $tokenGenerator = new CampaignTokenGenerator($client, (string) ($cfg['token_model'] ?? 'gpt-4o-mini'), (bool) ($cfg['token_use_ai'] ?? true));
        $bodyGenerator = new CampaignBodyGenerator($client, (string) ($cfg['body_model'] ?? 'gpt-4o'), (float) ($cfg['body_temperature'] ?? 0.7), (int) ($cfg['max_tokens'] ?? 1500));
        $emailBuilder = new CampaignEmailBuilder();
        $persister = new CampaignPersister();

        $reserved = [];   // получатели, отоваренные в этом прогоне (разнос)
        $rendered = 0; $droppedSat = 0; $reqDone = 0;

        foreach ($requestIds as $rid) {
            if ($budget <= 0) {
                break;
            }
            $row = DB::connection(self::CONN)->table('requests')->where('id', $rid)->first(['status', 'offer_target', 'max_reach']);
            // Терминальная заявка (completed/cancelled/нет строки) → гасим её backlog.
            if (!$row || in_array((string) $row->status, ['completed', 'cancelled'], true)) {
                if (!$dry) {
                    DB::connection(self::CONN)->table('send_intents')
                        ->where('request_id', $rid)->where('status', 'backlog')
                        ->update(['status' => 'dropped', 'last_reason' => 'request_terminal', 'updated_at' => now()]);
                }
                continue;
            }
            $offerTarget = $row->offer_target !== null ? (int) $row->offer_target : $defaultTarget;
            $maxReach = (bool) ($row->max_reach ?? false);

            $active = $coverage->activeItemIds($rid, $offerTarget, $maxReach);
            if ($active === [] && !$maxReach) {
                if (!$dry) {
                    $droppedSat += DB::connection(self::CONN)->table('send_intents')
                        ->where('request_id', $rid)->where('status', 'backlog')
                        ->update(['status' => 'dropped', 'last_reason' => 'request_satisfied', 'updated_at' => now()]);
                }
                $reqDone++;
                continue;
            }
            if ($active === []) {
                continue;
            }

            $backlogSuppliers = DB::connection(self::CONN)->table('send_intents')
                ->where('request_id', $rid)->where('status', 'backlog')
                ->when($topupSince !== null, fn ($q) => $q->whereIn('supplier_id', function ($sub) use ($topupSince) {
                    $sub->select('id')->from('suppliers')->where('created_at', '>=', $topupSince);
                }))
                ->pluck('supplier_id')->map(static fn ($v) => (int) $v)->flip();
            if ($backlogSuppliers->isEmpty()) {
                continue;
            }

            $items = $this->loadItems($rid, $active);
            if ($items === []) {
                continue;
            }
            $batches = $grouper->group($items);
            (new CampaignSenderAssigner())->assign($batches);

            foreach ($batches as $batch) {
                if ($budget <= 0) {
                    break;
                }
                if (empty($batch->sender)) {
                    continue;
                }
                $selected = $selector->select($batch);

                // Кандидаты: матчащие батч, имеющие backlog-интент, с ёмкостью и не отоваренные.
                $fit = [];
                $fitIds = [];
                foreach ($selected as $sup) {
                    $sid = (int) ($sup['id'] ?? 0);
                    if ($sid <= 0 || !$backlogSuppliers->has($sid)) {
                        continue;
                    }
                    $email = mb_strtolower(trim((string) ($sup['email'] ?? '')));
                    if ($email === '' || isset($reserved[$email])) {
                        continue;
                    }
                    $cap = $caps[$email] ?? $baseCap;
                    if ($cap > 0 && (int) ($pending[$email] ?? 0) >= $cap) {
                        continue; // outbox получателя полон — оставляем в backlog
                    }
                    $fit[] = $sup;
                    $fitIds[] = $sid;
                    $reserved[$email] = true;
                    $pending[$email] = (int) ($pending[$email] ?? 0) + 1;
                    if (count($fit) >= $budget) {
                        break;
                    }
                }
                if ($fit === []) {
                    continue;
                }

                // Dry-run: считаем «что отрендерили бы», без AI/persist/пометок.
                if ($dry) {
                    $rendered += count($fit);
                    $budget -= count($fit);
                    continue;
                }

                $batch->suppliers = $fit;
                $batch->expansionSuppliers = [];
                $batch->coldSuppliers = [];

                $tokenGenerator->generate($batch);
                $bodyGenerator->generate($batch);
                $emails = [];
                foreach ($fit as $sup) {
                    $e = $emailBuilder->build($batch, $sup);
                    $e['wave'] = 1; // планировщик: всё уходит сейчас, held-пула нет
                    $emails[] = $e;
                }
                $persister->persist($batch, $emails);

                // Интенты → rendered.
                DB::connection(self::CONN)->table('send_intents')
                    ->where('request_id', $rid)->whereIn('supplier_id', $fitIds)->where('status', 'backlog')
                    ->update(['status' => 'rendered', 'batch_id' => $batch->batchId, 'updated_at' => now()]);

                $rendered += count($emails);
                $budget -= count($emails);
            }
        }

        $this->info("Планировщик: " . ($dry ? 'БЫЛО БЫ отрендерено' : 'отрендерено') . " {$rendered}, закрытых заявок {$reqDone} (backlog→dropped {$droppedSat}), получателей {$this->cnt($reserved)}." . ($dry ? ' [dry-run]' : ''));
        Log::info('PlanRenderIntents: прогон', ['rendered' => $rendered, 'req_done' => $reqDone, 'recipients' => $this->cnt($reserved)]);
        return self::SUCCESS;
    }

    private function cnt(array $a): int
    {
        return count($a);
    }

    /**
     * Ёмкость получателей backlog-заявок: [caps(email→cap), pending(email→не-held pending)].
     *
     * @param array<int,int> $requestIds
     * @return array{0:array<string,int>,1:array<string,int>}
     */
    private function recipientCapacity(array $requestIds): array
    {
        // email всех backlog-поставщиков этих заявок.
        $emails = DB::connection(self::CONN)->table('send_intents as si')
            ->join('suppliers as s', 's.id', '=', 'si.supplier_id')
            ->whereIn('si.request_id', $requestIds)->where('si.status', 'backlog')
            ->whereNotNull('s.email')->where('s.email', '<>', '')
            ->selectRaw('LOWER(s.email) as email_l')->distinct()->pluck('email_l')->all();
        if ($emails === []) {
            return [[], []];
        }

        $pending = DB::connection(self::CONN)->table('email_queue')
            ->whereIn(DB::raw('LOWER(to_email)'), $emails)
            ->where('status', 'pending')->where('scheduled_at', '<', self::HELD)
            ->selectRaw('LOWER(to_email) r, COUNT(*) c')
            ->groupBy(DB::raw('LOWER(to_email)'))->pluck('c', 'r')
            ->map(static fn ($v) => (int) $v)->all();

        $baseCap = (int) config('services.email_dispatch.recipient_daily_cap', 10);
        $caps = [];
        foreach (DB::connection(self::CONN)->table('recipient_mailboxes')
            ->whereIn('email', $emails)->get(['email', 'daily_cap', 'is_blocked']) as $r) {
            // Заблокированный получатель — cap 0 (не рендерим вовсе).
            $caps[$r->email] = (int) $r->is_blocked === 1 ? 0 : ($r->daily_cap !== null ? (int) $r->daily_cap : $baseCap);
        }

        return [$caps, $pending];
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

    private function loadUseNewRouting(): bool
    {
        return (bool) DB::connection(self::CONN)->table('migration_flags')
            ->where('flag_name', 'use_new_routing')->value('is_enabled');
    }

    /** @return array<int,array<string,mixed>> */
    private function loadCategories(): array
    {
        return array_map(static fn ($row) => (array) $row, DB::connection(self::CONN)->table('categories')
            ->where('is_active', 1)->get(['id', 'name', 'description', 'routing'])->all());
    }

    /** @param array<string,mixed> $cfg */
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
