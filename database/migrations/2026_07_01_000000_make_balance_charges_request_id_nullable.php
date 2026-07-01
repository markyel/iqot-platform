<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Делает balance_charges.request_id nullable.
 *
 * API-flow заморозки (BalanceHold с api_submission_id) не имеют внутренней
 * iqot.requests-строки — заявка живёт только в reports (ExternalRequest), а
 * api_submissions.internal_request_id указывает на reports-id. BalanceHold у них
 * с request_id=NULL. Однако BalanceHold::chargeForItem() при списании вставляет
 * 'request_id' => $this->request_id (NULL) в balance_charges, где колонка была
 * foreignId()->constrained() = NOT NULL (миграция 2026_01_16_000001). Любое
 * списание за API-позицию падало на integrity constraint → API-биллинг был
 * полностью заблокирован (0 списаний при сотнях выполненных позиций).
 *
 * FK на requests сохраняется — nullable-FK допускает NULL, ссылочная целостность
 * для web-flow (request_id задан) не нарушается.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE balance_charges MODIFY request_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE balance_charges MODIFY request_id BIGINT UNSIGNED NOT NULL');
    }
};
