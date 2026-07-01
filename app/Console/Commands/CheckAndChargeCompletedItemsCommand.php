<?php

namespace App\Console\Commands;

use App\Models\BalanceHold;
use App\Models\Request;
use App\Models\ExternalRequest;
use App\Models\ExternalRequestItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAndChargeCompletedItemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:check-completed-items
        {--request-number= : Проверить конкретную заявку по номеру (web-flow)}
        {--user= : Ограничить обработку одним пользователем (id или email)}
        {--dry-run : Ничего не списывать, только показать что было бы списано}
        {--force : Обработать API-холды даже если флаг BILLING_API_RECONCILE_ENABLED=false}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить и списать средства за выполненные позиции (с 3+ валидными предложениями)';

    private bool $dryRun = false;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->dryRun = (bool) $this->option('dry-run');
        if ($this->dryRun) {
            $this->warn('[DRY-RUN] Списаний не будет — только расчёт.');
        }

        $this->info('Проверка выполненных позиций для списания средств...');

        $requestNumber = $this->option('request-number');

        if ($requestNumber) {
            // Проверяем конкретную заявку (web-flow).
            $this->processRequest($requestNumber);
        } else {
            // Проверяем все активные заморозки.
            $this->processAllActiveHolds();
        }

        return Command::SUCCESS;
    }

    /**
     * Обработать конкретную заявку
     */
    private function processRequest(string $requestNumber): void
    {
        $request = Request::where('request_number', $requestNumber)->first();

        if (!$request) {
            $this->error("Заявка {$requestNumber} не найдена в основной БД");
            return;
        }

        $externalRequest = ExternalRequest::where('request_number', $requestNumber)->first();

        if (!$externalRequest) {
            $this->error("Заявка {$requestNumber} не найдена в БД reports");
            return;
        }

        $balanceHold = $request->balanceHold;

        if (!$balanceHold || $balanceHold->status !== 'held') {
            $this->warn("У заявки {$requestNumber} нет активной заморозки средств");
            return;
        }

        $this->info("Проверка заявки {$requestNumber}...");
        $this->processHold($balanceHold, $externalRequest);
    }

    /**
     * Обработать все активные заморозки
     */
    private function processAllActiveHolds(): void
    {
        $userFilter = $this->resolveUserFilter();

        // === Web-flow заморозки (одна заморозка на заявку, request_id задан). ===
        $webHolds = BalanceHold::where('status', 'held')
            ->whereNotNull('request_id')
            ->when($userFilter, fn ($q) => $q->where('user_id', $userFilter))
            ->with(['request', 'user'])
            ->get();

        $this->info("Web-flow активных заморозок: {$webHolds->count()}");

        $totalCharged = 0;

        foreach ($webHolds as $hold) {
            $request = $hold->request;
            if (!$request || !$request->request_number) {
                continue;
            }

            $externalRequest = ExternalRequest::where('request_number', $request->request_number)->first();

            if (!$externalRequest) {
                continue;
            }

            $charged = $this->processHold($hold, $externalRequest);
            $totalCharged += $charged;
        }

        $this->info("Web-flow: списано позиций: {$totalCharged}");

        // === API-flow заморозки (попозиционные, api_submission_id задан). ===
        $this->processApiHolds($userFilter);
    }

    /**
     * API-flow: попозиционные заморозки (api_submission_id задан, одна заморозка =
     * одна позиция). Резолвим позицию reports по balance_holds.request_item_id
     * (проставляется в PromotionService при промоушене staging → reports).
     *
     * Отдельная ветка, потому что:
     *  - у API-холдов request_id=NULL → они выпадают из web-цикла ($hold->request null);
     *  - списываем ЗАМОРОЖЕННУЮ сумму холда (hold->amount), а не getItemCost() —
     *    это цена позиции, зафиксированная при приёме submission (с корректным
     *    попозиционным учётом лимита), устойчивая к месячному сбросу items_used;
     *  - ветка новая/непроверенная → под флагом BILLING_API_RECONCILE_ENABLED.
     */
    private function processApiHolds(?int $userFilter): void
    {
        $enabled = (bool) config('services.billing.api_reconcile.enabled', false);
        $force = (bool) $this->option('force');

        $apiHolds = BalanceHold::where('status', 'held')
            ->whereNotNull('api_submission_id')
            ->whereNotNull('request_item_id')
            ->where('amount', '>', 0)
            ->when($userFilter, fn ($q) => $q->where('user_id', $userFilter))
            ->with('user')
            ->get();

        $this->info("API-flow активных заморозок (промоутнутых): {$apiHolds->count()}");

        if (!$this->dryRun && !$enabled && !$force) {
            $this->warn('API-биллинг выключен (BILLING_API_RECONCILE_ENABLED=false). '
                . 'Прогоните --dry-run для сверки, затем включите флаг или используйте --force.');
            return;
        }

        $chargedCount = 0;
        $chargedSum = 0.0;
        $wouldCount = 0;
        $wouldSum = 0.0;

        foreach ($apiHolds as $hold) {
            $item = ExternalRequestItem::find($hold->request_item_id);
            if (!$item) {
                Log::warning("check-completed-items: API hold_id={$hold->id} указывает на несуществующую позицию request_item_id={$hold->request_item_id}");
                continue;
            }

            if ($this->validOffersCount($item) < 3) {
                continue; // Позиция ещё не выполнена.
            }

            // Идемпотентность: уже списывали за эту позицию?
            $already = $hold->charges()
                ->where('external_request_item_id', $item->id)
                ->exists();
            if ($already) {
                continue;
            }

            $amount = (float) $hold->amount;

            if ($this->dryRun) {
                $wouldCount++;
                $wouldSum += $amount;
                $this->line("  [dry] позиция #{$item->position_number} (item_id={$item->id}, hold_id={$hold->id}): списалось бы {$amount} ₽");
                continue;
            }

            try {
                $hold->chargeForItem(
                    $item->id,
                    $amount,
                    "Списание за выполненную позицию #{$item->position_number} (API submission #{$hold->api_submission_id})"
                );
                $chargedCount++;
                $chargedSum += $amount;
                $this->info("  ✓ Списано {$amount} ₽ за API-позицию #{$item->position_number} (item_id={$item->id})");
            } catch (\Throwable $e) {
                $this->error("  ✗ Ошибка при списании за API-позицию item_id={$item->id}: " . $e->getMessage());
                Log::error("check-completed-items API: hold_id={$hold->id} item_id={$item->id}: " . $e->getMessage());
            }
        }

        if ($this->dryRun) {
            $this->warn("API-flow [dry-run]: списалось бы {$wouldCount} позиций на {$wouldSum} ₽");
        } else {
            $this->info("API-flow: списано {$chargedCount} позиций на {$chargedSum} ₽");
        }
    }

    /**
     * Обработать одну заморозку (web-flow: одна заморозка на всю заявку).
     */
    private function processHold(BalanceHold $hold, ExternalRequest $externalRequest): int
    {
        $chargedCount = 0;
        $request = $hold->request;
        $user = $hold->user;

        // Получаем активный тариф пользователя
        $tariff = $user->getActiveTariff();

        if (!$tariff) {
            $this->warn("У пользователя {$user->email} нет активного тарифа");
            return 0;
        }

        $itemCost = $tariff->tariffPlan->getItemCost($user);

        // Проходим по всем позициям заявки
        foreach ($externalRequest->items as $item) {
            if ($this->validOffersCount($item) < 3) {
                continue; // Позиция не выполнена
            }

            // Проверяем, не списывали ли уже за эту позицию
            $existingCharge = $hold->charges()
                ->where('external_request_item_id', $item->id)
                ->first();

            if ($existingCharge) {
                continue; // Уже списано
            }

            if ($this->dryRun) {
                $this->line("  [dry] позиция #{$item->position_number} (item_id={$item->id}): списалось бы {$itemCost} ₽");
                $chargedCount++;
                continue;
            }

            // Списываем средства за эту позицию
            try {
                $hold->chargeForItem(
                    $item->id,
                    $itemCost,
                    "Списание за выполненную позицию #{$item->position_number} в заявке {$request->request_number}"
                );

                $this->info("  ✓ Списано {$itemCost} ₽ за позицию #{$item->position_number}");
                $chargedCount++;
            } catch (\Exception $e) {
                $this->error("  ✗ Ошибка при списании за позицию #{$item->position_number}: " . $e->getMessage());
                Log::error("CheckAndChargeCompletedItems: " . $e->getMessage());
            }
        }

        if ($chargedCount > 0) {
            $this->info("Заявка {$request->request_number}: позиций к списанию: {$chargedCount}");
        }

        return $chargedCount;
    }

    /**
     * Количество ВАЛИДНЫХ предложений по позиции (status received/processed, цена > 0).
     * Единое определение для web- и API-веток и для release-команды.
     */
    private function validOffersCount(ExternalRequestItem $item): int
    {
        return $item->offers()
            ->whereIn('status', ['received', 'processed'])
            ->whereNotNull('price_per_unit')
            ->where('price_per_unit', '>', 0)
            ->count();
    }

    /**
     * Резолвит --user (id или email) в user_id. Возвращает null если опция не задана.
     */
    private function resolveUserFilter(): ?int
    {
        $opt = $this->option('user');
        if (!$opt) {
            return null;
        }
        if (ctype_digit((string) $opt)) {
            return (int) $opt;
        }
        $user = \App\Models\User::where('email', $opt)->first();
        if (!$user) {
            $this->error("Пользователь {$opt} не найден — фильтр игнорируется.");
            return null;
        }
        return (int) $user->id;
    }
}
