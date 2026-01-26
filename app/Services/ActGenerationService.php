<?php

namespace App\Services;

use App\Models\Act;
use App\Models\ActItem;
use App\Models\User;
use App\Models\BalanceCharge;
use App\Models\SubscriptionCharge;
use App\Models\ReportAccess;
use Carbon\Carbon;

class ActGenerationService
{
    /**
     * Сформировать акт за указанный месяц для пользователя
     */
    public function generateForPeriod(User $user, int $year, int $month): ?Act
    {
        // Проверяем, не существует ли уже акт за этот период
        $existingAct = Act::where('user_id', $user->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existingAct) {
            \Log::warning("Act for user {$user->id} for period {$year}-{$month} already exists");
            return $existingAct;
        }

        // Собираем все списания за период
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();

        // 1. Абонентская плата (списания subscription_charges за период)
        $subscriptionCharges = SubscriptionCharge::where('user_id', $user->id)
            ->whereBetween('charged_at', [$periodStart, $periodEnd])
            ->get();

        // 2. Ценовой мониторинг (balance_charges за выполненные позиции заявок)
        $balanceCharges = BalanceCharge::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->whereNotNull('external_request_item_id') // Только за позиции заявок
            ->get();

        // 3. Доступ к отчетам (report_accesses за период)
        $reportAccesses = ReportAccess::where('user_id', $user->id)
            ->whereBetween('accessed_at', [$periodStart, $periodEnd])
            ->get();

        // Если нет списаний - акт не формируем
        if ($subscriptionCharges->isEmpty() && $balanceCharges->isEmpty() && $reportAccesses->isEmpty()) {
            \Log::info("No charges found for user {$user->id} for period {$year}-{$month}");
            return null;
        }

        // Создаем акт
        $act = Act::create([
            'user_id' => $user->id,
            'number' => Act::generateNumber(),
            'act_date' => Carbon::create($year, $month, 1)->addMonth()->day(10), // 10 число следующего месяца
            'period_year' => $year,
            'period_month' => $month,
            'vat_rate' => 5.00,
            'status' => 'draft',
        ]);

        $sortOrder = 1;

        // Добавляем позиции абонентской платы
        foreach ($subscriptionCharges as $charge) {
            $tariffName = $charge->tariff_plan_name ?? 'Неизвестный тариф';
            $periodName = Carbon::parse($charge->charged_at)->locale('ru')->isoFormat('MMMM YYYY');

            ActItem::create([
                'act_id' => $act->id,
                'type' => 'subscription',
                'subscription_charge_id' => $charge->id,
                'sort_order' => $sortOrder++,
                'name' => "Абонентская плата за тарифный план «{$tariffName}» информационного сервиса IQOT за {$periodName} согласно Договору-оферте, размещённому на сайте iqot.ru",
                'unit' => 'шт',
                'quantity' => 1,
                'price' => $charge->amount,
            ]);
        }

        // Группируем balance_charges для подсчета количества позиций
        if ($balanceCharges->isNotEmpty()) {
            $totalAmount = $balanceCharges->sum('amount');
            $totalCount = $balanceCharges->count();
            $periodName = Carbon::create($year, $month, 1)->locale('ru')->isoFormat('MMMM YYYY');

            ActItem::create([
                'act_id' => $act->id,
                'type' => 'price_monitoring',
                'sort_order' => $sortOrder++,
                'name' => "Услуги ценового мониторинга информационного сервиса IQOT за {$periodName} ({$totalCount} позиций) согласно Договору-оферте, размещённому на сайте iqot.ru",
                'unit' => 'шт',
                'quantity' => $totalCount,
                'price' => $totalAmount / $totalCount,
            ]);
        }

        // Группируем report_accesses
        if ($reportAccesses->isNotEmpty()) {
            $totalAmount = $reportAccesses->sum('price');
            $totalCount = $reportAccesses->count();
            $periodName = Carbon::create($year, $month, 1)->locale('ru')->isoFormat('MMMM YYYY');

            ActItem::create([
                'act_id' => $act->id,
                'type' => 'catalog_access',
                'sort_order' => $sortOrder++,
                'name' => "Предоставление доступа к отчётам каталога информационного сервиса IQOT за {$periodName} ({$totalCount} отчётов) согласно Договору-оферте, размещённому на сайте iqot.ru",
                'unit' => 'шт',
                'quantity' => $totalCount,
                'price' => $totalAmount / $totalCount,
            ]);
        }

        // Пересчитываем итоги
        $act->recalculate();
        $act->update(['status' => 'generated', 'generated_at' => now()]);

        \Log::info("Act #{$act->number} generated for user {$user->id} for period {$year}-{$month}");

        return $act;
    }

    /**
     * Сформировать акты за месяц для всех пользователей
     */
    public function generateForAllUsers(int $year, int $month): array
    {
        $users = User::whereHas('balanceCharges', function ($query) use ($year, $month) {
            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
            $query->whereBetween('created_at', [$periodStart, $periodEnd]);
        })->orWhereHas('subscriptionCharges', function ($query) use ($year, $month) {
            $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
            $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
            $query->whereBetween('charged_at', [$periodStart, $periodEnd]);
        })->get();

        $generated = [];
        foreach ($users as $user) {
            $act = $this->generateForPeriod($user, $year, $month);
            if ($act) {
                $generated[] = $act;
            }
        }

        return $generated;
    }
}
