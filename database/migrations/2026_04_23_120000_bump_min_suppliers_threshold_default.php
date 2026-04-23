<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Поднимает default min_suppliers_threshold c 8 до 20.
 *
 * Политика: 8 было начальным значением, проставленным миграцией 100013 для
 * всех существующих product_types. Менее 20 активных B2B-поставщиков на пару
 * (domain, product_type) на практике приводит к рассылке в пустоту. Обновляем
 * всех, кто ещё на старом default (=8); ручные переопределения (иные значения)
 * не трогаем.
 */
return new class extends Migration {
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            // Меняем default у колонки. Требует doctrine/dbal, но в Laravel 11 можно так:
            DB::connection('reports')->statement(
                'ALTER TABLE product_types MODIFY COLUMN min_suppliers_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 20'
            );

            // Подтягиваем существующие default-значения (=8) до нового default.
            DB::connection('reports')->table('product_types')
                ->where('min_suppliers_threshold', 8)
                ->update(['min_suppliers_threshold' => 20]);
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            DB::connection('reports')->statement(
                'ALTER TABLE product_types MODIFY COLUMN min_suppliers_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 8'
            );
            DB::connection('reports')->table('product_types')
                ->where('min_suppliers_threshold', 20)
                ->update(['min_suppliers_threshold' => 8]);
        }
    }
};
