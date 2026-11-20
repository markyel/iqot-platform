<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Функция calculate_request_completion — её вызывает триггер after_response_update (request_item_responses):
 * при 100% полученных ответов заявка → responses_received. Дамп 28.01.2026 (phpMyAdmin) хранимых функций не
 * содержал — без неё любая запись цены падала (1305 FUNCTION does not exist, 20.11.2026). Тело — дословно с прода
 * (information_schema.ROUTINES, 14.10.2026). Процедуры кармы (collect_response_signals и др.) не восстанавливаем:
 * их вызывал только n8n «Status Manager», выключенный с 19.10.2026, а тела недоступны.
 * Создание функции при включённом бинлоге — log_bin_trust_function_creators=1 (deploy/server/provision-prod.sh).
 */
return new class extends Migration
{
    private string $conn = 'reports';

    public function up(): void
    {
        $db = DB::connection($this->conn);
        $exists = $db->table('information_schema.routines')->where('routine_schema', $db->getDatabaseName())
            ->where('routine_name', 'calculate_request_completion')->exists();
        if ($exists) {
            return;
        }
        $db->unprepared(<<<'SQL'
CREATE FUNCTION calculate_request_completion(req_id INT) RETURNS DECIMAL(5,2)
READS SQL DATA
BEGIN
    DECLARE total_expected INT;
    DECLARE total_received INT;
    DECLARE completion DECIMAL(5,2);

    -- Подсчёт ожидаемых ответов
    SELECT COUNT(*) INTO total_expected
    FROM request_item_responses rir
    JOIN request_items ri ON rir.request_item_id = ri.id
    WHERE ri.request_id = req_id;

    -- Подсчёт полученных ответов
    SELECT COUNT(*) INTO total_received
    FROM request_item_responses rir
    JOIN request_items ri ON rir.request_item_id = ri.id
    WHERE ri.request_id = req_id
      AND rir.status = 'received';

    -- Процент завершённости
    IF total_expected = 0 THEN
        SET completion = 0;
    ELSE
        SET completion = (total_received * 100.0) / total_expected;
    END IF;

    RETURN completion;
END
SQL);
    }

    public function down(): void
    {
        DB::connection($this->conn)->unprepared('DROP FUNCTION IF EXISTS calculate_request_completion');
    }
};
