<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * program_items.client_category_id — категория заказчика из API (тот же client_category, что в
 * заявках). Классификация товаров мониторинга по таксономии IQOT идёт ПО КАТЕГОРИИ (соответствие
 * категория заказчика → product_type/domain; ≤1 GPT-вызов на категорию), а не поштучно gpt-4o
 * (решение владельца 2026-10-15: 30 тыс. вызовов → не больше ~100).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_items') && !Schema::hasColumn('program_items', 'client_category_id')) {
            Schema::table('program_items', function (Blueprint $table) {
                $table->unsignedBigInteger('client_category_id')->nullable()->after('raw_unit')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('program_items', 'client_category_id')) {
            Schema::table('program_items', function (Blueprint $table) {
                $table->dropIndex(['client_category_id']);
                $table->dropColumn('client_category_id');
            });
        }
    }
};
