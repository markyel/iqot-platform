<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляем 'sending' в ENUM outgoing_replies.status (connection=reports).
 *
 * Диспетчер отправки ответов (emails:dispatch-replies, замена n8n «Send Outgoing
 * Replies») использует status='sending' как «claim» — взятый в работу ответ не
 * подхватывается повторным тиком. В исходном ENUM('pending','sent','failed')
 * такого значения нет → n8n-узел «Update Status to Sending» молча писал '' (MySQL
 * усекал недопустимый enum до пустой строки). После добавления значения claim
 * корректный, реклейм застрявших 'sending' работает.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('reports')->hasTable('outgoing_replies')) {
            return;
        }

        DB::connection('reports')->statement(
            "ALTER TABLE outgoing_replies MODIFY status "
            . "ENUM('pending','sending','sent','failed') "
            . "NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (!Schema::connection('reports')->hasTable('outgoing_replies')) {
            return;
        }

        // Перед сужением ENUM возвращаем застрявшие 'sending' в 'pending', иначе
        // строки станут '' (недопустимое значение усечётся).
        DB::connection('reports')->table('outgoing_replies')
            ->where('status', 'sending')
            ->update(['status' => 'pending']);

        DB::connection('reports')->statement(
            "ALTER TABLE outgoing_replies MODIFY status "
            . "ENUM('pending','sent','failed') "
            . "NULL DEFAULT 'pending'"
        );
    }
};
