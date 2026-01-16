<?php

namespace App\Services;

use App\Models\TariffPlan;
use App\Models\User;
use App\Models\UserTariff;
use Illuminate\Support\Facades\DB;

class TariffService
{
    /**
     * Назначить стартовый тариф новому пользователю
     */
    public function assignStartTariffToNewUser(User $user): ?UserTariff
    {
        $startTariff = TariffPlan::getStartTariff();

        if (!$startTariff) {
            \Log::warning('Start tariff not found, cannot assign to user ' . $user->id);
            return null;
        }

        return $user->assignTariff($startTariff);
    }

    /**
     * Обновить тарифы, у которых истек срок
     */
    public function renewExpiredTariffs(): int
    {
        $count = 0;

        // Находим тарифы, у которых истек срок
        $expiredTariffs = UserTariff::with(['user', 'tariffPlan'])
            ->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredTariffs as $userTariff) {
            try {
                DB::beginTransaction();

                $user = $userTariff->user;
                $tariffPlan = $userTariff->tariffPlan;

                // Проверяем баланс пользователя
                if ($tariffPlan->monthly_price > 0) {
                    if (!$user->canAfford($tariffPlan->monthly_price)) {
                        // Недостаточно средств - переводим на бесплатный тариф
                        $this->downgradeToStartTariff($user, 'Недостаточно средств для продления тарифа');
                        \Log::info("User {$user->id} downgraded to start tariff due to insufficient balance");
                        continue;
                    }

                    // Списываем средства
                    $user->decrement('balance', $tariffPlan->monthly_price);
                }

                // Обнуляем использованные лимиты
                $userTariff->resetLimits();

                // Продлеваем период
                $userTariff->extendForMonth();

                DB::commit();
                $count++;

                \Log::info("Renewed tariff for user {$user->id}");
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("Failed to renew tariff for user {$userTariff->user_id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Перевести пользователя на стартовый тариф
     */
    public function downgradeToStartTariff(User $user, string $reason = null): UserTariff
    {
        $startTariff = TariffPlan::getStartTariff();

        if (!$startTariff) {
            throw new \Exception('Start tariff not found');
        }

        // Деактивируем текущий тариф
        $currentTariff = $user->getActiveTariff();
        if ($currentTariff) {
            $currentTariff->deactivate();
        }

        // Назначаем стартовый тариф
        $newTariff = $user->assignTariff($startTariff);

        if ($reason) {
            \Log::info("User {$user->id} downgraded to start tariff: {$reason}");
        }

        return $newTariff;
    }

    /**
     * Переключить пользователя на новый тариф
     */
    public function switchUserTariff(User $user, TariffPlan $newTariffPlan): UserTariff
    {
        DB::beginTransaction();

        try {
            $newTariff = $user->switchTariff($newTariffPlan);

            DB::commit();

            \Log::info("User {$user->id} switched to tariff {$newTariffPlan->code}");

            return $newTariff;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Использовать лимиты при создании заявки
     */
    public function useRequestLimits(User $user, int $itemsCount): void
    {
        $activeTariff = $user->getActiveTariff();

        if ($activeTariff) {
            $activeTariff->useItems($itemsCount);
        }
    }

    /**
     * Использовать лимиты при открытии отчета
     */
    public function useReportLimits(User $user): void
    {
        $activeTariff = $user->getActiveTariff();

        if ($activeTariff) {
            $activeTariff->useReport();
        }
    }

    /**
     * Получить информацию о лимитах пользователя
     */
    public function getUserLimitsInfo(User $user): array
    {
        $activeTariff = $user->getActiveTariff();

        if (!$activeTariff) {
            return [
                'has_tariff' => false,
                'tariff_name' => null,
                'items_limit' => null,
                'items_used' => 0,
                'items_remaining' => null,
                'reports_limit' => null,
                'reports_used' => 0,
                'reports_remaining' => null,
                'expires_at' => null,
                'days_left' => null,
            ];
        }

        $tariffPlan = $activeTariff->tariffPlan;

        return [
            'has_tariff' => true,
            'tariff_name' => $tariffPlan->name,
            'tariff_code' => $tariffPlan->code,
            'items_limit' => $tariffPlan->items_limit,
            'items_used' => $activeTariff->items_used,
            'items_remaining' => $tariffPlan->items_limit !== null
                ? max(0, $tariffPlan->items_limit - $activeTariff->items_used)
                : null, // null = безлимит
            'items_used_percentage' => $activeTariff->getItemsUsedPercentage(),
            'reports_limit' => $tariffPlan->reports_limit,
            'reports_used' => $activeTariff->reports_used,
            'reports_remaining' => $tariffPlan->reports_limit !== null
                ? max(0, $tariffPlan->reports_limit - $activeTariff->reports_used)
                : null, // null = безлимит
            'reports_used_percentage' => $activeTariff->getReportsUsedPercentage(),
            'expires_at' => $activeTariff->expires_at,
            'days_left' => $activeTariff->getDaysLeft(),
            'monthly_price' => $tariffPlan->monthly_price,
        ];
    }

    /**
     * Проверить возможность создания заявки
     */
    public function checkRequestCreationAbility(User $user, int $itemsCount): array
    {
        $activeTariff = $user->getActiveTariff();

        if (!$activeTariff) {
            return [
                'can_create' => false,
                'reason' => 'У вас нет активного тарифа',
                'cost' => 0,
                'items_over_limit' => 0,
            ];
        }

        $canCreate = $activeTariff->canCreateRequest($itemsCount);
        $cost = $activeTariff->calculateRequestCost($itemsCount);
        $itemsOverLimit = $activeTariff->getItemsOverLimit($itemsCount);

        if (!$canCreate && $itemsOverLimit > 0) {
            // Проверяем баланс для оплаты сверх лимита
            if (!$user->canAfford($cost)) {
                return [
                    'can_create' => false,
                    'reason' => "Превышен лимит на {$itemsOverLimit} позиций. Недостаточно средств на балансе для оплаты сверх лимита.",
                    'cost' => $cost,
                    'items_over_limit' => $itemsOverLimit,
                    'balance_needed' => $cost - $user->getAvailableBalanceAttribute(),
                ];
            }

            return [
                'can_create' => true,
                'reason' => "Превышен лимит на {$itemsOverLimit} позиций. С вашего баланса будет списано {$cost} ₽.",
                'cost' => $cost,
                'items_over_limit' => $itemsOverLimit,
            ];
        }

        return [
            'can_create' => true,
            'reason' => 'В пределах лимита тарифного плана',
            'cost' => $cost,
            'items_over_limit' => $itemsOverLimit,
        ];
    }
}
