<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Спека §3.11. Добавляет ключ api_access=false в tariff_plans.features (JSON).
 *
 * Фичи тарифов хранятся в колонке features JSON. Эта миграция мердж-апдейтом
 * добавляет api_access=false во все существующие планы (где features не null).
 * Планы с features=null — пропускаются, их features инициализируется {api_access:false}.
 */
return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('tariff_plans')->select('id', 'features')->get();

        foreach ($plans as $plan) {
            $features = $plan->features ? json_decode($plan->features, true) : [];
            if (!is_array($features)) {
                $features = [];
            }
            if (!array_key_exists('api_access', $features)) {
                $features['api_access'] = false;
                DB::table('tariff_plans')
                    ->where('id', $plan->id)
                    ->update(['features' => json_encode($features)]);
            }
        }
    }

    public function down(): void
    {
        $plans = DB::table('tariff_plans')->select('id', 'features')->get();

        foreach ($plans as $plan) {
            $features = $plan->features ? json_decode($plan->features, true) : [];
            if (!is_array($features) || !array_key_exists('api_access', $features)) {
                continue;
            }
            unset($features['api_access']);
            DB::table('tariff_plans')
                ->where('id', $plan->id)
                ->update(['features' => $features ? json_encode($features) : null]);
        }
    }
};
