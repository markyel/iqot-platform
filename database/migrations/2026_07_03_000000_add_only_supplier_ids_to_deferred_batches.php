<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deferred_batches (на reports): only_supplier_ids — пин пула для отсрочек по капасити
 * (reason='sender_capacity') и контейнмента бана (reason='ban_containment'). Повторная
 * генерация шлёт ТОЛЬКО этим поставщикам (кому письма были отложены/сняты) — без
 * дублей тем, кто уже получил письмо от другого под-батча/до бана.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('reports');
        $schema->table('deferred_batches', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('deferred_batches', 'only_supplier_ids')) {
                $table->json('only_supplier_ids')->nullable()->after('domain_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('reports')->table('deferred_batches', function (Blueprint $table) {
            $table->dropColumn('only_supplier_ids');
        });
    }
};
