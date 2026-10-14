<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг цен (Ф0): асинхронный импорт номенклатуры (API-пачка или файл).
 * Строки лежат в payload_path (storage/app/monitoring/imports), не в БД.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mp_imports')) {
            return;
        }

        Schema::create('mp_imports', function (Blueprint $table) {
            $table->id();
            $table->char('external_id', 26)->unique();
            $table->foreignId('program_id')->constrained('monitoring_programs')->cascadeOnDelete();
            $table->enum('source', ['api', 'file']);
            $table->string('idempotency_key', 128)->nullable();
            $table->enum('status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_ok')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->json('errors')->nullable();
            $table->string('payload_path', 500)->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mp_imports');
    }
};
