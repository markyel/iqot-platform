<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Отметка времени последней отправки письма ящиком (секундная точность).
 *
 * Используется атомарным «замком интервала» в SendQueuedEmailJob:
 * UPDATE ... WHERE last_send_at IS NULL OR last_send_at <= NOW()-send_delay_seconds.
 * Гарантирует паузу между письмами одного отправителя при многопоточной рассылке.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if (!$reports->hasColumn('senders', 'last_send_at')) {
            $reports->table('senders', function (Blueprint $table) {
                $table->timestamp('last_send_at')->nullable()->after('last_send_date');
                $table->index('last_send_at', 'idx_senders_last_send_at');
            });
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasColumn('senders', 'last_send_at')) {
            $reports->table('senders', function (Blueprint $table) {
                $table->dropIndex('idx_senders_last_send_at');
                $table->dropColumn('last_send_at');
            });
        }
    }
};
