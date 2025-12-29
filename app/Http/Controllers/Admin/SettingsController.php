<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $unlockPrice = Setting::get('item_unlock_price', 99);
        $pricePerItem = SystemSetting::get('price_per_item', 50);

        return view('admin.settings.index', compact('unlockPrice', 'pricePerItem'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'item_unlock_price' => 'required|numeric|min:0',
            'price_per_item' => 'required|numeric|min:0',
        ]);

        Setting::set('item_unlock_price', $request->item_unlock_price);
        SystemSetting::set('price_per_item', $request->price_per_item);

        return redirect()->back()->with('success', 'Настройки обновлены');
    }
}
