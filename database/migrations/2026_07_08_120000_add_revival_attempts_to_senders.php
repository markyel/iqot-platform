<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * senders (reports): revival_attempts — счётчик НЕУДАЧНЫХ пробационных возвратов
 * (эскалация кулдауна возврата: 1 провал → 7д, 2 → 14д, 3 → 30д + banned_once).
 * Отдельно от block_count (тот — 30-мин ratelimit-блоки SendQueuedEmailJob и его
 * деактивация «3-й блок/сутки»), чтобы счётчики не пересекались.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->unsignedInteger('revival_attempts')->default(0)->after('block_count');
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->dropColumn('revival_attempts');
        });
    }
};
