<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен, Ф1 (поиск листингов + выдача курсором):
 *  - program_items.prices_changed_at — момент изменения цен позиции (курсор выдачи
 *    GET …/prices?updated_since), ставит ListingChecker только при реальном изменении;
 *  - mp_listings.found_via — откуда листинг (yandex|site_search|bench|manual), для метрик;
 *  - mp_products.search_attempts — сколько раз искали (экспоненциальный повтор not_found).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_items') && !Schema::hasColumn('program_items', 'prices_changed_at')) {
            Schema::table('program_items', function (Blueprint $table) {
                $table->dateTime('prices_changed_at')->nullable()->after('price_source');
                $table->index(['program_id', 'prices_changed_at']);
            });
        }
        if (Schema::hasTable('mp_listings') && !Schema::hasColumn('mp_listings', 'found_via')) {
            Schema::table('mp_listings', function (Blueprint $table) {
                $table->string('found_via', 32)->nullable()->after('bench');
            });
        }
        if (Schema::hasTable('mp_products') && !Schema::hasColumn('mp_products', 'search_attempts')) {
            Schema::table('mp_products', function (Blueprint $table) {
                $table->unsignedSmallInteger('search_attempts')->default(0)->after('next_search_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('program_items', 'prices_changed_at')) {
            Schema::table('program_items', function (Blueprint $table) {
                $table->dropIndex(['program_id', 'prices_changed_at']);
                $table->dropColumn('prices_changed_at');
            });
        }
        if (Schema::hasColumn('mp_listings', 'found_via')) {
            Schema::table('mp_listings', fn (Blueprint $table) => $table->dropColumn('found_via'));
        }
        if (Schema::hasColumn('mp_products', 'search_attempts')) {
            Schema::table('mp_products', fn (Blueprint $table) => $table->dropColumn('search_attempts'));
        }
    }
};
