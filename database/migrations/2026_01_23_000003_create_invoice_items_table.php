<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_items')) {
            return;
        }

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');

            // Позиция
            $table->integer('sort_order')->default(0); // Порядок сортировки
            $table->text('name'); // Наименование услуги
            $table->string('unit', 10)->default('шт'); // Единица измерения
            $table->decimal('quantity', 10, 2)->default(1); // Количество
            $table->decimal('price', 10, 2); // Цена за единицу
            $table->decimal('sum', 10, 2); // Сумма (quantity * price)

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
