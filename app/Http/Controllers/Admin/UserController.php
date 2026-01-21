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

        return view('admin.users.show', compact('user', 'tariff', 'limitsInfo', 'requestsStats', 'reportAccessCount', 'itemPurchasesCount'));
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
