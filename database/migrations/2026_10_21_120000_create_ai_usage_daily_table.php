<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Расход ИИ по задачам за МСК-сутки (OpenAIClassifierClient пишет каждый вызов) — 21.10.2026. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_usage_daily')) {
            return;
        }
        Schema::create('ai_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('task', 60);
            $table->string('model', 60);
            $table->string('provider', 20);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('cached_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->decimal('cost_usd', 12, 4)->default(0);
            $table->decimal('cost_rub', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['day', 'task', 'model', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_daily');
    }
};
