<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * monitoring_programs.discovery_rate: 0 = без собственного потолка программы — скорость поиска
 * задаёт агрессивность (MonitoringSettings). Решение владельца 2026-10-15: потолок 500/сутки по
 * умолчанию убираем (30 тыс. позиций искались бы ≥60 дней при любых настройках).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('monitoring_programs', 'discovery_rate')) {
            return;
        }
        DB::statement('ALTER TABLE monitoring_programs MODIFY discovery_rate INT UNSIGNED NOT NULL DEFAULT 0');
        DB::table('monitoring_programs')->update(['discovery_rate' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('monitoring_programs', 'discovery_rate')) {
            DB::statement('ALTER TABLE monitoring_programs MODIFY discovery_rate INT UNSIGNED NOT NULL DEFAULT 500');
        }
    }
};
