<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): идентификаторы канонического товара.
 * Уникальность (kind, value_norm, brand_norm), а не (kind, value_norm): один артикул
 * у разных брендов — разные товары (урок Content Engine). brand_norm '' = бренд неизвестен.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_identifiers')) {
            return;
        }

        Schema::create('mp_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('mp_products')->cascadeOnDelete();
            $table->enum('kind', ['oem', 'mpn', 'ean', 'alias']);
            $table->string('value', 255);
            $table->string('value_norm', 191);
            $table->string('brand_norm', 191)->default('');
            $table->string('source', 32)->nullable();
            $table->timestamps();

            $table->unique(['kind', 'value_norm', 'brand_norm'], 'mp_identifiers_kind_value_brand_unique');
            $table->index('value_norm');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_identifiers');
    }
};
