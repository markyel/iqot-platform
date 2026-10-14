<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * outgoing_replies.claimed_at (connection=reports) — момент claim'а ответа
 * диспетчером emails:dispatch-replies (status → 'sending').
 *
 * Реклейм «застрявших» 'sending' раньше считался от created_at (updated_at в
 * таблице нет): старый ответ, повторно заклеймленный после ретрая, на следующем
 * тике сразу возвращался в 'pending' и уходил вторым job'ом → двойная отправка.
 * Теперь реклейм — по claimed_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('reports');
        if (!$schema->hasTable('outgoing_replies') || $schema->hasColumn('outgoing_replies', 'claimed_at')) {
            return;
        }

        $schema->table('outgoing_replies', function (Blueprint $table) {
            $table->dateTime('claimed_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('reports');
        if ($schema->hasTable('outgoing_replies') && $schema->hasColumn('outgoing_replies', 'claimed_at')) {
            $schema->table('outgoing_replies', function (Blueprint $table) {
                $table->dropColumn('claimed_at');
            });
        }
    }
};
