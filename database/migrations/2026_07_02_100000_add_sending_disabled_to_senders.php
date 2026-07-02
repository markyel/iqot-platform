<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * senders (на reports): sending_disabled + spam_reject_count.
 *
 * Разделяем «нельзя слать новое» и «живой ящик для приёма». Раньше единственным
 * рычагом был is_active, но он выключает И генерацию, И приём (DispatchPendingReceives
 * тоже фильтрует is_active=1) — из-за чего у «сдохших» отправителей переставали
 * забираться ответы. Теперь:
 *   - генерация (CampaignSenderAssigner) исключает sending_disabled=1;
 *   - приём (DispatchPendingReceives) по-прежнему смотрит только is_active=1.
 * Так спам-помеченный/сломанный на отправку ящик остаётся is_active=1
 * (продолжает читаться), но sending_disabled=1 (новых писем с него не шлём).
 * spam_reject_count — накопитель спам-реджектов по отправителю (авто-disable при пороге).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->boolean('sending_disabled')->default(false)->after('is_active');
            $table->unsignedInteger('spam_reject_count')->default(0)->after('sending_disabled');
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->dropColumn(['sending_disabled', 'spam_reject_count']);
        });
    }
};
