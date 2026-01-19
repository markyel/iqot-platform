<?php
/**
 * Скрипт диагностики настройки функционала управления заявками
 * Запуск: php check-setup.php
 */

echo "\n";
echo "===========================================\n";
echo "  IQOT - Проверка настройки управления заявками\n";
echo "===========================================\n\n";

// Загружаем Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$errors = 0;
$warnings = 0;

// 1. Проверка файлов
echo "1. Проверка файлов...\n";
$requiredFiles = [
    'app/Http/Controllers/Admin/ManageRequestController.php',
    'app/Services/N8nRequestService.php',
    'app/Models/ClientOrganization.php',
    'app/Models/Category.php',
    'app/Models/ProductType.php',
    'app/Models/ApplicationDomain.php',
    'resources/views/admin/manage/requests/index.blade.php',
    'resources/views/admin/manage/requests/create.blade.php',
    'resources/views/admin/manage/requests/edit.blade.php',
    'resources/views/admin/manage/requests/show.blade.php',
];

foreach ($requiredFiles as $file) {
    if (file_exists(__DIR__.'/'.$file)) {
        echo "   ✓ {$file}\n";
    } else {
        echo "   ✗ {$file} - НЕ НАЙДЕН!\n";
        $errors++;
    }
}

// 2. Проверка конфигурации n8n
echo "\n2. Проверка конфигурации n8n...\n";
$n8nUrl = config('services.n8n.webhook_url');
$n8nAuthToken = config('services.n8n.auth_token');
$n8nParseToken = config('services.n8n.parse_auth_token');

if ($n8nUrl) {
    echo "   ✓ N8N_WEBHOOK_URL: {$n8nUrl}\n";
} else {
    echo "   ✗ N8N_WEBHOOK_URL не настроен!\n";
    $errors++;
}

if ($n8nAuthToken) {
    echo "   ✓ N8N_AUTH_TOKEN: " . substr($n8nAuthToken, 0, 10) . "...\n";
} else {
    echo "   ⚠ N8N_AUTH_TOKEN не настроен\n";
    $warnings++;
}

if ($n8nParseToken) {
    echo "   ✓ N8N_PARSE_AUTH_TOKEN: " . substr($n8nParseToken, 0, 10) . "...\n";
} else {
    echo "   ⚠ N8N_PARSE_AUTH_TOKEN не настроен\n";
    $warnings++;
}

// 3. Проверка подключения к БД reports
echo "\n3. Проверка подключения к БД reports...\n";
try {
    $pdo = DB::connection('reports')->getPdo();
    echo "   ✓ Подключение к БД reports успешно\n";

    // Проверка таблиц
    $tables = ['categories', 'product_types', 'application_domains', 'client_organizations'];
    foreach ($tables as $table) {
        try {
            $count = DB::connection('reports')->table($table)->count();
            echo "   ✓ Таблица {$table}: {$count} записей\n";
        } catch (\Exception $e) {
            echo "   ✗ Таблица {$table} не найдена!\n";
            $errors++;
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Ошибка подключения к БД reports: " . $e->getMessage() . "\n";
    $errors++;
}

// 4. Проверка middleware
echo "\n4. Проверка middleware...\n";
if (class_exists('\App\Http\Middleware\EnsureUserIsAdmin')) {
    echo "   ✓ Middleware EnsureUserIsAdmin существует\n";
} else {
    echo "   ✗ Middleware EnsureUserIsAdmin не найден!\n";
    $errors++;
}

// 5. Проверка маршрутов
echo "\n5. Проверка маршрутов...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $manageRoutes = 0;

    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'manage-requests')) {
            $manageRoutes++;
        }
    }

    if ($manageRoutes >= 8) {
        echo "   ✓ Найдено {$manageRoutes} маршрутов manage-requests\n";
    } else {
        echo "   ⚠ Найдено только {$manageRoutes} маршрутов (ожидалось 8+)\n";
        echo "   Выполните: php artisan route:clear\n";
        $warnings++;
    }
} catch (\Exception $e) {
    echo "   ✗ Ошибка проверки маршрутов: " . $e->getMessage() . "\n";
    $errors++;
}

// 6. Проверка пользователей-админов
echo "\n6. Проверка пользователей-администраторов...\n";
try {
    $adminCount = DB::table('users')->where('is_admin', 1)->count();
    if ($adminCount > 0) {
        echo "   ✓ Найдено администраторов: {$adminCount}\n";

        $admins = DB::table('users')->where('is_admin', 1)->select('id', 'email')->get();
        foreach ($admins as $admin) {
            echo "      - {$admin->email} (ID: {$admin->id})\n";
        }
    } else {
        echo "   ⚠ Администраторы не найдены!\n";
        echo "   Создайте администратора:\n";
        echo "   UPDATE users SET is_admin = 1 WHERE email = 'ваш@email.com';\n";
        $warnings++;
    }
} catch (\Exception $e) {
    echo "   ✗ Ошибка проверки пользователей: " . $e->getMessage() . "\n";
    $errors++;
}

// 7. Тест N8nRequestService
echo "\n7. Тест N8nRequestService...\n";
try {
    $service = app(\App\Services\N8nRequestService::class);
    echo "   ✓ Сервис N8nRequestService инициализирован\n";

    // Можем попробовать тестовый парсинг только если токен настроен
    if ($n8nParseToken) {
        echo "   ℹ Тестовый парсинг через n8n...\n";
        try {
            $result = $service->parseRequestText("Кнопка OTIS 10шт");
            if ($result['success'] ?? false) {
                echo "   ✓ AI-парсинг работает!\n";
                echo "   Распознано позиций: " . count($result['items'] ?? []) . "\n";
            } else {
                echo "   ⚠ AI-парсинг вернул ошибку: " . ($result['message'] ?? 'Unknown') . "\n";
                $warnings++;
            }
        } catch (\Exception $e) {
            echo "   ⚠ Ошибка парсинга (возможно, n8n недоступен): " . $e->getMessage() . "\n";
            $warnings++;
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Ошибка инициализации сервиса: " . $e->getMessage() . "\n";
    $errors++;
}

// Итоги
echo "\n===========================================\n";
echo "  ИТОГИ ПРОВЕРКИ\n";
echo "===========================================\n";

if ($errors === 0 && $warnings === 0) {
    echo "✓ Всё отлично! Система готова к использованию.\n";
    echo "\nОткройте в браузере:\n";
    echo "http://ваш-домен/manage/manage-requests\n\n";
} else {
    if ($errors > 0) {
        echo "✗ Найдено критических ошибок: {$errors}\n";
    }
    if ($warnings > 0) {
        echo "⚠ Найдено предупреждений: {$warnings}\n";
    }

    echo "\nРекомендации:\n";
    echo "1. Очистите кэш: php artisan config:clear && php artisan route:clear\n";
    echo "2. Проверьте .env файл (токены n8n)\n";
    echo "3. Убедитесь, что у вас есть права администратора\n";
    echo "4. См. документацию: QUICK_START.md\n\n";
}
