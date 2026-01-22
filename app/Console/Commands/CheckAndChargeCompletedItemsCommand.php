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
    protected $signature = 'balance:check-completed-items {--request-number= : Проверить конкретную заявку по номеру}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Проверить и списать средства за выполненные позиции (с 3+ валидными предложениями)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Проверка выполненных позиций для списания средств...');

        $requestNumber = $this->option('request-number');

        if ($requestNumber) {
            // Проверяем конкретную заявку
            $this->processRequest($requestNumber);
        } else {
            // Проверяем все активные заморозки
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
        $activeHolds = BalanceHold::where('status', 'held')
            ->with(['request', 'user'])
            ->get();

        $this->info("Найдено активных заморозок: {$activeHolds->count()}");

        $totalCharged = 0;

        foreach ($activeHolds as $hold) {
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

        $this->info("Итого списано позиций: {$totalCharged}");
    }

    /**
     * Обработать одну заморозку
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
            // Проверяем количество ВАЛИДНЫХ предложений (с ценой > 0)
            $validOffersCount = $item->offers()
                ->whereIn('status', ['received', 'processed'])
                ->whereNotNull('price_per_unit')
                ->where('price_per_unit', '>', 0)
                ->count();

            if ($validOffersCount < 3) {
                continue; // Позиция не выполнена
            }

            // Проверяем, не списывали ли уже за эту позицию
            $existingCharge = $hold->charges()
                ->where('external_request_item_id', $item->id)
                ->first();

            if ($existingCharge) {
                continue; // Уже списано
            }

            // Списываем средства за эту позицию
            try {
                $hold->chargeForItem(
                    $item->id,
                    $itemCost,
                    "Списание за выполненную позицию #{$item->position_number} в заявке {$request->request_number}"
                );

                $this->info("  ✓ Списано {$itemCost} ₽ за позицию #{$item->position_number} ({$validOffersCount} предложений)");
                $chargedCount++;
            } catch (\Exception $e) {
                $this->error("  ✗ Ошибка при списании за позицию #{$item->position_number}: " . $e->getMessage());
                Log::error("CheckAndChargeCompletedItems: " . $e->getMessage());
            }
        }

        if ($chargedCount > 0) {
            $this->info("Заявка {$request->request_number}: списано {$chargedCount} позиций");
        }

        return $chargedCount;
    }
}
