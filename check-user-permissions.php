<?php
/**
 * Проверка прав текущего пользователя
 * Запуск: php check-user-permissions.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "===========================================\n";
echo "  Проверка пользователей и прав\n";
echo "===========================================\n\n";

// Получаем всех пользователей
$users = DB::table('users')->get();

if ($users->isEmpty()) {
    echo "❌ Нет пользователей в системе!\n\n";
    exit(1);
}

echo "Всего пользователей: " . $users->count() . "\n\n";

foreach ($users as $user) {
    echo "─────────────────────────────────────────\n";
    echo "ID: {$user->id}\n";
    echo "Email: {$user->email}\n";
    echo "Имя: {$user->name}\n";
    echo "Email подтвержден: " . ($user->email_verified_at ? "✓ Да ({$user->email_verified_at})" : "✗ Нет") . "\n";
    echo "Администратор: " . ($user->is_admin ? "✓ Да" : "✗ Нет") . "\n";

    // Проверка доступа к админке
    $canAccessAdmin = $user->is_admin && $user->email_verified_at;
    echo "Доступ к /manage/*: " . ($canAccessAdmin ? "✓ Разрешен" : "✗ Запрещен") . "\n";

    if (!$canAccessAdmin) {
        echo "\nПричины отказа:\n";
        if (!$user->is_admin) {
            echo "  - Не является администратором (is_admin = 0)\n";
        }
        if (!$user->email_verified_at) {
            echo "  - Email не подтвержден\n";
        }
    }

    echo "\n";
}

echo "===========================================\n";
echo "  Как исправить\n";
echo "===========================================\n\n";

$firstUser = $users->first();

echo "Чтобы дать права администратора пользователю:\n\n";
echo "UPDATE users SET is_admin = 1 WHERE email = '{$firstUser->email}';\n\n";

echo "Чтобы подтвердить email:\n\n";
echo "UPDATE users SET email_verified_at = NOW() WHERE email = '{$firstUser->email}';\n\n";

echo "Или всё сразу:\n\n";
echo "UPDATE users SET is_admin = 1, email_verified_at = NOW() WHERE email = '{$firstUser->email}';\n\n";

echo "===========================================\n\n";
