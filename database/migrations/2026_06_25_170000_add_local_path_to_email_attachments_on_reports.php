<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Колонка local_path на таблицах вложений (connection=reports) — путь локальной
 * копии на диске public.
 *
 * Переходный период (см. App\Services\Senders\IncomingEmailRouter,
 * App\Services\Senders\GoogleDriveUploader): вложения сохраняются И локально, И в
 * Google Drive. В file_path кладётся Drive-URL (его читает downstream-воркфлоу
 * «Process Email Conversations»), а в local_path — относительный путь локальной
 * копии (источник истины). После полного перехода на локал downstream правят на
 * чтение local_path, а Drive-дублирование выключают флагом ATTACHMENTS_DRIVE_ENABLED.
 *
 * Таблицы email_attachments / unidentified_email_attachments создавались внешне
 * (n8n), Laravel-миграции их не создавали — поэтому добавляем колонку через ALTER
 * с проверкой hasColumn (идемпотентность).
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        foreach (['email_attachments', 'unidentified_email_attachments'] as $table) {
            if ($reports->hasTable($table) && !$reports->hasColumn($table, 'local_path')) {
                $reports->table($table, function (Blueprint $t) {
                    $t->string('local_path', 1000)->nullable()->after('file_path');
                });
            }
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        foreach (['email_attachments', 'unidentified_email_attachments'] as $table) {
            if ($reports->hasTable($table) && $reports->hasColumn($table, 'local_path')) {
                $reports->table($table, function (Blueprint $t) {
                    $t->dropColumn('local_path');
                });
            }
        }
    }
};
