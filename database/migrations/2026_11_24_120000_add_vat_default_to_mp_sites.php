<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * НДС по умолчанию для сайта (24.11.2026, решение владельца): сайт указывает НДС одинаково для всех товаров — где у цены
 * подписи нет, берём преобладающий признак сайта (≥5 определённых цен, ≥90% одинаковые). Считает monitoring:refresh-coverage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'vat_default')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->boolean('vat_default')->nullable()->after('publishes_stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_sites') && Schema::hasColumn('mp_sites', 'vat_default')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dropColumn('vat_default');
            });
        }
    }
};
