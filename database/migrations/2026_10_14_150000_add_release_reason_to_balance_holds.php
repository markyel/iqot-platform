<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * balance_holds.release_reason — почему заморозка снята: moderation_rejected,
 * moderation_timeout, deadline_expired, no_suppliers, cancelled, expired (срок
 * сбора КП истёк без ≥3 цен). По 'expired' биллинг ловит поздние КП и списывает
 * постфактум; остальные причины — позиция не разослана, списывать нечего.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('balance_holds') || Schema::hasColumn('balance_holds', 'release_reason')) {
            return;
        }

        Schema::table('balance_holds', function (Blueprint $table) {
            $table->string('release_reason', 32)->nullable()->after('released_at');
            $table->index(['status', 'release_reason']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('balance_holds') && Schema::hasColumn('balance_holds', 'release_reason')) {
            Schema::table('balance_holds', function (Blueprint $table) {
                $table->dropIndex(['status', 'release_reason']);
                $table->dropColumn('release_reason');
            });
        }
    }
};
