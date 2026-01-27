<?php

namespace App\Http\Controllers;

use App\Models\TariffPlan;
use App\Models\BalanceHold;
use App\Models\BalanceCharge;
use App\Models\Request as RequestModel;
use App\Models\ReportAccess;
use App\Models\ItemPurchase;
use App\Models\SubscriptionCharge;
use App\Models\Invoice;
use App\Models\PromoCode;
use App\Services\TariffService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TariffController extends Controller
{
    protected $tariffService;

    public function __construct(TariffService $tariffService)
    {
        $this->tariffService = $tariffService;
    }

    /**
     * Страница "Мой тариф"
     */
    public function index()
    {
        $user = auth()->user();

        // Получаем информацию о текущем тарифе
        $currentTariff = $user->getActiveTariff();
        $limitsInfo = $this->tariffService->getUserLimitsInfo($user);

        // Получаем все доступные тарифы
        $availableTariffs = TariffPlan::active()->ordered()->get();

        return view('cabinet.tariff.index', compact('currentTariff', 'limitsInfo', 'availableTariffs'));
    }

    /**
     * Переключить тариф
     */
    public function switch(Request $request)
    {
        $request->validate([
            'tariff_plan_id' => 'required|exists:tariff_plans,id',
        ]);

        $user = auth()->user();
        $newTariffPlan = TariffPlan::findOrFail($request->tariff_plan_id);

        try {
            DB::beginTransaction();

            // Проверяем баланс
            if ($newTariffPlan->monthly_price > 0) {
                if (!$user->canAfford($newTariffPlan->monthly_price)) {
                    return back()->with('error', 'Недостаточно средств на балансе для активации тарифа. Необходимо: ' .
                        number_format($newTariffPlan->monthly_price, 2, ',', ' ') . ' ₽');
                }
            }

            // Переключаем тариф
            $this->tariffService->switchUserTariff($user, $newTariffPlan);

            DB::commit();

            return redirect()->route('cabinet.tariff.index')
                ->with('success', "Тариф успешно изменен на «{$newTariffPlan->name}»");

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to switch tariff: ' . $e->getMessage());

            return back()->with('error', 'Ошибка при смене тарифа: ' . $e->getMessage());
        }
    }

    /**
     * Детализация расходов и пополнений
     */
    public function transactions()
    {
        $user = auth()->user();

        // Собираем все транзакции из разных источников
        $transactions = collect();

        // История holds (заморозка, списание, разморозка)
        $holds = BalanceHold::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($holds as $hold) {
            // Заморозка средств
            $transactions->push([
                'created_at' => $hold->created_at,
                'type' => 'hold',
                'description' => 'Заморозка средств на обработку заявки #' . ($hold->request->request_number ?? $hold->request_id),
                'amount' => $hold->amount,
                'balance_after' => null,
            ]);

            // Списания по позициям (новая логика)
            $charges = $hold->charges()->orderBy('created_at')->get();
            foreach ($charges as $charge) {
                $transactions->push([
                    'created_at' => $charge->created_at,
                    'type' => 'charge',
                    'description' => $charge->description,
                    'amount' => $charge->amount,
                    'balance_after' => null,
                ]);
            }

            // Полное списание (старая логика, если нет charges)
            if ($hold->status === 'charged' && $hold->charged_at && $charges->isEmpty()) {
                $transactions->push([
                    'created_at' => $hold->charged_at,
                    'type' => 'charge',
                    'description' => 'Списание за обработку заявки #' . ($hold->request->request_number ?? $hold->request_id),
                    'amount' => $hold->amount,
                    'balance_after' => null,
                ]);
            }

            // Разморозка за отмену
            if ($hold->status === 'released' && $hold->released_at) {
                $transactions->push([
                    'created_at' => $hold->released_at,
                    'type' => 'release',
                    'description' => 'Возврат средств за отмененную/невыполненную заявку #' . ($hold->request->request_number ?? $hold->request_id),
                    'amount' => $hold->amount,
                    'balance_after' => null,
                ]);
            }
        }

        // Доступ к отчетам
        if (class_exists(ReportAccess::class)) {
            $reportAccesses = ReportAccess::where('user_id', $user->id)
                ->where('price', '>', 0)
                ->orderBy('accessed_at', 'desc')
                ->get();

            foreach ($reportAccesses as $access) {
                $transactions->push([
                    'created_at' => $access->accessed_at,
                    'type' => 'report_access',
                    'description' => 'Открытие отчета #' . ($access->report_number ?? $access->report_id),
                    'amount' => $access->price,
                    'balance_after' => null,
                ]);
            }
        }

        // Покупка доступа к позициям
        $itemPurchases = ItemPurchase::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($itemPurchases as $purchase) {
            $transactions->push([
                'created_at' => $purchase->created_at,
                'type' => 'item_purchase',
                'description' => 'Покупка доступа к позиции #' . $purchase->item_id,
                'amount' => $purchase->amount,
                'balance_after' => null,
            ]);
        }

        // Абонентская плата
        $subscriptionCharges = SubscriptionCharge::where('user_id', $user->id)
            ->orderBy('charged_at', 'desc')
            ->get();

        foreach ($subscriptionCharges as $charge) {
            $transactions->push([
                'created_at' => $charge->charged_at,
                'type' => 'subscription',
                'description' => $charge->description,
                'amount' => $charge->amount,
                'balance_after' => null,
            ]);
        }

        // Пополнения баланса (оплаченные счета)
        $paidInvoices = Invoice::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->orderBy('paid_at', 'desc')
            ->get();

        foreach ($paidInvoices as $invoice) {
            $transactions->push([
                'created_at' => $invoice->paid_at,
                'type' => 'top_up',
                'description' => 'Пополнение баланса по счету №' . $invoice->number,
                'amount' => -$invoice->subtotal, // Отрицательная сумма для пополнения (увеличение баланса), без НДС
                'balance_after' => null,
            ]);
        }

        // Активация промокода
        if ($user->promo_code_id && $user->promo_code_activated_at) {
            $promoCode = $user->promoCode;
            if ($promoCode) {
                $transactions->push([
                    'created_at' => $user->promo_code_activated_at,
                    'type' => 'promo_code',
                    'description' => 'Активация промокода ' . $promoCode->code,
                    'amount' => -$promoCode->amount, // Отрицательная сумма для пополнения
                    'balance_after' => null,
                ]);
            }
        }

        // Сортируем по дате (от новых к старым)
        $transactions = $transactions->sortByDesc('created_at')->values()->all();

        // Вычисляем баланс после каждой операции
        // Идем от текущего баланса назад по времени
        $balanceAfter = $user->balance; // Текущий баланс = баланс ПОСЛЕ самой новой операции

        foreach ($transactions as $key => $transaction) {
            // Записываем баланс ПОСЛЕ этой операции
            $transactions[$key]['balance_after'] = round($balanceAfter, 2);

            // Вычисляем баланс ДО этой операции (для следующей, более старой транзакции)
            if ($transaction['amount'] < 0) {
                // Это было пополнение (amount отрицательный, т.е. +1100 в балансе)
                // Значит ДО этой операции баланс был меньше на эту сумму
                $balanceAfter = $balanceAfter - abs($transaction['amount']);
            } else {
                // Это было списание (amount положительный, т.е. -98 из баланса)
                // Значит ДО этой операции баланс был больше на эту сумму
                $balanceAfter = $balanceAfter + $transaction['amount'];
            }
        }

        // Преобразуем обратно в коллекцию для пагинации
        $transactions = collect($transactions);

        // Пагинация
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $transactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $transactions->forPage($currentPage, $perPage),
            $transactions->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('cabinet.tariff.transactions', compact('user', 'transactions'));
    }

    /**
     * Детализация использования лимитов
     */
    public function limitsUsage()
    {
        $user = auth()->user();
        $tariff = $user->getActiveTariff();

        // Получаем информацию о лимитах
        $limitsInfo = $this->tariffService->getUserLimitsInfo($user);

        $itemsUsed = $limitsInfo['items_used'] ?? 0;
        $itemsLimit = $limitsInfo['items_limit'];
        $itemsRemaining = $limitsInfo['items_remaining'] ?? 0;

        $reportsUsed = $limitsInfo['reports_used'] ?? 0;
        $reportsLimit = $limitsInfo['reports_limit'];
        $reportsRemaining = $limitsInfo['reports_remaining'] ?? 0;

        // История использования лимитов
        $limitUsage = collect();

        // Заявки с позициями
        $requests = RequestModel::where('user_id', $user->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($requests as $request) {
            $itemsCount = $request->items->count();
            $overLimit = 0;
            $cost = 0;

            if ($tariff && $itemsLimit !== null && $itemsUsed > $itemsLimit) {
                $overLimit = $itemsUsed - $itemsLimit;
                $cost = $overLimit * ($tariff->tariffPlan->price_per_item_over_limit ?? 0);
            }

            $limitUsage->push([
                'date' => $request->created_at,
                'type' => 'items',
                'description' => 'Заявка #' . ($request->request_number ?? $request->id) . ' (' . $itemsCount . ' поз.)',
                'quantity' => $itemsCount,
                'cost' => $cost,
            ]);
        }

        // Доступ к отчетам
        if (class_exists(ReportAccess::class)) {
            $reportAccesses = ReportAccess::where('user_id', $user->id)
                ->orderBy('accessed_at', 'desc')
                ->get();

            foreach ($reportAccesses as $access) {
                $limitUsage->push([
                    'date' => $access->accessed_at,
                    'type' => 'report',
                    'description' => 'Открытие отчета #' . ($access->report_number ?? $access->report_id),
                    'quantity' => 1,
                    'cost' => $access->price ?? 0,
                ]);
            }
        }

        // Покупка доступа к позициям
        $itemPurchases = ItemPurchase::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($itemPurchases as $purchase) {
            $limitUsage->push([
                'date' => $purchase->created_at,
                'type' => 'report',
                'description' => 'Покупка позиции #' . $purchase->item_id,
                'quantity' => 1,
                'cost' => $purchase->amount ?? 0,
            ]);
        }

        // Сортируем по дате
        $limitUsage = $limitUsage->sortByDesc('date');

        // Пагинация
        $perPage = 20;
        $currentPage = request()->get('page', 1);
        $limitUsage = new \Illuminate\Pagination\LengthAwarePaginator(
            $limitUsage->forPage($currentPage, $perPage),
            $limitUsage->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('cabinet.tariff.limits-usage', compact(
            'tariff',
            'itemsUsed',
            'itemsLimit',
            'itemsRemaining',
            'reportsUsed',
            'reportsLimit',
            'reportsRemaining',
            'limitUsage'
        ));
    }

    /**
     * Применить промокод
     */
    public function applyPromoCode(Request $request)
    {
        $request->validate([
            'promo_code' => 'required|string|max:50',
        ]);

        $user = auth()->user();

        // Проверяем, не использовал ли пользователь уже промокод
        if ($user->promo_code_id) {
            return back()->with('error', 'Вы уже активировали промокод ранее');
        }

        // Ищем промокод
        $promoCode = PromoCode::where('code', strtoupper($request->promo_code))
            ->where('is_used', false)
            ->first();

        if (!$promoCode) {
            return back()->with('error', 'Промокод не найден или уже использован');
        }

        DB::transaction(function () use ($user, $promoCode) {
            // Активируем промокод
            $promoCode->activate($user);

            // Начисляем баланс
            $user->increment('balance', $promoCode->amount);
            $user->update([
                'promo_code_id' => $promoCode->id,
                'promo_code_activated_at' => now(),
                'has_promo_priority' => true,
            ]);
        });

        return back()->with('success', "Промокод успешно активирован! На ваш баланс зачислено {$promoCode->amount} ₽");
    }
}
