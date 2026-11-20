<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Дожим» поиска (решение владельца 21.11.2026): лёгкий поиск по пресету не дал цен — сразу заходы глубже
 * (monitoring.search.escalation). search_level — ступень следующего захода, last_queries — уже заданные
 * Яндексу запросы (не повторяем).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_products') && !Schema::hasColumn('mp_products', 'search_level')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->unsignedTinyInteger('search_level')->default(0)->after('search_attempts');
                $table->json('last_queries')->nullable()->after('search_level');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_products') && Schema::hasColumn('mp_products', 'search_level')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->dropColumn(['search_level', 'last_queries']);
            });
        }
    }
};
