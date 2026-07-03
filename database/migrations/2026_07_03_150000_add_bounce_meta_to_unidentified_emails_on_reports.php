<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * unidentified_emails (на reports): метаданные отбойника (NDR) для точного анализа.
 *   - bounce_reason     — классификация недоставки (permanent/spam/temporary/unknown),
 *                         вычисляется на приёме classifyBounceReason (читает вложение-DSN,
 *                         которое мы НЕ храним) → сохраняем результат, чтобы позже считать
 *                         долю спам-реджекта по ящику-отправителю без перечтения тела.
 *   - failed_recipient  — адрес, на который письмо не дошло (из DSN Final-Recipient) →
 *                         принимающий домен для анализа «строгий получатель vs наш ящик».
 * Таблица создаётся внешне (n8n) — ALTER идемпотентный (hasColumn-гварды).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('reports');
        if (!$schema->hasTable('unidentified_emails')) {
            return;
        }
        $schema->table('unidentified_emails', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('unidentified_emails', 'bounce_reason')) {
                $table->string('bounce_reason', 20)->nullable()->after('reason');
            }
            if (!$schema->hasColumn('unidentified_emails', 'failed_recipient')) {
                $table->string('failed_recipient', 255)->nullable()->after('bounce_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('unidentified_emails', function (Blueprint $table) {
            $table->dropColumn(['bounce_reason', 'failed_recipient']);
        });
    }
};
