<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TariffPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TariffPlanController extends Controller
{
    /**
     * Список тарифных планов
     */
    public function index()
    {
        $tariffPlans = TariffPlan::withCount('activeSubscriptions')
            ->ordered()
            ->get();

        return view('admin.tariff-plans.index', compact('tariffPlans'));
    }

    /**
     * Форма создания тарифа
     */
    public function create()
    {
        return view('admin.tariff-plans.create');
    }

    /**
     * Сохранение нового тарифа
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:tariff_plans,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'items_limit' => 'nullable|integer|min:0',
            'reports_limit' => 'nullable|integer|min:0',
            'price_per_item_over_limit' => 'required|numeric|min:0',
            'price_per_report_over_limit' => 'required|numeric|min:0',
            'sort_order' => 'required|integer|min:0',
        ]);

        // Checkbox отправляет значение только когда отмечен
        $validated['is_active'] = $request->has('is_active');

        TariffPlan::create($validated);

        return redirect()
            ->route('admin.tariff-plans.index')
            ->with('success', 'Тарифный план успешно создан');
    }

    /**
     * Форма редактирования тарифа
     */
    public function edit($id)
    {
        $tariffPlan = TariffPlan::withCount('activeSubscriptions')->findOrFail($id);

        return view('admin.tariff-plans.edit', compact('tariffPlan'));
    }

    /**
     * Обновление тарифа
     */
    public function update(Request $request, $id)
    {
        $tariffPlan = TariffPlan::findOrFail($id);

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:tariff_plans,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'monthly_price' => 'required|numeric|min:0',
            'items_limit' => 'nullable|integer|min:0',
            'reports_limit' => 'nullable|integer|min:0',
            'price_per_item_over_limit' => 'required|numeric|min:0',
            'price_per_report_over_limit' => 'required|numeric|min:0',
            'sort_order' => 'required|integer|min:0',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $tariffPlan->update($validated);

        return redirect()
            ->route('admin.tariff-plans.index')
            ->with('success', 'Тарифный план успешно обновлен');
    }

    /**
     * Удаление тарифа
     */
    public function destroy($id)
    {
        $tariffPlan = TariffPlan::withCount('activeSubscriptions')->findOrFail($id);

        // Проверяем, есть ли активные подписки
        if ($tariffPlan->active_subscriptions_count > 0) {
            return back()->with('error', 'Невозможно удалить тариф с активными подписками');
        }

        $tariffPlan->delete();

        return redirect()
            ->route('admin.tariff-plans.index')
            ->with('success', 'Тарифный план успешно удален');
    }
}
