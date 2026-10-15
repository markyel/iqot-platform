<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ф3+ «Гибрид»: запрос КП у поставщиков без цены на сайте (решения владельца 2026-10-15):
 *  - mp_asks.site_id / listing_id — nullable: запрос пулу поставщиков (без найденной страницы);
 *  - mp_kp_offers — КП поставщиков по товарам мониторинга (из проверочных писем и запросов КП):
 *    отдельный источник цены с признаком «Есть КП / счёт», даже если цены на сайте нет.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_asks')) {
            DB::statement('ALTER TABLE mp_asks MODIFY site_id BIGINT UNSIGNED NULL, MODIFY listing_id BIGINT UNSIGNED NULL');
        }
        if (!Schema::hasTable('mp_kp_offers')) {
            Schema::create('mp_kp_offers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedInteger('supplier_id')->index();                 // reports.suppliers.id
                $table->unsignedBigInteger('ask_id')->nullable();
                $table->unsignedBigInteger('listing_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedInteger('request_item_id');                       // reports.request_items.id (тех. заявка)
                $table->string('source', 16);                                    // verify | on_request | pool
                $table->decimal('price', 14, 2);
                $table->char('currency', 3)->default('RUB');
                $table->boolean('vat_included')->nullable();
                $table->unsignedSmallInteger('delivery_days')->nullable();
                $table->string('notes', 255)->nullable();
                $table->dateTime('received_at');
                $table->timestamps();
                $table->unique(['request_item_id', 'supplier_id']);
                $table->index(['product_id', 'received_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_kp_offers');
    }
};
