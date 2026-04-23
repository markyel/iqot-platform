<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Спека §3.9 + §3.10. Расширения в reports-БД:
 *  - requests: + source, + api_submission_external_id.
 *  - product_types: + min_suppliers_threshold (NOT NULL default 8).
 *  - domain_product_types: + min_suppliers_threshold (NULL, переопределение на паре).
 *
 * Каждое изменение выполняется проверкой Schema::hasColumn чтобы быть
 * идемпотентным относительно внешних SQL-накатов (reports БД может управляться
 * вне Laravel).
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasTable('requests')) {
            $reports->table('requests', function (Blueprint $table) use ($reports) {
                if (!$reports->hasColumn('requests', 'source')) {
                    $table->enum('source', ['web', 'admin', 'api'])->default('web')->after('user_id');
                }
                if (!$reports->hasColumn('requests', 'api_submission_external_id')) {
                    $table->char('api_submission_external_id', 26)->nullable()->after('source');
                }
            });

            $reports->table('requests', function (Blueprint $table) use ($reports) {
                // Индексы добавляем отдельной пачкой — ищутся по имени.
                $indexes = collect($reports->getConnection()->select("SHOW INDEX FROM requests"))
                    ->pluck('Key_name')->all();
                if (!in_array('idx_source', $indexes, true)) {
                    $table->index('source', 'idx_source');
                }
                if (!in_array('idx_api_submission', $indexes, true)) {
                    $table->index('api_submission_external_id', 'idx_api_submission');
                }
            });
        }

        if ($reports->hasTable('product_types') && !$reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            $reports->table('product_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('min_suppliers_threshold')->default(8);
            });
        }

        if ($reports->hasTable('domain_product_types')
            && !$reports->hasColumn('domain_product_types', 'min_suppliers_threshold')
        ) {
            $reports->table('domain_product_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('min_suppliers_threshold')->nullable();
            });
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasTable('requests')) {
            $reports->table('requests', function (Blueprint $table) use ($reports) {
                $indexes = collect($reports->getConnection()->select("SHOW INDEX FROM requests"))
                    ->pluck('Key_name')->all();
                if (in_array('idx_source', $indexes, true)) {
                    $table->dropIndex('idx_source');
                }
                if (in_array('idx_api_submission', $indexes, true)) {
                    $table->dropIndex('idx_api_submission');
                }
                if ($reports->hasColumn('requests', 'api_submission_external_id')) {
                    $table->dropColumn('api_submission_external_id');
                }
                if ($reports->hasColumn('requests', 'source')) {
                    $table->dropColumn('source');
                }
            });
        }

        if ($reports->hasTable('product_types') && $reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            $reports->table('product_types', function (Blueprint $table) {
                $table->dropColumn('min_suppliers_threshold');
            });
        }

        if ($reports->hasTable('domain_product_types')
            && $reports->hasColumn('domain_product_types', 'min_suppliers_threshold')
        ) {
            $reports->table('domain_product_types', function (Blueprint $table) {
                $table->dropColumn('min_suppliers_threshold');
            });
        }
    }
};
