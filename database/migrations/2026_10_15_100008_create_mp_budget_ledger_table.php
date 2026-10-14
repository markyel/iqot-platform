<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): суточный учёт расхода ресурсов (Яндекс, загрузки, headless,
 * LLM, письма) — общий (program_id = 0) и по программам. Инкремент атомарный:
 * INSERT … ON DUPLICATE KEY UPDATE по unique (day, program_id, resource).
 * program_id = 0 вместо NULL: NULL в unique-ключе MySQL не даёт дедупликации.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_budget_ledger')) {
            return;
        }

        Schema::create('mp_budget_ledger', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->unsignedBigInteger('program_id')->default(0);
            $table->enum('resource', ['yandex_query', 'fetch_http', 'fetch_headless', 'llm_call', 'email']);
            $table->unsignedInteger('units')->default(0);
            $table->decimal('cost_usd', 12, 6)->default(0);
            $table->decimal('cost_rub', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['day', 'program_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_budget_ledger');
    }
};
