<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** mp_budget_ledger.resource += fetch_index (обход карточек по sitemap, Ф2). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_budget_ledger')) {
            DB::statement("ALTER TABLE mp_budget_ledger MODIFY resource ENUM('yandex_query','fetch_http','fetch_headless','fetch_index','llm_call','email') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_budget_ledger')) {
            DB::table('mp_budget_ledger')->where('resource', 'fetch_index')->delete();
            DB::statement("ALTER TABLE mp_budget_ledger MODIFY resource ENUM('yandex_query','fetch_http','fetch_headless','llm_call','email') NOT NULL");
        }
    }
};
