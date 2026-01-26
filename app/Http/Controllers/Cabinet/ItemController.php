<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\ExternalRequestItem;
use App\Models\ItemPurchase;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $query = ExternalRequestItem::with('request');

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('article', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Has offers filter
        if ($request->has('has_offers')) {
            $query->whereHas('offers', function ($q) {
                $q->whereIn('status', ['received', 'processed']);
            });
        }

        $items = $query->orderBy('created_at', 'desc')->paginate(20);

        // Load purchased items for current user
        $purchasedItemIds = ItemPurchase::where('user_id', auth()->id())
            ->pluck('item_id')
            ->toArray();

        // Get user's request numbers to check which items belong to user's requests
        $userRequestNumbers = auth()->user()->requests()
            ->where('synced_to_main_db', true)
            ->pluck('request_number')
            ->toArray();

        return view('cabinet.items.index', compact('items', 'purchasedItemIds', 'userRequestNumbers'));
    }

    public function show(ExternalRequestItem $item)
    {
        $item->load(['request', 'offers.supplier']);

        $user = auth()->user();

        // Check if user has access (purchased or owns the request)
        $hasPurchased = $user->hasAccessToItem($item);

        // Get unlock price from user's active tariff (respects limits)
        $tariff = $user->getActiveTariff();
        $unlockPrice = $tariff
            ? $tariff->tariffPlan->getReportCost($user)
            : (float) Setting::get('item_unlock_price', 99);

        // Get offers with received or processed status
        $offers = $item->offers()
            ->whereIn('status', ['received', 'processed'])
            ->get()
            ->sortBy('price_per_unit_in_rub');

        return view('cabinet.items.show', compact('item', 'hasPurchased', 'unlockPrice', 'offers'));
    }

    public function purchase(Request $request, ExternalRequestItem $item)
    {
        $user = auth()->user();

        // Check if user already has access (purchased or owns the request)
        if ($user->hasAccessToItem($item)) {
            return redirect()->back()->with('error', 'У вас уже есть доступ к этому отчету');
        }

        // Get unlock price from user's active tariff (respects limits)
        $tariff = $user->getActiveTariff();
        $unlockPrice = $tariff
            ? $tariff->tariffPlan->getReportCost($user)
            : (float) Setting::get('item_unlock_price', 99);

        // Check balance only if price > 0
        if ($unlockPrice > 0 && $user->balance < $unlockPrice) {
            return redirect()->back()->with('error', 'Недостаточно средств на балансе');
        }

        DB::beginTransaction();
        try {
            // Deduct balance only if price > 0
            if ($unlockPrice > 0) {
                $user->decrement('balance', $unlockPrice);
            }

            // Create purchase record
            $purchase = ItemPurchase::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'amount' => $unlockPrice,
            ]);

            // Отслеживаем расходование средств из оплаченных счетов
            app(\App\Services\InvoiceTrackingService::class)->trackItemPurchase($purchase);

            // Increment reports_used counter in user's tariff
            if ($tariff) {
                $tariff->useReport();
            }

            DB::commit();

            if ($unlockPrice > 0) {
                return redirect()->back()->with('success', "Полный доступ к отчету получен. Списано: {$unlockPrice} ₽");
            } else {
                return redirect()->back()->with('success', 'Полный доступ к отчету получен бесплатно (в пределах лимита тарифа)');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Произошла ошибка при обработке платежа');
        }
    }
}
