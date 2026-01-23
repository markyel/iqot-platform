<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BillingSettingsController extends Controller
{
    public function index()
    {
        $settings = BillingSettings::current() ?? new BillingSettings();

        return view('admin.billing.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'full_name' => 'nullable|string|max:500',
            'inn' => 'required|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'ogrnip' => 'nullable|string|max:15',
            'ogrn' => 'nullable|string|max:13',
            'address' => 'required|string',
            'bank_name' => 'required|string|max:255',
            'bank_bik' => 'required|string|max:9',
            'bank_corr_account' => 'required|string|max:20',
            'bank_account' => 'required|string|max:20',
            'director_name' => 'nullable|string|max:255',
            'director_short' => 'nullable|string|max:100',
            'director_position' => 'nullable|string|max:255',
            'accountant_name' => 'nullable|string|max:255',
            'registration_date' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|string|max:255',
            'signature_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'stamp_image' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'invoice_number_mask' => 'required|string|max:100',
            'invoice_number_start' => 'required|integer|min:1',
            'vat_enabled' => 'nullable|boolean',
            'vat_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Обработка чекбокса НДС
        $validated['vat_enabled'] = $request->has('vat_enabled');

        $settings = BillingSettings::current();

        // Проверяем, изменился ли начальный номер счета
        $shouldResetCounter = false;
        if ($settings && $settings->invoice_number_start != $validated['invoice_number_start']) {
            $shouldResetCounter = true;
        }

        // Обработка загрузки подписи
        if ($request->hasFile('signature_image')) {
            // Удаляем старое изображение
            if ($settings && $settings->signature_image) {
                Storage::disk('public')->delete($settings->signature_image);
            }
            $validated['signature_image'] = $request->file('signature_image')->store('billing/signatures', 'public');
        }

        // Обработка удаления подписи
        if ($request->has('remove_signature') && $settings && $settings->signature_image) {
            Storage::disk('public')->delete($settings->signature_image);
            $validated['signature_image'] = null;
        }

        // Обработка загрузки печати
        if ($request->hasFile('stamp_image')) {
            // Удаляем старое изображение
            if ($settings && $settings->stamp_image) {
                Storage::disk('public')->delete($settings->stamp_image);
            }
            $validated['stamp_image'] = $request->file('stamp_image')->store('billing/stamps', 'public');
        }

        // Обработка удаления печати
        if ($request->has('remove_stamp') && $settings && $settings->stamp_image) {
            Storage::disk('public')->delete($settings->stamp_image);
            $validated['stamp_image'] = null;
        }

        if ($settings) {
            // Если изменился начальный номер - сбрасываем счетчик
            if ($shouldResetCounter) {
                $validated['invoice_number_current'] = 0;
            }
            $settings->update($validated);
        } else {
            BillingSettings::create($validated);
        }

        $message = 'Реквизиты успешно обновлены';
        if ($shouldResetCounter) {
            $message .= '. Счетчик счетов сброшен.';
        }

        return redirect()->back()->with('success', $message);
    }
}
