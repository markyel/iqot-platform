<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Дневной счётчик писем на получателя (reports.recipient_mailboxes) — для жёсткого
 * пер-получательского потолка ЧЕРЕЗ ВСЕ ящики (анти-FBL). Инкремент при клейме
 * (markDispatched), сброс по смене дня (МСК). Диспетчер пропускает добравших лимит.
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasColumn('recipient_mailboxes', 'daily_sent_count')) {
            Schema::connection($this->conn)->table('recipient_mailboxes', function (Blueprint $table) {
                $table->unsignedInteger('daily_sent_count')->default(0)->after('last_dispatched_at');
                $table->date('daily_sent_date')->nullable()->after('daily_sent_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->conn)->hasColumn('recipient_mailboxes', 'daily_sent_count')) {
            Schema::connection($this->conn)->table('recipient_mailboxes', function (Blueprint $table) {
                $table->dropColumn(['daily_sent_count', 'daily_sent_date']);
            });
        }
    }
};
