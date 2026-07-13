<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Колонка ai_attempts на email_messages (connection=reports) — счётчик попыток
 * AI-анализа письма.
 *
 * Предохранитель от «ядовитого» письма: AnalyzeSupplierReplyJob инкрементит
 * ai_attempts ДО тяжёлого парсинга вложений (таймаут/kill во время разбора всё
 * равно увеличивает счётчик), а выборка (AnalyzeSupplierReplies + loadMessage)
 * пропускает письма с ai_attempts >= max (services.email_analysis.max_attempts).
 * Без этого одно тяжёлое вложение (напр. 6.5 МБ PDF-каталог, виснущий в LZW-декодере
 * smalot/pdfparser дольше timeout=180s) роняло джоб каждые 30 мин бесконечно —
 * письмо не получало ai_processed=1 и переставлялось в очередь снова и снова.
 *
 * Таблица email_messages создавалась внешне (n8n) — добавляем колонку через ALTER
 * с проверкой hasColumn (идемпотентность).
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasTable('email_messages') && !$reports->hasColumn('email_messages', 'ai_attempts')) {
            $reports->table('email_messages', function (Blueprint $t) {
                $t->unsignedInteger('ai_attempts')->default(0)->after('ai_processed');
            });
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasTable('email_messages') && $reports->hasColumn('email_messages', 'ai_attempts')) {
            $reports->table('email_messages', function (Blueprint $t) {
                $t->dropColumn('ai_attempts');
            });
        }
    }
};
