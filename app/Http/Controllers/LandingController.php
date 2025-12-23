<?php

namespace App\Http\Controllers;

use App\Models\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Главная страница (лендинг)
     */
    public function index(): View
    {
        return view('landing.index');
    }

    /**
     * Запрос демо
     */
    public function demoRequest(HttpRequest $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:2000',
        ]);

        // Отправляем в n8n webhook
        try {
            Http::post(config('services.n8n.webhook_url') . '/demo-request', $validated);
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
                'message' => 'Спасибо! Мы свяжемся с вами в ближайшее время.',
            ]);
        }

        return back()->with('success', 'Спасибо! Мы свяжемся с вами в ближайшее время.');
    }

    /**
     * Политика конфиденциальности
     */
    public function privacy(): View
    {
        return view('landing.privacy');
    }
}
