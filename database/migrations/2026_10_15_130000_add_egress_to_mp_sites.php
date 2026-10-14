<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mp_sites.egress — исходящий канал мониторинга (EgressPool), закреплённый за сайтом:
 * сайт видит один стабильный IP; при бане канала сайт переезжает на следующий.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'egress')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->string('egress', 32)->nullable()->after('fetch_mode');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_sites') && Schema::hasColumn('mp_sites', 'egress')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dropColumn('egress');
            });
        }
    }
};
