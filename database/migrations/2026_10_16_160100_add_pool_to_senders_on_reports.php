<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reports.senders.pool: 'main' — основная рассылка, 'alias' — ящики алгоритма «ящик на батч»
 * (docs/alias-mailbox-design.md). Ящики 'alias' исключены из плана дня, назначения отправителей,
 * прогрева, возврата и опроса IMAP (App\Support\Senders\SenderPool). Прод-БД Beget.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('reports')->hasColumn('senders', 'pool')) {
            return;
        }
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->string('pool', 16)->default('main')->after('is_personal');
            $table->index('pool');
        });
        \App\Support\Senders\SenderPool::forget();
    }

    public function down(): void
    {
        if (!Schema::connection('reports')->hasColumn('senders', 'pool')) {
            return;
        }
        Schema::connection('reports')->table('senders', function (Blueprint $table) {
            $table->dropIndex(['pool']);
            $table->dropColumn('pool');
        });
    }
};
