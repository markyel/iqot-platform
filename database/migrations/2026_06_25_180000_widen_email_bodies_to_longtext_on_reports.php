<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Расширение тел писем body_text/body_html до LONGTEXT на таблицах входящих
 * (connection=reports).
 *
 * Зачем: при приёме на стороне Laravel (App\Services\Senders\IncomingEmailRouter)
 * часть писем (например, рассылки dkc.ru / bitrix24) имеет HTML-тело больше 64 КБ —
 * лимита типа TEXT. Вставка падала на `SQLSTATE[22001] 1406 Data too long for
 * column 'body_html'`, письмо терялось (route failed, error++). LONGTEXT (до 4 ГБ)
 * убирает потолок без потери данных и чинит обе ветки вставки — email_messages
 * (беседы) и unidentified_emails (неопознанные).
 *
 * Таблицы создавались внешне (n8n) — поэтому ALTER через DB::statement с проверкой
 * hasTable/hasColumn (идемпотентность). Колонки nullable (роутер пишет null при
 * пустом теле) — сохраняем nullability.
 */
return new class extends Migration
{
    private const TABLES = ['email_messages', 'unidentified_emails'];
    private const COLUMNS = ['body_text', 'body_html'];

    public function up(): void
    {
        $this->modify('LONGTEXT');
    }

    public function down(): void
    {
        $this->modify('TEXT');
    }

    private function modify(string $type): void
    {
        $schema = Schema::connection('reports');

        foreach (self::TABLES as $table) {
            if (!$schema->hasTable($table)) {
                continue;
            }

            foreach (self::COLUMNS as $column) {
                if ($schema->hasColumn($table, $column)) {
                    DB::connection('reports')->statement(
                        "ALTER TABLE `{$table}` MODIFY `{$column}` {$type} NULL"
                    );
                }
            }
        }
    }
};
