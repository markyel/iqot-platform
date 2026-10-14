<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): профиль сайта поставщика (по registrable domain).
 * Поля доверия (trust_*, sample_every, verified_*) заводим сразу — логика в Ф3,
 * чтобы потом не мигрировать горячую таблицу. supplier_id → reports.suppliers.id
 * (int unsigned, без FK — другая БД).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites')) {
            return;
        }

        Schema::create('mp_sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 191)->unique();
            $table->unsignedInteger('supplier_id')->nullable()->index();
            $table->enum('kind', ['supplier', 'manufacturer', 'marketplace', 'aggregator', 'unknown'])->default('unknown');
            $table->boolean('publishes_prices')->nullable();
            $table->boolean('publishes_stock')->nullable();
            $table->boolean('has_structured_markup')->nullable();
            $table->string('search_url_template', 500)->nullable();
            $table->string('sitemap_url', 500)->nullable();
            $table->enum('fetch_mode', ['http', 'headless'])->default('http');
            $table->dateTime('blocked_until')->nullable();
            $table->string('blocked_reason', 120)->nullable();
            $table->unsignedInteger('rate_limit_ms')->default(1000);
            $table->unsignedTinyInteger('trust_level')->default(0);
            $table->enum('trust_scope', ['all', 'in_stock', 'availability', 'none'])->default('none');
            $table->unsignedSmallInteger('sample_every')->default(1);
            $table->unsignedInteger('verified_ok')->default(0);
            $table->unsignedInteger('verified_mismatch')->default(0);
            $table->unsignedInteger('consecutive_ok')->default(0);
            $table->dateTime('last_verified_at')->nullable();
            $table->dateTime('next_verification_due_at')->nullable();
            $table->json('stats_30d')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_sites');
    }
};
