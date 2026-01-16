<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tariff_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Код тарифа: start, basic, extended, professional
            $table->string('name'); // Название тарифа
            $table->text('description')->nullable(); // Описание
            $table->decimal('monthly_price', 10, 2)->default(0); // Ежемесячная плата
            $table->integer('items_limit')->nullable(); // Лимит позиций в заявках (null = безлимит)
            $table->integer('reports_limit')->nullable(); // Лимит открытых отчетов (null = безлимит)
            $table->decimal('price_per_item_over_limit', 10, 2)->default(0); // Цена за позицию сверх лимита
            $table->decimal('price_per_report_over_limit', 10, 2)->default(0); // Цена за отчет сверх лимита
            $table->json('features')->nullable(); // Дополнительный функционал (будущее: team_size, pdf_export, etc)
            $table->boolean('is_active')->default(true); // Активен ли тариф
            $table->integer('sort_order')->default(0); // Порядок сортировки
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_plans');
    }
};
