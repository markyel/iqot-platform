<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Проверяем пользователя #2
$user = \App\Models\User::find(2);

echo "User #2: {$user->name} ({$user->email})\n";
echo "Is Admin: " . ($user->is_admin ? 'Yes' : 'No') . "\n\n";

// Проверяем тарифы
$tariffs = \App\Models\UserTariff::where('user_id', 2)->get();
echo "User Tariffs count: " . $tariffs->count() . "\n";

foreach ($tariffs as $tariff) {
    echo "- Tariff ID: {$tariff->id}, Plan ID: {$tariff->tariff_plan_id}, Status: {$tariff->status}\n";
}

echo "\n";

// Проверяем активный тариф
$activeTariff = $user->getActiveTariff();
if ($activeTariff) {
    echo "Active Tariff: Yes\n";
    echo "Plan: {$activeTariff->tariffPlan->name}\n";
} else {
    echo "Active Tariff: No\n";
}

echo "\n";

// Проверяем настройку
$setting = \App\Models\Setting::where('key', 'default_tariff_plan_id')->first();
if ($setting) {
    echo "Default Tariff Setting: {$setting->value}\n";
} else {
    echo "Default Tariff Setting: Not found\n";
}

// Проверяем тариф "Старт"
$startTariff = \App\Models\TariffPlan::where('code', 'start')->first();
if ($startTariff) {
    echo "Start Tariff exists: ID={$startTariff->id}, Name={$startTariff->name}\n";
} else {
    echo "Start Tariff: Not found\n";
}
