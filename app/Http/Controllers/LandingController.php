<?php

namespace App\Http\Controllers;

use App\Models\Request;
use App\Models\DemoRequest;
use App\Models\User;
use App\Models\PublicCatalogItem;
use App\Http\Requests\StoreDemoRequestRequest;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    /**
     * Главная страница (лендинг)
     */
    public function index(): View
    {
        // Получаем стартовый тариф (Pay-as-you-go)
        $startTariff = \App\Models\TariffPlan::where('is_active', true)
            ->where('code', 'start')
            ->first();

        $pricing = [
            'monitoring' => $startTariff ? $startTariff->price_per_item_over_limit : 396,
            'report_unlock' => $startTariff ? $startTariff->price_per_report_over_limit : 99,
        ];

        // Получаем последние 5 позиций из каталога для тизера (по дате создания позиции)
        $catalogItems = PublicCatalogItem::published()
            ->withOffers()
            ->orderBy('item_created_at', 'desc')
            ->limit(5)
            ->get();

        return view('landing.index', compact('pricing', 'catalogItems'));
    }

    /**
     * Проверка существования email
     */
    public function checkEmail(HttpRequest $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Запрос демо
     */
    public function demoRequest(StoreDemoRequestRequest $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validated();

        // Проверяем, существует ли пользователь
        $user = User::where('email', $validated['email'])->first();

        // Если пользователь существует, проверяем пароль
        if ($user) {
            if (!$request->has('password') || !Hash::check($request->password, $user->password)) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['password' => ['Неверный пароль']],
                    ], 422);
                }
                return back()->withErrors(['password' => 'Неверный пароль'])->withInput();
            }

            // Авторизуем пользователя
            Auth::login($user);
        }

        // Генерируем токен для установки пароля (если новый пользователь)
        $passwordToken = null;
        if (!$user) {
            $passwordToken = Str::random(64);
            $validated['password_setup_token'] = $passwordToken;
            $validated['password_setup_token_expires_at'] = now()->addHours(24);
        }

        // Создаём заявку в БД
        $demoRequest = DemoRequest::create($validated);

        // Отправляем в n8n webhook
        try {
            Http::post(config('services.n8n.webhook_url', '') . '/demo-request', [
                'id' => $demoRequest->id,
                ...$validated
            ]);
        } catch (\Exception $e) {
            // Логируем ошибку, но не показываем пользователю
            logger()->error('Failed to send demo request to n8n', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $user
                    ? 'Спасибо! Ваша заявка принята. Результаты будут доступны в личном кабинете.'
                    : 'Спасибо! Ваша заявка принята. Сейчас создадим пароль для доступа к результатам.',
                'redirect' => $passwordToken ? route('set-password', ['token' => $passwordToken]) : null,
            ]);
        }

        if ($passwordToken) {
            return redirect()->route('set-password', ['token' => $passwordToken]);
        }

        return back()->with('success', 'Спасибо! Ваша заявка принята.');
    }

    /**
     * Политика конфиденциальности
     */
    public function privacy(): View
    {
        return view('landing.privacy');
    }

    /**
     * Условия использования
     */
    public function terms(): View
    {
        return view('landing.terms');
    }

    /**
     * Тарифы и оплата
     */
    public function pricing(): View
    {
        // Получаем тарифные планы из базы
        $tariffPlans = \App\Models\TariffPlan::where('is_active', true)
            ->orderBy('monthly_price')
            ->get()
            ->keyBy('code');

        // Базовые цены без тарифа (Pay-as-you-go) - тариф "start"
        $startTariff = $tariffPlans->get('start');

        $pricing = [
            'monitoring' => $startTariff ? $startTariff->price_per_item_over_limit : 396,
            'report_unlock' => $startTariff ? $startTariff->price_per_report_over_limit : 99,
            'subscription_basic' => [
                'price' => $tariffPlans->get('basic')?->monthly_price ?? 5000,
                'positions' => $tariffPlans->get('basic')?->items_limit ?? 15,
                'reports' => $tariffPlans->get('basic')?->reports_limit ?? 5,
                'overlimit_position' => $tariffPlans->get('basic')?->price_per_item_over_limit ?? 300,
                'overlimit_report' => $tariffPlans->get('basic')?->price_per_report_over_limit ?? 89,
            ],
            'subscription_advanced' => [
                'price' => $tariffPlans->get('extended')?->monthly_price ?? 15000,
                'positions' => $tariffPlans->get('extended')?->items_limit ?? 50,
                'reports' => $tariffPlans->get('extended')?->reports_limit ?? 15,
                'overlimit_position' => $tariffPlans->get('extended')?->price_per_item_over_limit ?? 270,
                'overlimit_report' => $tariffPlans->get('extended')?->price_per_report_over_limit ?? 79,
            ],
            'subscription_pro' => [
                'price' => $tariffPlans->get('professional')?->monthly_price ?? 50000,
                'positions' => $tariffPlans->get('professional')?->items_limit ?? 200,
                'reports' => $tariffPlans->get('professional')?->reports_limit ?? 50,
                'overlimit_position' => $tariffPlans->get('professional')?->price_per_item_over_limit ?? 240,
                'overlimit_report' => $tariffPlans->get('professional')?->price_per_report_over_limit ?? 69,
            ],
        ];

        return view('landing.pricing', compact('pricing'));
    }

    /**
     * Страница установки пароля
     */
    public function showSetPassword(string $token): View|RedirectResponse
    {
        $demoRequest = DemoRequest::where('password_setup_token', $token)
            ->where('password_setup_token_expires_at', '>', now())
            ->first();

        if (!$demoRequest) {
            return redirect('/')->with('error', 'Ссылка недействительна или истекла');
        }

        return view('auth.set-password', ['token' => $token]);
    }

    /**
     * Обработка установки пароля
     */
    public function storePassword(HttpRequest $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $demoRequest = DemoRequest::where('password_setup_token', $request->token)
            ->where('password_setup_token_expires_at', '>', now())
            ->first();

        if (!$demoRequest) {
            return redirect('/')->with('error', 'Ссылка недействительна или истекла');
        }

        // Создаём пользователя
        $user = User::create([
            'name' => $demoRequest->full_name,
            'email' => $demoRequest->email,
            'password' => Hash::make($request->password),
            'organization' => $demoRequest->organization,
            'inn' => $demoRequest->inn,
            'kpp' => $demoRequest->kpp,
            'phone' => $demoRequest->phone,
            'email_verified_at' => now(),
        ]);

        // Удаляем токен
        $demoRequest->update([
            'password_setup_token' => null,
            'password_setup_token_expires_at' => null,
        ]);

        // Авторизуем пользователя
        Auth::login($user);

        return redirect()->route('cabinet.dashboard')
            ->with('success', 'Добро пожаловать! Ваш аккаунт создан.');
    }
}
