<?php

namespace App\Console\Commands;

use App\Models\BalanceHold;
use App\Models\Request;
use App\Models\ExternalRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredBalanceHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:release-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release balance holds for requests that received less than 3 offers within a week';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired balance holds...');

        try {
            // Получаем все активные заморозки старше 7 дней
            $expiredHolds = BalanceHold::where('status', 'held')
                ->where('created_at', '<=', now()->subWeek())
                ->with(['request', 'user', 'charges'])
                ->get();

            $fullyReleasedCount = 0;
            $partiallyReleasedCount = 0;

            foreach ($expiredHolds as $hold) {
                $request = $hold->request;
                if (!$request || !$request->request_number) {
                    continue;
                }

                // Находим соответствующую заявку в БД reports
                $externalRequest = ExternalRequest::where('request_number', $request->request_number)->first();

                if (!$externalRequest) {
                    Log::warning("ReleaseExpiredBalanceHolds: Не найдена external заявка для request_number={$request->request_number}");
                    continue;
                }

                DB::beginTransaction();
                try {
                    // Получаем все позиции заявки
                    $items = $externalRequest->items;
                    $totalItems = $items->count();

                    // Уже списанная сумма
                    $alreadyCharged = $hold->getChargedAmount();

                    // Оставшаяся замороженная сумма
                    $remainingAmount = $hold->getRemainingAmount();

                    if ($remainingAmount <= 0) {
                        // Все средства уже списаны, переводим в статус charged
                        $hold->update([
                            'status' => 'charged',
                            'charged_at' => now(),
                        ]);
                        $this->line("Заявка {$request->request_number}: все средства уже списаны ({$alreadyCharged} ₽)");
                        DB::commit();
                        continue;
                    }

                    // Подсчитываем позиции с 3+ ответами
                    $itemsWithEnoughOffers = $items->filter(fn($item) => $item->offers_count >= 3)->count();
                    $itemsWithoutEnoughOffers = $totalItems - $itemsWithEnoughOffers;

                    if ($itemsWithoutEnoughOffers === 0) {
                        // Все позиции набрали 3+ ответов, но средства ещё не списаны полностью
                        // Ждём списания через Observer
                        $this->line("Заявка {$request->request_number}: все {$totalItems} позиции набрали 3+ ответов, ждём полного списания (осталось {$remainingAmount} ₽)");
                        DB::commit();
                        continue;
                    }

                    if ($itemsWithEnoughOffers === 0) {
                        // НИ ОДНА позиция не набрала 3+ ответов - размораживаем ВСЮ оставшуюся сумму
                        $hold->update([
                            'status' => 'released',
                            'released_at' => now(),
                        ]);

                        // Возвращаем средства пользователю
                        $hold->user->increment('balance', $remainingAmount);

                        $this->info("✓ Полная разморозка {$remainingAmount} ₽ для заявки {$request->request_number} (0/{$totalItems} позиций выполнено)");
                        $fullyReleasedCount++;
                    } else {
                        // ЧАСТИЧНОЕ выполнение: X позиций с 3+ ответами, N-X без ответов
                        // Размораживаем только средства за невыполненные позиции

                        // Рассчитываем сумму за 1 позицию (средняя стоимость)
                        $costPerItem = $hold->amount / $totalItems;

                        // Сумма за невыполненные позиции
                        $amountToRelease = $costPerItem * $itemsWithoutEnoughOffers;

                        // Если уже списано больше чем планировалось - не размораживаем
                        if ($amountToRelease > $remainingAmount) {
                            $amountToRelease = $remainingAmount;
                        }

                        if ($amountToRelease > 0) {
                            // Возвращаем средства за невыполненные позиции
                            $hold->user->increment('balance', $amountToRelease);

                            // Обновляем сумму заморозки
                            $newHoldAmount = $hold->amount - $amountToRelease;
                            $hold->update(['amount' => $newHoldAmount]);

                            $this->info("✓ Частичная разморозка {$amountToRelease} ₽ для заявки {$request->request_number} ({$itemsWithEnoughOffers}/{$totalItems} позиций выполнено, списано {$alreadyCharged} ₽)");
                            $partiallyReleasedCount++;
                        }

                        // Если все средства вернули/списали - меняем статус
                        if ($hold->getRemainingAmount() <= 0) {
                            $hold->update([
                                'status' => 'charged',
                                'charged_at' => now(),
                            ]);
                        }
                    }

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("ReleaseExpiredBalanceHolds: Ошибка при разморозке hold_id={$hold->id}: " . $e->getMessage());
                    $this->error("Ошибка при разморозке hold_id={$hold->id}");
                }
            }

            $this->info("Обработано: {$expiredHolds->count()} заморозок");
            $this->info("Разморожено полностью: {$fullyReleasedCount}");
            $this->info("Разморожено частично: {$partiallyReleasedCount}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ошибка: ' . $e->getMessage());
            Log::error('ReleaseExpiredBalanceHolds failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
