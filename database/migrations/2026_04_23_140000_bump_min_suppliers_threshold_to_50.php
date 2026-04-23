<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Поднимает default min_suppliers_threshold c 20 до 50.
 *
 * Политика: 20 оказалось недостаточно — при broadcast по offer/price заявке
 * процент ответов остаётся низким, нужен более широкий пул.
 * Обновляем всех, кто ещё на прошлом default (=20); ручные переопределения
 * (иные значения) не трогаем.
 */
return new class extends Migration {
    public function up(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            DB::connection('reports')->statement(
                'ALTER TABLE product_types MODIFY COLUMN min_suppliers_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 50'
            );

            DB::connection('reports')->table('product_types')
                ->where('min_suppliers_threshold', 20)
                ->update(['min_suppliers_threshold' => 50]);
        }
    }

    public function down(): void
    {
        $reports = Schema::connection('reports');

        if ($reports->hasColumn('product_types', 'min_suppliers_threshold')) {
            DB::connection('reports')->statement(
                'ALTER TABLE product_types MODIFY COLUMN min_suppliers_threshold SMALLINT UNSIGNED NOT NULL DEFAULT 20'
            );
            DB::connection('reports')->table('product_types')
                ->where('min_suppliers_threshold', 50)
                ->update(['min_suppliers_threshold' => 20]);
        }
    }
};
