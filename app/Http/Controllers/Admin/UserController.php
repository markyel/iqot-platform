<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceCharge;
use App\Models\ItemPurchase;
use App\Models\ReportAccess;
use App\Models\SubscriptionCharge;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Admin filter
        if ($request->has('is_admin')) {
            $query->where('is_admin', $request->get('is_admin') == 1);
        }

        $users = $query->with('tariffs.tariffPlan')->orderBy('created_at', 'desc')->paginate(20);

        // Load purchase counts
        foreach ($users as $user) {
            $user->purchases_count = ItemPurchase::where('user_id', $user->id)->count();

            // Суммируем все расходы: balance_charges + report_accesses + item_purchases + subscription_charges
            $balanceChargesSum = BalanceCharge::where('user_id', $user->id)->sum('amount');
            $reportAccessesSum = ReportAccess::where('user_id', $user->id)->where('price', '>', 0)->sum('price');
            $itemPurchasesSum = ItemPurchase::where('user_id', $user->id)->sum('amount');
            $subscriptionChargesSum = SubscriptionCharge::where('user_id', $user->id)->sum('amount');

            $user->purchases_sum = $balanceChargesSum + $reportAccessesSum + $itemPurchasesSum + $subscriptionChargesSum;
        }

        return view('admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load(['requests', 'balanceHolds']);
        $user->purchases_count = ItemPurchase::where('user_id', $user->id)->count();

        // Суммируем все расходы: balance_charges + report_accesses + item_purchases + subscription_charges
        $balanceChargesSum = BalanceCharge::where('user_id', $user->id)->sum('amount');
        $reportAccessesSum = ReportAccess::where('user_id', $user->id)->where('price', '>', 0)->sum('price');
        $itemPurchasesSum = ItemPurchase::where('user_id', $user->id)->sum('amount');
        $subscriptionChargesSum = SubscriptionCharge::where('user_id', $user->id)->sum('amount');

        $user->purchases_sum = $balanceChargesSum + $reportAccessesSum + $itemPurchasesSum + $subscriptionChargesSum;

        // Тариф и лимиты
        $tariff = $user->getActiveTariff();
        $limitsInfo = $tariff ? app(\App\Services\TariffService::class)->getUserLimitsInfo($user) : null;

        // Статистика по заявкам
        $requestsStats = [
            'total' => $user->requests()->count(),
            'draft' => $user->requests()->where('status', 'draft')->count(),
            'pending' => $user->requests()->where('status', 'pending')->count(),
            'completed' => $user->requests()->where('status', 'completed')->count(),
            'cancelled' => $user->requests()->where('status', 'cancelled')->count(),
        ];

        // Статистика по отчетам
        $reportAccessCount = \App\Models\ReportAccess::where('user_id', $user->id)->count();
        $itemPurchasesCount = ItemPurchase::where('user_id', $user->id)->count();

        // Статистика по счетам
        $invoicesCount = \App\Models\Invoice::where('user_id', $user->id)->count();

        // Статистика по актам
        $actsCount = \App\Models\Act::where('user_id', $user->id)->count();

        // Транзакции (аналогично TariffController::transactions)
        $transactions = $this->getUserTransactions($user);

        return view('admin.users.show', compact('user', 'tariff', 'limitsInfo', 'requestsStats', 'reportAccessCount', 'itemPurchasesCount', 'invoicesCount', 'actsCount', 'transactions'));
    }

    private function getUserTransactions(User $user)
    {
        // Собираем все транзакции из разных источников
        $transactions = collect();

        // История holds (заморозка, списание, разморозка)
        $holds = \App\Models\BalanceHold::where('user_id', $user->id)
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
        $paidInvoices = \App\Models\Invoice::where('user_id', $user->id)
            ->whereIn('status', ['paid', 'closed'])
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

        // Преобразуем обратно в коллекцию
        return collect($transactions);
    }

    /**
     * Список счетов пользователя
     */
    public function invoices(User $user)
    {
        $invoices = \App\Models\Invoice::where('user_id', $user->id)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return view('admin.users.invoices', compact('user', 'invoices'));
    }

    public function updateBalance(Request $request, User $user)
    {
        $request->validate([
            'action' => 'required|in:add,subtract,set',
            'amount' => 'required|numeric|min:0',
        ]);

        $amount = (float) $request->amount;

        switch ($request->action) {
            case 'add':
                $user->increment('balance', $amount);
                break;
            case 'subtract':
                $user->decrement('balance', $amount);
                break;
            case 'set':
                $user->update(['balance' => $amount]);
                break;
        }

        return redirect()->back()->with('success', 'Баланс пользователя обновлен');
    }
}
