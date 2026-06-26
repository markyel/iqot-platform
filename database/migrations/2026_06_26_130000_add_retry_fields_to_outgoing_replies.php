<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Поля ретрая для reports.outgoing_replies (connection=reports).
 *
 * Отправители шлют через smtp.beget.com — round-robin DNS на 6 IP, из которых
 * часть периодически не отвечает. Без ретрая ~треть ответов падала в 'failed' по
 * таймауту коннекта (транзиентная ошибка), хотя на повторной попытке (другой IP)
 * ушла бы. Зеркалим механизм массовой рассылки (email_queue.retry_count): на
 * транзиентной ошибке копим retry_count и возвращаем ответ в 'pending' (диспетчер
 * перезаберёт на след. тике), пока retry_count < max_retries; дальше — 'failed'.
 *
 * error_message — последняя ошибка отправки (как email_queue.error_message), для
 * диагностики и отличия 550-«ящик отключён» (терминал + деактивация отправителя)
 * от таймаута коннекта (ретрай).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('reports')->hasTable('outgoing_replies')) {
            return;
        }

        Schema::connection('reports')->table('outgoing_replies', function ($table) {
            if (!Schema::connection('reports')->hasColumn('outgoing_replies', 'retry_count')) {
                $table->unsignedInteger('retry_count')->default(0)->after('status');
            }
            if (!Schema::connection('reports')->hasColumn('outgoing_replies', 'error_message')) {
                $table->string('error_message', 500)->nullable()->after('retry_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('reports')->hasTable('outgoing_replies')) {
            return;
        }

        Schema::connection('reports')->table('outgoing_replies', function ($table) {
            if (Schema::connection('reports')->hasColumn('outgoing_replies', 'error_message')) {
                $table->dropColumn('error_message');
            }
            if (Schema::connection('reports')->hasColumn('outgoing_replies', 'retry_count')) {
                $table->dropColumn('retry_count');
            }
        });
    }
};
