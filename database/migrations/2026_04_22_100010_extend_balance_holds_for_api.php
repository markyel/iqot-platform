<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Спека §3.7. Расширение balance_holds для попозиционных holds.
 *
 * Добавляет:
 *  - request_item_id — FK на reports.request_items (cross-DB, логическая ссылка, без FK).
 *    Для API-holds заполняется после PromoteSubmissionJob.
 *    Для старых web-holds остаётся NULL.
 *  - api_submission_id — FK на iqot.api_submissions. Заполняется для API-holds.
 *  - request_items_staging_id — FK на iqot.request_items_staging. Заполняется для API-holds до промоушена.
 *
 * Старые web-holds продолжают работать через request_id (не меняется).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_holds', function (Blueprint $table) {
            // reports.request_items.id — cross-DB, логическая ссылка без FK.
            // Тип int unsigned — совместимость с reports.request_items.id (int(10) unsigned).
            $table->unsignedInteger('request_item_id')->nullable()->after('request_id');
            $table->foreignId('api_submission_id')->nullable()->after('user_id')
                ->constrained('api_submissions')->onDelete('set null');
            $table->foreignId('request_items_staging_id')->nullable()->after('api_submission_id')
                ->constrained('request_items_staging')->onDelete('set null');

            $table->index('api_submission_id', 'idx_api_submission');
            $table->index('request_items_staging_id', 'idx_staging_item');
            $table->index('request_item_id', 'idx_request_item');
        });
    }

    public function down(): void
    {
        Schema::table('balance_holds', function (Blueprint $table) {
            $table->dropForeign(['api_submission_id']);
            $table->dropForeign(['request_items_staging_id']);
            $table->dropIndex('idx_api_submission');
            $table->dropIndex('idx_staging_item');
            $table->dropIndex('idx_request_item');
            $table->dropColumn(['api_submission_id', 'request_items_staging_id', 'request_item_id']);
        });
    }
};
