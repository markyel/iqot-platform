<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Находим тариф "Старт"
$startTariff = \App\Models\TariffPlan::where('code', 'start')->first();

if (!$startTariff) {
    echo "Тариф 'Старт' не найден!\n";
    exit(1);
}

echo "Тариф 'Старт' найден: ID={$startTariff->id}\n\n";

// Находим пользователей без активного тарифа
$usersWithoutTariff = \App\Models\User::whereDoesntHave('tariffs', function($query) {
    $query->where('is_active', true);
})
->where('is_admin', false)
->get();

echo "Пользователей без тарифа: " . $usersWithoutTariff->count() . "\n\n";

foreach ($usersWithoutTariff as $user) {
    echo "Назначаем тариф пользователю: {$user->name} (ID={$user->id})\n";

    \App\Models\UserTariff::create([
        'user_id' => $user->id,
        'tariff_plan_id' => $startTariff->id,
        'is_active' => true,
        'started_at' => now(),
        'items_used' => 0,
        'reports_used' => 0,
    ]);
}

echo "\nГотово!\n";
