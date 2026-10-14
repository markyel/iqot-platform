<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): программа мониторинга клиента — список номенклатуры +
 * режим получения цены (lite|hybrid) + целевая свежесть. Задача не закрывается.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_programs')) {
            return;
        }

        Schema::create('monitoring_programs', function (Blueprint $table) {
            $table->id();
            $table->char('external_id', 26)->unique();
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 255);
            $table->enum('price_mode', ['lite', 'hybrid'])->default('lite');
            $table->unsignedSmallInteger('target_freshness_days')->default(30);
            $table->unsignedSmallInteger('site_refresh_days')->default(14);
            $table->unsignedSmallInteger('email_refresh_days')->default(60);
            $table->unsignedInteger('discovery_rate')->default(500);
            $table->enum('status', ['draft', 'active', 'paused', 'archived'])->default('draft')->index();
            $table->string('paused_reason', 64)->nullable();
            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_active')->default(0);
            $table->unsignedInteger('items_matched')->default(0);
            $table->unsignedInteger('items_ambiguous')->default(0);
            $table->unsignedInteger('items_covered')->default(0);
            $table->string('webhook_url', 500)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_programs');
    }
};
