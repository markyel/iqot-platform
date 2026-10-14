<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Тарификация мониторинга цен в существующих планах (docs/monitoring-design.md §11a):
 *  - monitoring_items_included — покрытых позиций входит в абонплату;
 *  - monitoring_items_max — потолок позиций на мониторинге (0 = мониторинг недоступен);
 *  - monitoring_price_per_item — ₽/мес за покрытую web-позицию;
 *  - monitoring_kp_multiplier — множитель для позиции, покрытой только КП/счётом.
 * Стартовые значения: база 25 ₽ на «Старте», на старших — пропорционально цене
 * позиции в обычном режиме. Меняются в админке.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'start' => [0, 1000, 25],
        'basic' => [100, 5000, 22],
        'extended' => [500, 15000, 20],
        'professional' => [2000, 50000, 17],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('tariff_plans') || Schema::hasColumn('tariff_plans', 'monitoring_items_max')) {
            return;
        }

        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->unsignedInteger('monitoring_items_included')->default(0)->after('price_per_report_over_limit');
            $table->unsignedInteger('monitoring_items_max')->default(0)->after('monitoring_items_included');
            $table->decimal('monitoring_price_per_item', 8, 2)->default(0)->after('monitoring_items_max');
            $table->decimal('monitoring_kp_multiplier', 4, 2)->default(3.00)->after('monitoring_price_per_item');
        });

        foreach (self::DEFAULTS as $code => [$included, $max, $price]) {
            DB::table('tariff_plans')->where('code', $code)->update([
                'monitoring_items_included' => $included,
                'monitoring_items_max' => $max,
                'monitoring_price_per_item' => $price,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('tariff_plans') || !Schema::hasColumn('tariff_plans', 'monitoring_items_max')) {
            return;
        }

        Schema::table('tariff_plans', function (Blueprint $table) {
            $table->dropColumn(['monitoring_items_included', 'monitoring_items_max', 'monitoring_price_per_item', 'monitoring_kp_multiplier']);
        });
    }
};
