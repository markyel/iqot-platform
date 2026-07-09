<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Адаптивный дневной cap получателя (reports.recipient_mailboxes.daily_cap).
 * null → база из конфига (recipient_daily_cap). emails:recompute-recipient-caps
 * двигает его по вовлечённости: ответил → к max (15), нет реакции/баунсы → к min (5).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasColumn('recipient_mailboxes', 'daily_cap')) {
            Schema::connection($this->conn)->table('recipient_mailboxes', function (Blueprint $table) {
                $table->unsignedSmallInteger('daily_cap')->nullable()->after('daily_sent_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->conn)->hasColumn('recipient_mailboxes', 'daily_cap')) {
            Schema::connection($this->conn)->table('recipient_mailboxes', function (Blueprint $table) {
                $table->dropColumn('daily_cap');
            });
        }
    }
};
