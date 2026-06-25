<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Расширение jobs.attempts с TINYINT (макс. 255) до INT UNSIGNED.
 *
 * Защита от «ядовитых» job'ов: при многопоточной рассылке голодающий по слоту
 * job делает release() сотни раз (tries=0 + retryUntil(30 мин)), инкрементя
 * attempts. На 256-м переносе TINYINT переполнялся → SQLSTATE[22003] при pop'е,
 * job становился ядовитым и валил воркеры → очередь `emails` вставала колом.
 *
 * Исходный тип задан в 2026_01_28_000000_create_jobs_table.php
 * ($table->unsignedTinyInteger('attempts')). jobs живёт на дефолтном коннекте.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jobs MODIFY attempts INT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jobs MODIFY attempts TINYINT UNSIGNED NOT NULL');
    }
};
