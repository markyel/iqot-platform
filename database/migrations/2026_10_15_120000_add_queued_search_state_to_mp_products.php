<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * mp_products.search_state += 'queued': товар поставлен в очередь поиска, но
 * DiscoverProductJob ещё не выполнился. Раньше диспетчер сразу ставил 'searching',
 * и refresh-coverage закрывал такие товары как not_found (листингов ещё нет —
 * «непроверенных» тоже нет) — 313 ложных not_found на пилоте 14.10.2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mp_products')) {
            return;
        }
        DB::statement("ALTER TABLE mp_products MODIFY search_state ENUM('new','queued','searching','found','not_found','exhausted') NOT NULL DEFAULT 'new'");
        // Ложные not_found: поиск ни разу не выполнялся.
        DB::table('mp_products')->where('search_state', 'not_found')->where('search_attempts', 0)
            ->update(['search_state' => 'queued', 'next_search_at' => null]);
        DB::table('mp_products')->where('search_state', 'searching')->where('search_attempts', 0)
            ->update(['search_state' => 'queued']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('mp_products')) {
            return;
        }
        DB::table('mp_products')->where('search_state', 'queued')->update(['search_state' => 'new']);
        DB::statement("ALTER TABLE mp_products MODIFY search_state ENUM('new','searching','found','not_found','exhausted') NOT NULL DEFAULT 'new'");
    }
};
