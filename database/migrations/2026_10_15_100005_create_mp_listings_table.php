<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): листинг — товар × страница сайта поставщика. Текущая цена
 * со всеми признаками (за что, НДС, вид цены, наличие), состояние проверки и данные
 * для дешёвого переобхода (ETag/Last-Modified, хеш блока товара, финальный URL).
 * bench=1 — временные листинги стенда сверки с Content Engine, в выдачу не идут.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_listings')) {
            return;
        }

        Schema::create('mp_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('mp_products')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('mp_sites')->cascadeOnDelete();
            $table->string('url', 2000);
            $table->char('url_hash', 40);
            $table->boolean('bench')->default(false);
            $table->decimal('match_confidence', 3, 2)->nullable();
            $table->enum('match_method', ['oem_exact', 'oem_brand', 'structured_sku', 'llm_verified', 'manual'])->nullable();
            $table->string('match_evidence', 400)->nullable();
            $table->decimal('price', 14, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->enum('price_kind', ['retail', 'from', 'wholesale_tier', 'on_request'])->nullable();
            $table->boolean('vat_included')->nullable();
            $table->enum('price_unit', ['piece', 'meter', 'kg', 'liter', 'set', 'pack', 'unknown'])->nullable();
            $table->decimal('price_unit_qty', 12, 3)->nullable();
            $table->decimal('min_order_qty', 12, 3)->nullable();
            $table->enum('availability', ['in_stock', 'on_order', 'out_of_stock', 'unknown'])->nullable();
            $table->decimal('stock_qty', 12, 3)->nullable();
            $table->unsignedSmallInteger('lead_days')->nullable();
            $table->string('price_evidence', 400)->nullable();
            $table->enum('status', ['active', 'suspicious', 'gone', 'failed', 'mismatch', 'blocked'])->default('active');
            $table->unsignedSmallInteger('fail_count')->default(0);
            $table->dateTime('observed_at')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->dateTime('next_check_at')->nullable();
            $table->char('final_url_hash', 40)->nullable();
            $table->char('content_hash', 40)->nullable();
            $table->string('price_method', 16)->nullable();
            $table->string('etag', 255)->nullable();
            $table->string('last_modified', 255)->nullable();
            $table->decimal('confirmed_price', 14, 2)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'url_hash']);
            $table->index(['site_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['status', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_listings');
    }
};
