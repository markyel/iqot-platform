<?php

namespace App\Console\Commands;

use App\Services\Generate\CampaignItemGrouper;
use App\Services\Generate\CampaignSupplierSelector;
use App\Services\Generate\PositionCoverage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Фаза 2 (планировщик), Шаг 3: билдер BACKLOG спроса (send_intents).
 *
 * Дёшево (без AI/HTML): по активным заявкам подбирает профильных поставщиков и
 * заводит интенты (request×supplier, status=backlog). Единица покрытия — ПОЗИЦИЯ:
 * работаем только по АКТИВНЫМ (незакрытым) позициям; заявку с закрытыми позициями
 * (target достигнут) не трогаем, а её backlog-интенты гасим (dropped). Дедуп:
 * UNIQUE(request_id,supplier_id) + пропуск уже контактированных (есть
 * request_item_responses по заявке). HTML рендерит потом планировщик — лениво под ёмкость.
 *
 * За флагом EMAILS_PLANNER_ENABLED. Инертен, пока флаг off (ручной прогон --force).
 */
class BuildSendIntents extends Command
{
    private const CONN = 'reports';

    protected $signature = 'emails:build-intents
        {--limit= : Заявок за прогон (дефолт из конфига)}
        {--request= : Только эта заявка}
        {--force : Запустить при выключенном флаге}';

    protected $description = 'Фаза 2: наполнить backlog send_intents по активным заявкам (позиционно)';

    public function handle(): int
    {
        if (!$this->option('force') && !(bool) config('services.email_planner.enabled', false)) {
            $this->warn('Планировщик выключен (EMAILS_PLANNER_ENABLED=false). --force для ручного прогона.');
            return self::SUCCESS;
        }

        $coverage = new PositionCoverage();
        $defaultTarget = (int) config('services.email_planner.offer_target_default', 4);

        $q = DB::connection(self::CONN)->table('requests');
        if ($reqOpt = (int) $this->option('request')) {
            // Точечный прогон — вне зависимости от статуса (ручной/тест).
            $q->where('id', $reqOpt);
        } else {
            // Живой набор: новые + уже-обработанные старым генератором (их
            // не-контактированные поставщики = резерв для планировщика), в окне свежести.
            // Дедуп (request_item_responses) не даст двойной отправки уже-queued.
            $freshDays = max(1, (int) config('services.email_planner.fresh_days', 7));
            // Открытые заявки (не completed/cancelled): новые + отправленные/получившие
            // часть ответов, но с ещё незакрытыми позициями. Дедуп не даст двойной отправки.
            $q->whereIn('status', ['draft', 'new', 'active', 'queued_for_sending', 'emails_sent', 'responses_received'])
                ->where('created_at', '>=', now()->subDays($freshDays))
                ->orderBy('is_customer_request', 'desc')->orderBy('created_at', 'desc')
                ->limit(max(1, (int) ($this->option('limit') ?: config('services.email_planner.build_request_limit', 50))));
        }
        $requests = $q->get(['id', 'offer_target', 'max_reach', 'is_customer_request']);
        if ($requests->isEmpty()) {
            $this->info('Нет активных заявок.');
            return self::SUCCESS;
        }

        $categories = $this->loadCategories();
        $useNewRouting = $this->loadUseNewRouting();
        $grouper = new CampaignItemGrouper($categories, $useNewRouting, (int) config('services.email_generate.items_per_batch', 5));
        $selector = new CampaignSupplierSelector();

        $created = 0; $droppedSat = 0; $satisfied = 0; $processed = 0;

        foreach ($requests as $r) {
            $rid = (int) $r->id;
            $offerTarget = $r->offer_target !== null ? (int) $r->offer_target : $defaultTarget;
            $maxReach = (bool) $r->max_reach;

            $activeItems = $coverage->activeItemIds($rid, $offerTarget, $maxReach);

            // Заявка закрыта (target достигнут) → гасим её backlog-интенты, не заводим новых.
            if ($activeItems === [] && !$maxReach) {
                $satisfied++;
                $droppedSat += DB::connection(self::CONN)->table('send_intents')
                    ->where('request_id', $rid)->where('status', 'backlog')
                    ->update(['status' => 'dropped', 'last_reason' => 'request_satisfied', 'updated_at' => now()]);
                continue;
            }
            if ($activeItems === []) {
                continue;
            }

            // Кандидаты — профильные поставщики по АКТИВНЫМ позициям (grouper+selector).
            $items = $this->loadItems($rid, $activeItems);
            if ($items === []) {
                continue;
            }
            $candidateIds = [];
            foreach ($grouper->group($items) as $batch) {
                foreach ($selector->select($batch) as $sup) {
                    $sid = (int) ($sup['id'] ?? 0);
                    if ($sid > 0) {
                        $candidateIds[$sid] = true;
                    }
                }
            }
            if ($candidateIds === []) {
                continue;
            }

            // Дедуп: пропускаем уже контактированных по этой заявке (есть строка
            // request_item_responses = поставщику уже писали / он ответил).
            $allItemIds = DB::connection(self::CONN)->table('request_items')->where('request_id', $rid)->pluck('id')->all();
            $emailed = DB::connection(self::CONN)->table('request_item_responses')
                ->whereIn('request_item_id', $allItemIds)->distinct()->pluck('supplier_id')
                ->map(static fn ($v) => (int) $v)->flip();

            $now = now();
            $rows = [];
            foreach (array_keys($candidateIds) as $sid) {
                if (isset($emailed[$sid])) {
                    continue;
                }
                $rows[] = [
                    'request_id' => $rid,
                    'supplier_id' => $sid,
                    'status' => 'backlog',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                // insertOrIgnore — UNIQUE(request_id,supplier_id) отсекает существующие.
                $created += DB::connection(self::CONN)->table('send_intents')->insertOrIgnore($rows);
            }
            $processed++;
        }

        $this->info("Интенты: заявок обработано {$processed}, создано интентов {$created}, закрытых заявок {$satisfied} (погашено backlog {$droppedSat}).");
        return self::SUCCESS;
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
}
