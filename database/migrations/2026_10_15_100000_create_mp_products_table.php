<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): канонический товар — общий для всех клиентов и программ.
 * Поиск листингов, обход и доверие к сайтам делаются один раз на товар.
 * product_type_id / domain_id — логические ссылки на reports (int signed, без FK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_products')) {
            return;
        }

        Schema::create('mp_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 1000);
            $table->string('brand', 255)->nullable();
            $table->string('brand_norm', 191)->nullable()->index();
            $table->enum('unit_kind', ['piece', 'meter', 'kg', 'liter', 'set', 'pack', 'unknown'])->default('unknown');
            $table->integer('product_type_id')->nullable()->index();
            $table->integer('domain_id')->nullable();
            $table->string('classification_source', 32)->nullable();
            $table->enum('search_state', ['new', 'searching', 'found', 'not_found', 'exhausted'])->default('new')->index();
            $table->dateTime('last_search_at')->nullable();
            $table->dateTime('next_search_at')->nullable();
            $table->unsignedSmallInteger('listings_active')->default(0);
            $table->unsignedSmallInteger('listings_priced')->default(0);
            $table->unsignedBigInteger('merged_into_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_products');
    }
};
