<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ф2: индекс страниц сайтов продавцов из sitemap (без Яндекса). Сопоставление с товарами —
 * по артикулу в адресе и по транслиту названия (SitemapMatcher); найденное становится
 * листингом found_via=sitemap и проходит обычную проверку ListingChecker.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mp_site_pages')) {
            Schema::create('mp_site_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('site_id')->constrained('mp_sites')->cascadeOnDelete();
                $table->string('url', 1000);
                $table->char('url_hash', 40)->unique();
                $table->dateTime('lastmod')->nullable();
                $table->dateTime('seen_at');
                $table->index(['site_id', 'seen_at']);
            });
        }
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'sitemap_indexed_at')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dateTime('sitemap_indexed_at')->nullable()->after('sitemap_url');
                $table->unsignedInteger('sitemap_pages')->default(0)->after('sitemap_indexed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_site_pages');
        if (Schema::hasColumn('mp_sites', 'sitemap_indexed_at')) {
            Schema::table('mp_sites', fn (Blueprint $table) => $table->dropColumn(['sitemap_indexed_at', 'sitemap_pages']));
        }
    }
};
