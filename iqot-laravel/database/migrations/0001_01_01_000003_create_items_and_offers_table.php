<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Позиции в заявке
        Schema::create('request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('article')->nullable(); // Артикул
            $table->string('brand')->nullable();   // Марка/производитель
            $table->integer('quantity')->default(1);
            $table->string('unit')->default('шт'); // Единица измерения
            $table->text('description')->nullable();
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('avg_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->integer('offers_count')->default(0);
            $table->foreignId('best_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamps();
            
            $table->index('request_id');
            $table->fullText(['name', 'article', 'brand']);
        });

        // Коммерческие предложения
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 15, 2)->nullable(); // Цена за единицу
            $table->decimal('total_price', 15, 2)->nullable(); // Общая сумма
            $table->string('currency', 3)->default('RUB');
            $table->boolean('vat_included')->default(false); // Включён ли НДС
            $table->integer('delivery_days')->nullable(); // Срок поставки в днях
            $table->string('payment_terms')->nullable(); // Условия оплаты
            $table->text('notes')->nullable(); // Примечания
            $table->enum('source_type', [
                'email',   // Из письма
                'pdf',     // Из PDF
                'excel',   // Из Excel
                'word',    // Из Word
                'website', // Со страницы сайта
                'manual',  // Вручную
            ])->default('email');
            $table->string('source_file')->nullable(); // Путь к файлу-источнику
            $table->json('raw_data')->nullable(); // Сырые данные от AI
            $table->boolean('is_best')->default(false); // Лучшее предложение
            $table->timestamps();
            
            $table->index(['request_id', 'supplier_id']);
            $table->index(['request_item_id', 'price']);
            $table->index('is_best');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('request_items');
    }
};
