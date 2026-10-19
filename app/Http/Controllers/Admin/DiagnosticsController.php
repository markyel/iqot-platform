<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Requests\RequestTextParser;
use App\Services\Senders\CustomerSenderService;
use Illuminate\Http\Request;

/** Диагностика: разбор заявок (ИИ), пул ящиков клиентских отправителей, БД. Без n8n (выключен 10.2026). */
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
        $ai = app(OpenAIClassifierClient::class);
        $ok = $ai->isConfigured();

        return [
            'name' => 'Разбор заявок (ИИ)',
            'status' => $ok ? 'ok' : 'error',
            'message' => $ok ? 'ИИ-клиент настроен' : 'ИИ-клиент не настроен (OPENAI_CLASSIFIER_*)',
            'config' => ['model' => $ai->modelFull(), 'queue' => 'generate'],
            'details' => [],
        ];
    }

    private function checkSenderService(): array
    {
        try {
            $free = count(app(CustomerSenderService::class)->getAvailableEmails()['emails'] ?? []);
        } catch (\Throwable $e) {
            return ['name' => 'Отправители клиентов', 'status' => 'error', 'message' => 'Ошибка БД reports', 'details' => [$e->getMessage()]];
        }

        return [
            'name' => 'Отправители клиентов',
            'status' => $free > 0 ? 'ok' : 'warning',
            'message' => $free > 0 ? "Свободных ящиков в резерве: {$free}" : 'Свободных ящиков в reserved_emails нет',
            'config' => [],
            'details' => [],
        ];
    }

    private function checkDatabase(): array
    {
        $status = 'ok';
        $message = 'База данных работает корректно';
        $details = [];

        try {
            \DB::connection()->getPdo();
            \DB::connection('reports')->getPdo();

            $tables = ['users', 'system_settings', 'balance_holds', 'requests', 'request_items', 'parse_tasks'];
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
            'text' => 'required|string|min:3|max:10000',
        ]);

        try {
            return response()->json(['success' => true] + app(RequestTextParser::class)->parse($request->text));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function testConnection()
    {
        $ai = app(OpenAIClassifierClient::class);
        try {
            $t0 = microtime(true);
            $res = $ai->jsonCompletion($ai->modelMini(), 'Ответь JSON {"ok": true}', 'ping', 20);

            return response()->json(['success' => (bool) ($res['ok'] ?? false), 'ms' => (int) ((microtime(true) - $t0) * 1000)]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
