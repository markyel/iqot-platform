<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляем 'bounce' в ENUM unidentified_emails.reason (connection=reports).
 *
 * IncomingEmailRouter помечает отбойники (NDR) reason='bounce', но в исходном
 * ENUM('no_token','no_batch','no_supplier','no_match') такого значения не было →
 * MySQL молча усекал его до '' при вставке (см. строки с пустым reason). После
 * добавления значения и приём, и бэкфилл пишут корректный 'bounce'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('reports')->hasTable('unidentified_emails')) {
            return;
        }

        DB::connection('reports')->statement(
            "ALTER TABLE unidentified_emails MODIFY reason "
            . "ENUM('no_token','no_batch','no_supplier','no_match','bounce') "
            . "NULL DEFAULT 'no_token'"
        );
    }

    public function down(): void
    {
        if (!Schema::connection('reports')->hasTable('unidentified_emails')) {
            return;
        }

        // Перед сужением ENUM убираем 'bounce', иначе строки станут '' — возвращаем
        // в 'no_token' (исходная классификация до фичи отбойников).
        DB::connection('reports')->table('unidentified_emails')
            ->where('reason', 'bounce')
            ->update(['reason' => 'no_token']);

        DB::connection('reports')->statement(
            "ALTER TABLE unidentified_emails MODIFY reason "
            . "ENUM('no_token','no_batch','no_supplier','no_match') "
            . "NULL DEFAULT 'no_token'"
        );
    }
};
