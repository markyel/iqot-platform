<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем существование таблицы tariff_plans
        if (!Schema::hasTable('tariff_plans')) {
            return;
        }

        // Получаем тариф "Старт"
        $startTariff = DB::table('tariff_plans')
            ->where('code', 'start')
            ->first();

        if ($startTariff) {
            // Добавляем настройку тарифа по умолчанию (если её ещё нет)
            $existingSetting = DB::table('settings')
                ->where('key', 'default_tariff_plan_id')
                ->first();

            if (!$existingSetting) {
                DB::table('settings')->insert([
                    'key' => 'default_tariff_plan_id',
                    'value' => $startTariff->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Назначаем тариф "Старт" всем пользователям без активного тарифа
            $usersWithoutTariff = DB::table('users')
                ->leftJoin('user_tariffs', function ($join) {
                    $join->on('users.id', '=', 'user_tariffs.user_id')
                        ->where('user_tariffs.is_active', '=', true);
                })
                ->whereNull('user_tariffs.id')
                ->where('users.is_admin', '=', false)
                ->select('users.id')
                ->get();

            foreach ($usersWithoutTariff as $user) {
                DB::table('user_tariffs')->insert([
                    'user_id' => $user->id,
                    'tariff_plan_id' => $startTariff->id,
                    'is_active' => true,
                    'started_at' => now(),
                    'items_used' => 0,
                    'reports_used' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'default_tariff_plan_id')->delete();
    }
};
