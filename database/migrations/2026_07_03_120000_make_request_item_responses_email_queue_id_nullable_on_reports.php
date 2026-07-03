<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Делаем request_item_responses.email_queue_id NULLABLE (connection=reports).
 *
 * Зачем: AI-анализ ответов (App\Services\Analysis\SupplierReplyPersister) падал
 * 100–144 раз/сут на `SQLSTATE[23000] 1048 Column 'email_queue_id' cannot be null`.
 *
 * Корень — легитимное состояние данных, а не баг SQL. При приёме
 * (App\Services\Senders\IncomingEmailRouter) письмо матчится на БАТЧ по
 * tracking_token, но собственный email_queue.token поставщика в ответе НЕ найден
 * (matchQueue → null). Тогда поставщик определяется по адресу отправителя
 * (findSupplierByEmail) и оказывается НЕ среди получателей этого батча — для пары
 * (batch_id, supplier_id) строки email_queue не существует (роутер возвращает
 * 'conversation', а не 'replied', queueId=null — это штатная ветка). Позже
 * resolveEmailQueueId(batch, supplier) → null, и INSERT оффера в
 * request_item_responses упирался в NOT NULL.
 *
 * Семантически NULL здесь корректен: у ответа, опознанного по адресу отправителя,
 * нет исходного письма очереди. Оффер всё равно ценен для отчётов, поэтому
 * сохраняем строку с email_queue_id=NULL, а не теряем данные.
 *
 * FK request_item_responses_ibfk_3 (email_queue ON DELETE CASCADE) и индекс
 * idx_email_queue допускают NULL — MODIFY их не трогает. Upsert офферов уже
 * сохраняет реальный email_queue_id на дубле (его нет в ON DUPLICATE KEY UPDATE),
 * поэтому кросс-батчевые дубли не затираются null.
 *
 * Таблица создавалась внешне (n8n) — ALTER через DB::statement с проверкой
 * hasTable/hasColumn (идемпотентность), как в остальных reports-миграциях.
 */
return new class extends Migration
{
    private const TABLE = 'request_item_responses';
    private const COLUMN = 'email_queue_id';
    private const COMMENT = 'ID письма из очереди';

    public function up(): void
    {
        $this->modify('NULL');
    }

    public function down(): void
    {
        // Откат к NOT NULL упадёт при наличии NULL-строк — это ожидаемо
        // (сначала вычистить/забэкфилить их вручную).
        $this->modify('NOT NULL');
    }

    private function modify(string $nullability): void
    {
        $schema = Schema::connection('reports');

        if (!$schema->hasTable(self::TABLE) || !$schema->hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        DB::connection('reports')->statement(sprintf(
            "ALTER TABLE `%s` MODIFY `%s` INT UNSIGNED %s COMMENT '%s'",
            self::TABLE,
            self::COLUMN,
            $nullability,
            self::COMMENT,
        ));
    }
};
