<?php
/**
 * Простой тест подключения к n8n webhook
 * Запуск: php test-n8n-connection.php
 */

// Настройки из .env
$webhookUrl = 'https://liftway.app.n8n.cloud/webhook/sender-management';
$authToken = '__n8n_BLANK_VALUE_e5362baf-c777-4d57-a609-6eaf1f9e87f6';

echo "🔍 Тестирование подключения к n8n webhook\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "URL: {$webhookUrl}\n";
echo "Token: " . substr($authToken, 0, 30) . "...\n\n";

// Тест 1: Проверка доступности (GET запрос без авторизации)
echo "📋 Тест 1: Проверка доступности webhook\n";
echo "   Выполняется GET запрос без авторизации...\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "   ❌ ОШИБКА: {$error}\n";
    echo "   💡 Возможно webhook недоступен или URL неверный\n\n";
} else {
    echo "   ✅ Webhook отвечает: HTTP {$httpCode}\n";
    if ($httpCode === 404) {
        echo "   ❌ 404 Not Found - webhook не существует или workflow неактивен\n";
    } elseif ($httpCode === 403) {
        echo "   🟡 403 Forbidden - webhook существует, но требует авторизацию (это нормально)\n";
    } elseif ($httpCode === 401) {
        echo "   🟡 401 Unauthorized - webhook существует, но требует авторизацию\n";
    }
    echo "   Ответ: " . substr($response, 0, 100) . "\n\n";
}

// Тест 2: POST запрос с авторизацией
echo "📋 Тест 2: POST запрос с авторизацией\n";
echo "   Action: get_available_emails\n";

$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Auth-Token: ' . $authToken
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'action' => 'get_available_emails'
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "   ❌ ОШИБКА: {$error}\n\n";
} else {
    echo "   HTTP Status: {$httpCode}\n";

    if ($httpCode === 200) {
        echo "   ✅ УСПЕХ! Webhook работает корректно\n";
        echo "   Ответ: {$response}\n\n";
    } elseif ($httpCode === 403) {
        echo "   ❌ 403 Forbidden - Токен авторизации неверный!\n";
        echo "   💡 Проверьте токен в n8n (Webhook → Settings → Header Auth)\n";
        echo "   Ответ: {$response}\n\n";
    } elseif ($httpCode === 401) {
        echo "   ❌ 401 Unauthorized - Токен авторизации неверный!\n";
        echo "   Ответ: {$response}\n\n";
    } else {
        echo "   ⚠️  Неожиданный статус код\n";
        echo "   Ответ: {$response}\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n📝 Рекомендации:\n";
echo "   1. Если получен 403/401 - замените токен в .env на реальный из n8n\n";
echo "   2. Если получен 404 - проверьте URL webhook и активность workflow\n";
echo "   3. После изменения .env выполните: php artisan config:clear\n";
echo "   4. Откройте n8n и найдите правильный токен в настройках webhook\n\n";
