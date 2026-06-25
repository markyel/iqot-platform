<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Якорь адаптивного пейсинга по получателю (reports.recipient_mailboxes).
 *
 * Диспетчер (DispatchPendingEmails) размазывает письма одному to_email по
 * остатку рабочего окна: interval = clamp(остаток_окна / pending_получателю,
 * MIN, MAX), не больше одного письма получателю за тик. Момент последней
 * раздачи фиксируется здесь (last_dispatched_at) — ставится при клейме письма,
 * а НЕ в момент успешной отправки (recordSuccess), чтобы между тиком диспетчера
 * и асинхронной отправкой не было гонки двойной раздачи.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if (!$reports->hasTable('recipient_mailboxes')) {
            return;
        }
        if ($reports->hasColumn('recipient_mailboxes', 'last_dispatched_at')) {
            return;
        }

        $reports->table('recipient_mailboxes', function (Blueprint $table) {
            $table->timestamp('last_dispatched_at')->nullable()->after('last_success_at')->index();
        });
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if (!$reports->hasTable('recipient_mailboxes')) {
            return;
        }

        $reports->table('recipient_mailboxes', function (Blueprint $table) {
            $table->dropColumn('last_dispatched_at');
        });
    }
};
