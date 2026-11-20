<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Перепроверка выбросов (OutlierRecheck, решение владельца 20.11.2026): цена страницы, отличающаяся от медианы
 * товара больше чем в 4 раза, не выбрасывается, а уходит на строгую перепроверку (recheck_reason) — свежая загрузка
 * и обязательное подтверждение ИИ товара и цены. Подтверждённая цена (outlier_checked_price) повторно не перепроверяется.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_listings') && !Schema::hasColumn('mp_listings', 'recheck_reason')) {
            Schema::table('mp_listings', function (Blueprint $table) {
                $table->string('recheck_reason', 30)->nullable()->after('status');
                $table->dateTime('outlier_checked_at')->nullable()->after('recheck_reason');
                $table->decimal('outlier_checked_price', 14, 2)->nullable()->after('outlier_checked_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_listings') && Schema::hasColumn('mp_listings', 'recheck_reason')) {
            Schema::table('mp_listings', function (Blueprint $table) {
                $table->dropColumn(['recheck_reason', 'outlier_checked_at', 'outlier_checked_price']);
            });
        }
    }
};
