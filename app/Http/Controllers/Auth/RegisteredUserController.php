<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TariffPlan;
use App\Models\Setting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Форма регистрации
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Обработка регистрации
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'company' => $request->company,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // Назначаем тариф по умолчанию
        $this->assignDefaultTariff($user);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('cabinet.dashboard', absolute: false));
    }

    /**
     * Назначение тарифа по умолчанию новому пользователю
     */
    private function assignDefaultTariff(User $user): void
    {
        // Получаем ID тарифа по умолчанию из настроек
        $defaultTariffId = Setting::where('key', 'default_tariff_plan_id')->value('value');

        if (!$defaultTariffId) {
            // Если настройка не найдена, используем тариф "Старт"
            $startTariff = TariffPlan::where('code', 'start')->first();
            $defaultTariffId = $startTariff?->id;
        }

        if ($defaultTariffId) {
            $user->tariffs()->create([
                'tariff_plan_id' => $defaultTariffId,
                'is_active' => true,
                'started_at' => now(),
                'items_used' => 0,
                'reports_used' => 0,
            ]);
        }
    }
}
