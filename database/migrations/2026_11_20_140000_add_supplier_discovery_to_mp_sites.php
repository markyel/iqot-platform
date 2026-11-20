<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mp_sites.supplier_discovery_* — сайт мониторинга с ценой, но без поставщика IQOT, проверяется как кандидат
 * в поставщики (SiteSupplierDiscovery, решение владельца 20.11.2026): когда проверяли и чем кончилось.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_sites') && !Schema::hasColumn('mp_sites', 'supplier_discovery_at')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dateTime('supplier_discovery_at')->nullable()->after('supplier_id');
                $table->string('supplier_discovery_result', 60)->nullable()->after('supplier_discovery_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mp_sites') && Schema::hasColumn('mp_sites', 'supplier_discovery_at')) {
            Schema::table('mp_sites', function (Blueprint $table) {
                $table->dropColumn(['supplier_discovery_at', 'supplier_discovery_result']);
            });
        }
    }
};
