<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): история проверок листинга — включая сбои (урок Content
 * Engine: без записи failed не посчитать надёжность сайта) и «цены нет».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_price_observations')) {
            return;
        }

        Schema::create('mp_price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('mp_listings')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->decimal('price', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->enum('price_kind', ['retail', 'from', 'wholesale_tier', 'on_request'])->nullable();
            $table->boolean('vat_included')->nullable();
            $table->enum('price_unit', ['piece', 'meter', 'kg', 'liter', 'set', 'pack', 'unknown'])->nullable();
            $table->enum('availability', ['in_stock', 'on_order', 'out_of_stock', 'unknown'])->nullable();
            $table->enum('method', ['structured', 'llm', 'unchanged', 'not_modified', 'kp', 'manual', 'none']);
            $table->enum('status', ['ok', 'suspicious', 'gone', 'failed', 'soft404', 'blocked', 'mismatch', 'no_price']);
            $table->string('note', 255)->nullable();
            $table->char('content_hash', 40)->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->dateTime('observed_at');

            $table->index(['listing_id', 'observed_at']);
            $table->index(['product_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_price_observations');
    }
};
