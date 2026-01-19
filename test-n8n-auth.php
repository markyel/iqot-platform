<?php
/**
 * Скрипт тестирования разных вариантов аутентификации n8n
 * Запуск: php test-n8n-auth.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "\n===========================================\n";
echo "  Тестирование аутентификации n8n\n";
echo "===========================================\n\n";

$baseUrl = config('services.n8n.webhook_url', 'https://liftway.app.n8n.cloud/webhook');
$parseToken = config('services.n8n.parse_auth_token');

echo "Base URL: {$baseUrl}\n";
echo "Parse Token: " . ($parseToken ? substr($parseToken, 0, 15) . "..." : "НЕ НАСТРОЕН") . "\n\n";

if (!$parseToken) {
    echo "❌ Токен парсинга не настроен в .env!\n";
    echo "Добавьте: N8N_PARSE_AUTH_TOKEN=iqot_parse_api_2024_secret\n\n";
    exit(1);
}

$testText = "Кнопка вызова OTIS 10шт";

// Вариант 1: Authorization Bearer
echo "Тест 1: Authorization: Bearer {token}\n";
try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $parseToken,
    ])->timeout(30)->post("{$baseUrl}/parse-request", [
        'text' => $testText
    ]);

    echo "Статус: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "✅ УСПЕХ!\n";
        $data = $response->json();
        echo "Ответ: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Ошибка: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n---\n\n";

// Вариант 2: X-Auth-Token
echo "Тест 2: X-Auth-Token: {token}\n";
try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'X-Auth-Token' => $parseToken,
    ])->timeout(30)->post("{$baseUrl}/parse-request", [
        'text' => $testText
    ]);

    echo "Статус: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "✅ УСПЕХ!\n";
        $data = $response->json();
        echo "Ответ: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Ошибка: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n---\n\n";

// Вариант 3: Без токена
echo "Тест 3: Без аутентификации\n";
try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->timeout(30)->post("{$baseUrl}/parse-request", [
        'text' => $testText
    ]);

    echo "Статус: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "✅ УСПЕХ! (Токен не требуется)\n";
        $data = $response->json();
        echo "Ответ: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Ошибка: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n---\n\n";

// Вариант 4: В query параметре
echo "Тест 4: В URL query параметре ?token=...\n";
try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->timeout(30)->post("{$baseUrl}/parse-request?token={$parseToken}", [
        'text' => $testText
    ]);

    echo "Статус: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "✅ УСПЕХ!\n";
        $data = $response->json();
        echo "Ответ: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Ошибка: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n---\n\n";

// Вариант 5: В теле запроса
echo "Тест 5: В теле запроса (auth_token)\n";
try {
    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
    ])->timeout(30)->post("{$baseUrl}/parse-request", [
        'text' => $testText,
        'auth_token' => $parseToken
    ]);

    echo "Статус: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "✅ УСПЕХ!\n";
        $data = $response->json();
        echo "Ответ: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ Ошибка: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Исключение: " . $e->getMessage() . "\n";
}

echo "\n===========================================\n";
echo "  Тестирование завершено\n";
echo "===========================================\n\n";

echo "💡 Подсказка: Проверьте в n8n, какой метод аутентификации\n";
echo "   настроен для вебхука parse-request\n\n";
