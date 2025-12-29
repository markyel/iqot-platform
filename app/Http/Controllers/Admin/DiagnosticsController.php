<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\N8nParseService;
use App\Services\N8nSenderService;
use Illuminate\Http\Request;

class DiagnosticsController extends Controller
{
    public function index()
    {
        $diagnostics = [
            'parse' => $this->checkParseService(),
            'sender' => $this->checkSenderService(),
            'database' => $this->checkDatabase(),
        ];

        return view('admin.diagnostics.index', compact('diagnostics'));
    }

    private function checkParseService(): array
    {
        $config = [
            'url' => config('services.n8n.parse_webhook_url'),
            'token' => config('services.n8n.parse_auth_token'),
        ];

        $status = 'ok';
        $message = 'Конфигурация корректна';
        $details = [];

        if (empty($config['url'])) {
            $status = 'error';
            $message = 'N8N_PARSE_WEBHOOK_URL не настроен в .env';
            $details[] = 'Добавьте N8N_PARSE_WEBHOOK_URL=https://liftway.app.n8n.cloud/webhook/parse-request';
        }

        if (empty($config['token'])) {
            $status = 'error';
            $message = 'N8N_PARSE_AUTH_TOKEN не настроен в .env';
            $details[] = 'Добавьте N8N_PARSE_AUTH_TOKEN в .env';
        }

        if ($status === 'ok' && strpos($config['token'], '__n8n_BLANK_VALUE') !== false) {
            $status = 'warning';
            $message = 'Используется placeholder токен';
            $details[] = 'Замените токен на реальное значение в .env';
        }

        return [
            'name' => 'Parse Service (AI парсинг заявок)',
            'status' => $status,
            'message' => $message,
            'config' => $config,
            'details' => $details,
        ];
    }

    private function checkSenderService(): array
    {
        $config = [
            'url' => config('services.n8n.sender_webhook_url'),
            'token' => config('services.n8n.sender_auth_token'),
        ];

        $status = 'ok';
        $message = 'Конфигурация корректна';
        $details = [];

        if (empty($config['url'])) {
            $status = 'error';
            $message = 'N8N_SENDER_WEBHOOK_URL не настроен в .env';
            $details[] = 'Добавьте N8N_SENDER_WEBHOOK_URL в .env';
        }

        if (empty($config['token'])) {
            $status = 'error';
            $message = 'N8N_SENDER_AUTH_TOKEN не настроен в .env';
            $details[] = 'Добавьте N8N_SENDER_AUTH_TOKEN в .env';
        }

        if ($status === 'ok' && strpos($config['token'], '__n8n_BLANK_VALUE') !== false) {
            $status = 'warning';
            $message = 'Используется placeholder токен';
            $details[] = 'Замените токен на реальное значение в .env';
        }

        return [
            'name' => 'Sender Service (Управление отправителями)',
            'status' => $status,
            'message' => $message,
            'config' => $config,
            'details' => $details,
        ];
    }

    private function checkDatabase(): array
    {
        $status = 'ok';
        $message = 'База данных работает корректно';
        $details = [];

        try {
            \DB::connection()->getPdo();

            // Проверка важных таблиц
            $tables = ['users', 'system_settings', 'balance_holds', 'requests', 'request_items'];
            foreach ($tables as $table) {
                if (!\Schema::hasTable($table)) {
                    $status = 'error';
                    $message = 'Отсутствуют необходимые таблицы';
                    $details[] = "Таблица '{$table}' не найдена. Запустите: php artisan migrate";
                }
            }

        } catch (\Exception $e) {
            $status = 'error';
            $message = 'Ошибка подключения к базе данных';
            $details[] = $e->getMessage();
        }

        return [
            'name' => 'Database',
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    public function testParse(Request $request)
    {
        $request->validate([
            'text' => 'required|string|min:3'
        ]);

        $parseService = app(N8nParseService::class);
        $result = $parseService->parseRequest($request->text);

        return response()->json($result);
    }

    public function testConnection()
    {
        $url = config('services.n8n.parse_webhook_url');
        $token = config('services.n8n.parse_auth_token');

        if (empty($url) || empty($token)) {
            return response()->json([
                'success' => false,
                'error' => 'Configuration missing',
                'message' => 'URL или токен не настроены'
            ]);
        }

        try {
            // Тестовый запрос с Header Auth
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['X-Auth-Token' => $token])
                ->post($url, ['text' => 'Тестовая заявка']);

            return response()->json([
                'success' => true,
                'status_code' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'json' => $response->json(),
                'url' => $url,
                'token_length' => strlen($token),
                'token_preview' => substr($token, 0, 10) . '...' . substr($token, -5)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
