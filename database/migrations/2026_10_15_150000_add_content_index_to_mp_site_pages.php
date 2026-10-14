<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ф2, вариант 2 (решение владельца 14.10.2026): индекс СОДЕРЖИМОГО карточек из sitemap —
 * заголовок/H1 и артикулы со страницы (JSON-LD/microdata sku/mpn, «Артикул: …»). Нужен там,
 * где адрес не несёт ни артикула, ни названия (revator …/RR3096/, nlp-group), а поиск по
 * сайту закрыт robots.txt (13 из 14 топ-доменов). Страницы товара robots.txt не запрещает.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mp_site_pages') || Schema::hasColumn('mp_site_pages', 'fetched_at')) {
            return;
        }
        Schema::table('mp_site_pages', function (Blueprint $table) {
            $table->string('title', 500)->nullable()->after('lastmod');
            $table->text('codes')->nullable()->after('title');            // нормализованные артикулы через пробел
            $table->dateTime('index_queued_at')->nullable()->after('seen_at');
            $table->dateTime('fetched_at')->nullable()->after('index_queued_at');
            $table->unsignedSmallInteger('http_status')->nullable()->after('fetched_at');
            $table->index(['site_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('mp_site_pages', 'fetched_at')) {
            Schema::table('mp_site_pages', function (Blueprint $table) {
                $table->dropIndex(['site_id', 'fetched_at']);
                $table->dropColumn(['title', 'codes', 'index_queued_at', 'fetched_at', 'http_status']);
            });
        }
    }
};
