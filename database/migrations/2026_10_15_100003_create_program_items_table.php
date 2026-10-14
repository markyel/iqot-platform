<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): позиция программы — как её прислал клиент (raw_*) + ссылка
 * на канонический товар. client_ref уникален в программе (upsert при приёме).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('program_items')) {
            return;
        }

        Schema::create('program_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('monitoring_programs')->cascadeOnDelete();
            $table->string('client_ref', 191);
            $table->string('raw_name', 1000);
            $table->string('raw_brand', 255)->nullable();
            $table->string('raw_article', 255)->nullable();
            $table->string('raw_unit', 32)->nullable();
            $table->json('raw_attributes')->nullable();
            $table->foreignId('product_id')->nullable()->constrained('mp_products')->nullOnDelete();
            $table->enum('match_status', ['pending', 'matched', 'ambiguous', 'rejected'])->default('pending');
            $table->string('match_note', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->tinyInteger('priority')->default(0);
            $table->dateTime('last_priced_at')->nullable();
            $table->enum('price_source', ['site', 'kp', 'none'])->default('none');
            $table->dateTime('next_due_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['program_id', 'client_ref']);
            $table->index(['program_id', 'active', 'match_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_items');
    }
};
