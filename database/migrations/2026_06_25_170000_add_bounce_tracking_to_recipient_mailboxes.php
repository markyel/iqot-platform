<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отдельный счётчик отбойников (NDR) для recipient_mailboxes.
 *
 * Зачем отдельный, а не consecutive_errors: успешная отправка (SMTP-handshake
 * принят) вызывает RecipientMailbox::recordSuccess() и обнуляет consecutive_errors.
 * Но отбойник о недоставке приходит ПОЗЖЕ принятия письма сервером — если бы он
 * писал в тот же счётчик, следующая удачная отправка снова сбросила бы его, и
 * порог «критической массы» никогда бы не набрался. Поэтому bounce_count живёт
 * сам по себе; recordSuccess его НЕ трогает (см. модель).
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if (!$reports->hasTable('recipient_mailboxes')) {
            return;
        }

        $reports->table('recipient_mailboxes', function (Blueprint $table) {
            $table->unsignedInteger('bounce_count')->default(0)->after('consecutive_errors');
            $table->string('last_bounce_message', 255)->nullable()->after('last_error_message');
            $table->timestamp('last_bounce_at')->nullable()->after('last_error_at');
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('recipient_mailboxes', function (Blueprint $table) {
            $table->dropColumn(['bounce_count', 'last_bounce_message', 'last_bounce_at']);
        });
    }
};
