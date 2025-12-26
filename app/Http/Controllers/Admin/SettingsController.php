<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $unlockPrice = Setting::get('item_unlock_price', 99);

        return view('admin.settings.index', compact('unlockPrice'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'item_unlock_price' => 'required|numeric|min:0',
        ]);

        Setting::set('item_unlock_price', $request->item_unlock_price);

        return redirect()->back()->with('success', 'Настройки обновлены');
    }
}
