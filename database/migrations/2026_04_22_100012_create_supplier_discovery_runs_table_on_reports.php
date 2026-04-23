<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Спека §3.8. Таблица supplier_discovery_runs — создаётся на connection=reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reports = Schema::connection('reports');

        $reports->create('supplier_discovery_runs', function (Blueprint $table) {
            // PK unsigned bigint (новая таблица) — не обязан совпадать с int(11) у product_types;
            // FK ставится на domain_id/product_type_id как int signed.
            $table->id();
            // Типы int signed — совместимость с reports.application_domains.id / product_types.id (int(11)).
            $table->integer('domain_id')->nullable();
            $table->integer('product_type_id');
            $table->enum('status', [
                'queued', 'running',
                'success_covered', 'success_partial',
                'exhausted', 'failed',
            ])->default('queued');
            $table->unsignedTinyInteger('iterations_used')->default(0);
            $table->unsignedSmallInteger('suppliers_found')->default(0);
            $table->enum('trigger_source', ['api_submission', 'manual', 'scheduled']);
            // triggering_submission_external_id — логическая ссылка на iqot.api_submissions.external_id.
            $table->char('triggering_submission_external_id', 26)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['domain_id', 'product_type_id', 'status', 'finished_at'], 'idx_pair_status');
        });

        // FK добавляем условно — reports может быть управляем извне, и схема
        // product_types / application_domains могла прийти с другими типами столбцов.
        if ($reports->hasTable('product_types')) {
            $reports->table('supplier_discovery_runs', function (Blueprint $table) {
                $table->foreign('product_type_id', 'fk_sdr_product_type')
                    ->references('id')->on('product_types');
            });
        }
        if ($reports->hasTable('application_domains')) {
            $reports->table('supplier_discovery_runs', function (Blueprint $table) {
                $table->foreign('domain_id', 'fk_sdr_domain')
                    ->references('id')->on('application_domains');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('reports')->dropIfExists('supplier_discovery_runs');
    }
};
