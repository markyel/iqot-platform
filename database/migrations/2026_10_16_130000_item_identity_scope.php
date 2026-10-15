<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Идентичность позиции клиента ≠ идентичность общего товара (инцидент 15.10.2026, M02502):
 * товар из пилота liftpages нёс кросс-номера разных длин ремня, и позиции другого клиента
 * доставались страницы чужих вариантов.
 *  - program_items.oem_articles — все OEM-артикулы позиции от клиента (API `oem_articles`);
 *  - program_items.identity_codes — нормализованные коды позиции (артикул + oem_articles или
 *    коды из наименования): клиенту показываются только страницы, найденные по ЕГО кодам;
 *  - mp_listings.matched_codes — какие коды товара реально есть на странице (URL, заголовок,
 *    блок товара, sku разметки). NULL — ещё не вычислено (листинг до этой миграции).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('program_items', 'identity_codes')) {
            Schema::table('program_items', function (Blueprint $table) {
                $table->json('oem_articles')->nullable()->after('raw_article');
                $table->json('identity_codes')->nullable()->after('oem_articles');
            });
        }
        if (!Schema::hasColumn('mp_listings', 'matched_codes')) {
            Schema::table('mp_listings', function (Blueprint $table) {
                $table->json('matched_codes')->nullable()->after('match_evidence');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('program_items', 'identity_codes')) {
            Schema::table('program_items', fn (Blueprint $t) => $t->dropColumn(['oem_articles', 'identity_codes']));
        }
        if (Schema::hasColumn('mp_listings', 'matched_codes')) {
            Schema::table('mp_listings', fn (Blueprint $t) => $t->dropColumn('matched_codes'));
        }
    }
};
