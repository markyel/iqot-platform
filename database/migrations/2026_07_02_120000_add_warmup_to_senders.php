<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * senders (на reports): прогрев отправителя (Phase 3).
 *   - daily_limit        — дневной лимит писем ящика; стартует малым (warmup.start=30),
 *                          растёт на warmup.step_pct% за успешный день до warmup.cap=500.
 *                          Гейтит ГЕНЕРАЦИЮ: назначаем ящику ≤ остатка лимита (assigned_today),
 *                          большой батч бьётся на несколько ящиков (sub-batches).
 *   - warmup_updated_on  — дата последнего пересчёта лимита (чтобы рампа раз в день).
 *   - banned_once        — был ли уже бан: 1-й бан → сброс лимита в минимум,
 *                          повторный → sending_disabled=1 (блок, приём остаётся).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->unsignedInteger('daily_limit')->default(30)->after('spam_reject_count');
            $table->date('warmup_updated_on')->nullable()->after('daily_limit');
            $table->boolean('banned_once')->default(false)->after('warmup_updated_on');
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->dropColumn(['daily_limit', 'warmup_updated_on', 'banned_once']);
        });
    }
};
