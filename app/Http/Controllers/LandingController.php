<?php

namespace App\Http\Controllers;

use App\Models\Request;
use App\Models\DemoRequest;
use App\Models\User;
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
        $pricing = [
            'monitoring' => \App\Models\SystemSetting::get('pricing_monitoring', 396),
            'report_unlock' => \App\Models\SystemSetting::get('pricing_report_unlock', 99),
        ];

        return view('landing.index', compact('pricing'));
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
        $pricing = [
            'monitoring' => \App\Models\SystemSetting::get('pricing_monitoring', 396),
            'report_unlock' => \App\Models\SystemSetting::get('pricing_report_unlock', 99),
            'subscription_basic' => [
                'price' => \App\Models\SystemSetting::get('subscription_basic_price', 5000),
                'positions' => \App\Models\SystemSetting::get('subscription_basic_positions', 15),
                'reports' => \App\Models\SystemSetting::get('subscription_basic_reports', 5),
                'overlimit_position' => \App\Models\SystemSetting::get('subscription_basic_overlimit_position', 300),
                'overlimit_report' => \App\Models\SystemSetting::get('subscription_basic_overlimit_report', 89),
            ],
            'subscription_advanced' => [
                'price' => \App\Models\SystemSetting::get('subscription_advanced_price', 15000),
                'positions' => \App\Models\SystemSetting::get('subscription_advanced_positions', 50),
                'reports' => \App\Models\SystemSetting::get('subscription_advanced_reports', 15),
                'overlimit_position' => \App\Models\SystemSetting::get('subscription_advanced_overlimit_position', 270),
                'overlimit_report' => \App\Models\SystemSetting::get('subscription_advanced_overlimit_report', 79),
            ],
            'subscription_pro' => [
                'price' => \App\Models\SystemSetting::get('subscription_pro_price', 50000),
                'positions' => \App\Models\SystemSetting::get('subscription_pro_positions', 200),
                'reports' => \App\Models\SystemSetting::get('subscription_pro_reports', 50),
                'overlimit_position' => \App\Models\SystemSetting::get('subscription_pro_overlimit_position', 240),
                'overlimit_report' => \App\Models\SystemSetting::get('subscription_pro_overlimit_report', 69),
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
