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
        Schema::create('public_catalog_items', function (Blueprint $table) {
            $table->id();

            // ID из external БД
            $table->unsignedBigInteger('external_item_id')->unique();

            // Основная информация
            $table->string('name', 500);
            $table->string('brand', 100)->nullable();
            $table->string('article', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->string('unit', 50)->default('шт.');

            // Категоризация
            $table->string('category', 100)->nullable();
            $table->unsignedBigInteger('product_type_id')->nullable();
            $table->string('product_type_name', 100)->nullable();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->string('domain_name', 100)->nullable();

            // Информация о заявке
            $table->unsignedBigInteger('external_request_id');
            $table->string('request_number', 50);

            // Статистика предложений
            $table->integer('offers_count')->default(0);
            $table->decimal('min_price', 15, 2)->nullable();
            $table->decimal('max_price', 15, 2)->nullable();
            $table->string('currency', 10)->default('RUB');

            // Статус публикации
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();

            // Даты
            $table->timestamps();

            // Индексы для быстрого поиска
            $table->index('external_item_id');
            $table->index('product_type_id');
            $table->index('domain_id');
            $table->index('offers_count');
            $table->index('is_published');
            $table->index(['product_type_id', 'domain_id']);

            // Полнотекстовый поиск
            $table->fullText(['name', 'brand', 'article']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_catalog_items');
    }
};
