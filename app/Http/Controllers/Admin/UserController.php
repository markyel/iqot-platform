<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceCharge;
use App\Models\ItemPurchase;
use App\Models\ReportAccess;
use App\Models\SubscriptionCharge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            $isApi = !empty($hold->api_submission_id);
            $refLabel = $this->holdRefLabel($hold); // «заявки #REQ-…» или «API-submission sub_XXXX (ref=…)»

            // Заморозка средств
            $transactions->push([
                'created_at' => $hold->created_at,
                'type' => 'hold',
                'description' => 'Заморозка средств на обработку ' . $refLabel,
                'amount' => $hold->amount,
                'balance_after' => null,
                'source' => $isApi ? 'api' : 'web',
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
                    'source' => $isApi ? 'api' : 'web',
                ]);
            }

            // Полное списание (старая логика, если нет charges)
            if ($hold->status === 'charged' && $hold->charged_at && $charges->isEmpty()) {
                $transactions->push([
                    'created_at' => $hold->charged_at,
                    'type' => 'charge',
                    'description' => 'Списание за обработку ' . $refLabel,
                    'amount' => $hold->amount,
                    'balance_after' => null,
                    'source' => $isApi ? 'api' : 'web',
                ]);
            }

            // Разморозка за отмену
            if ($hold->status === 'released' && $hold->released_at) {
                $transactions->push([
                    'created_at' => $hold->released_at,
                    'type' => 'release',
                    'description' => 'Возврат средств за отменённую/невыполненную ' . $refLabel,
                    'amount' => $hold->amount,
                    'balance_after' => null,
                    'source' => $isApi ? 'api' : 'web',
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

        // Преобразуем обратно в коллекцию
        return collect($transactions);
    }

    /**
     * Человеко-читаемая ссылка на объект, к которому относится hold.
     *  - Для web: «заявки #REQ-…»
     *  - Для API: «API-заявки sub_… (ref=…)»
     */
    private function holdRefLabel(\App\Models\BalanceHold $hold): string
    {
        if (!empty($hold->api_submission_id)) {
            $sub = \App\Models\Api\ApiSubmission::find($hold->api_submission_id);
            if (!$sub) {
                return 'API-заявки #' . $hold->api_submission_id;
            }
            $parts = ['API-заявки sub_' . $sub->external_id];
            if ($sub->client_ref) {
                $parts[] = 'ref=' . $sub->client_ref;
            }
            return implode(' ', $parts);
        }

        return 'заявки #' . ($hold->request->request_number ?? $hold->request_id);
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

    /**
     * Детализация списаний/операций пользователя за период (для админа).
     * Переиспользует агрегатор getUserTransactions() и фильтрует по датам.
     * Время — по серверу (app.timezone, UTC).
     */
    public function transactions(Request $request, User $user)
    {
        // Период: по умолчанию текущий календарный месяц
        $from = $request->filled('from')
            ? Carbon::parse($request->get('from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->filled('to')
            ? Carbon::parse($request->get('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        // Полная лента (с рассчитанным balance_after), затем срез по периоду
        $all = $this->getUserTransactions($user);
        $periodAll = $all->filter(function ($t) use ($from, $to) {
            $dt = $t['created_at'] ?? null;
            return $dt && !$dt->lt($from) && !$dt->gt($to);
        })->values();

        // Типы операций
        $chargeTypes = ['charge', 'report_access', 'item_purchase', 'subscription'];
        $topupTypes = ['top_up', 'promo_code'];

        // Сводка за период (стабильна независимо от отображаемого фильтра):
        // заморозки/возвраты в денежные итоги НЕ входят — это не расход/приход.
        $summary = [
            'charged_total' => 0.0,
            'topped_up_total' => 0.0,
            'count_charges' => 0,
            'by_type' => [],
        ];
        foreach ($periodAll as $t) {
            if (in_array($t['type'], $chargeTypes, true)) {
                $summary['charged_total'] += $t['amount'];
                $summary['by_type'][$t['type']] = ($summary['by_type'][$t['type']] ?? 0) + $t['amount'];
                $summary['count_charges']++;
            } elseif (in_array($t['type'], $topupTypes, true)) {
                $summary['topped_up_total'] += abs($t['amount']);
            }
        }

        // По умолчанию показываем только списания; ?all=1 — вся лента за период
        $showAll = $request->boolean('all');
        $display = $showAll
            ? $periodAll
            : $periodAll->filter(fn ($t) => in_array($t['type'], $chargeTypes, true))->values();

        if ($request->get('export') === 'csv') {
            return $this->exportTransactionsCsv($user, $display, $from, $to);
        }

        return view('admin.users.transactions', [
            'user' => $user,
            'transactions' => $display,
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'showAll' => $showAll,
        ]);
    }

    /**
     * Выгрузка отображаемых операций в CSV (разделитель «;», UTF-8 BOM для Excel).
     * «Изменение баланса» = знак, обратный amount (списание отрицательно, приход положительно).
     */
    private function exportTransactionsCsv(User $user, $transactions, Carbon $from, Carbon $to): StreamedResponse
    {
        $filename = 'transactions_' . $user->id . '_' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        $callback = function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, ['Дата (UTC)', 'Тип', 'Описание', 'Изменение баланса, ₽', 'Баланс после, ₽'], ';');
            foreach ($transactions as $t) {
                fputcsv($out, [
                    optional($t['created_at'])->format('d.m.Y H:i'),
                    $t['type'],
                    $t['description'],
                    number_format(-1 * $t['amount'], 2, ',', ''),
                    isset($t['balance_after']) ? number_format($t['balance_after'], 2, ',', '') : '',
                ], ';');
            }
            fclose($out);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
