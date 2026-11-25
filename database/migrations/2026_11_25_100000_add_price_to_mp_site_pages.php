<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Цена в индексе каталогов сайтов-лидеров (25.11.2026, решение владельца): при индексации карточки кроме названия и
 * артикулов — цена, валюта, наличие, НДС (разметка или рецепт сайта, без ИИ).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_site_pages') && !Schema::hasColumn('mp_site_pages', 'price')) {
            Schema::table('mp_site_pages', function (Blueprint $table) {
                $table->decimal('price', 14, 2)->nullable()->after('codes');
                $table->string('currency', 3)->nullable()->after('price');
                $table->string('availability', 16)->nullable()->after('currency');
                $table->boolean('vat_included')->nullable()->after('availability');
                $table->string('price_source', 16)->nullable()->after('vat_included');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_site_pages') && Schema::hasColumn('mp_site_pages', 'price')) {
            Schema::table('mp_site_pages', function (Blueprint $table) {
                $table->dropColumn(['price', 'currency', 'availability', 'vat_included', 'price_source']);
            });
        }
    }
};
