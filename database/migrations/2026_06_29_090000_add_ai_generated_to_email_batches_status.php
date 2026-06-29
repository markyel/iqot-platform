<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * email_batches.status получил значение 'ai_generated' в коде генератора рассылок
 * (CampaignPersister, коммит 656e2d9), но энум не был расширен → каждый батч падал
 * с "Data truncated for column 'status'", генерация писем стояла. Добавляем значение.
 *
 * Таблица живёт на коннекте `reports`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('reports')->statement(
            "ALTER TABLE email_batches MODIFY COLUMN status "
            . "ENUM('pending','ai_generated','queued','completed','error','reassigned') "
            . "NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        DB::connection('reports')->statement(
            "ALTER TABLE email_batches MODIFY COLUMN status "
            . "ENUM('pending','queued','completed','error','reassigned') "
            . "NULL DEFAULT 'pending'"
        );
    }
};
