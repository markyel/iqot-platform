<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('act_items')) {
            return;
        }

        Schema::create('act_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('act_id')->constrained()->onDelete('cascade');

            // Тип услуги
            $table->enum('type', ['subscription', 'price_monitoring', 'catalog_access']); // Тип услуги

            // Связь с источником списания
            $table->foreignId('subscription_charge_id')->nullable()->constrained('subscription_charges')->onDelete('set null');
            $table->foreignId('balance_charge_id')->nullable()->constrained('balance_charges')->onDelete('set null');
            $table->foreignId('report_access_id')->nullable()->constrained('report_accesses')->onDelete('set null');

            // Позиция
            $table->integer('sort_order')->default(0);
            $table->text('name'); // Наименование услуги (формируется автоматически)
            $table->string('unit', 10)->default('шт'); // Единица измерения
            $table->decimal('quantity', 10, 2)->default(1); // Количество
            $table->decimal('price', 10, 2); // Цена за единицу
            $table->decimal('sum', 10, 2); // Сумма

            $table->timestamps();

            $table->index('act_id');
            $table->index(['type', 'subscription_charge_id']);
            $table->index(['type', 'balance_charge_id']);
            $table->index(['type', 'report_access_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('act_items');
    }
};
