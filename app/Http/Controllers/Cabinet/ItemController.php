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

        return view('cabinet.items.index', compact('items', 'purchasedItemIds'));
    }

    public function show(ExternalRequestItem $item)
    {
        $item->load(['request', 'offers.supplier']);

        // Check if user has purchased this item
        $hasPurchased = ItemPurchase::where('user_id', auth()->id())
            ->where('item_id', $item->id)
            ->exists();

        $unlockPrice = Setting::get('item_unlock_price', 99);

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
        $unlockPrice = (float) Setting::get('item_unlock_price', 99);

        // Check if already purchased
        if (ItemPurchase::where('user_id', $user->id)->where('item_id', $item->id)->exists()) {
            return redirect()->back()->with('error', 'Вы уже приобрели доступ к этому отчету');
        }

        // Check balance
        if ($user->balance < $unlockPrice) {
            return redirect()->back()->with('error', 'Недостаточно средств на балансе');
        }

        DB::beginTransaction();
        try {
            // Deduct balance
            $user->decrement('balance', $unlockPrice);

            // Create purchase record
            ItemPurchase::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'amount' => $unlockPrice,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Полный доступ к отчету получен');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Произошла ошибка при обработке платежа');
        }
    }
}
