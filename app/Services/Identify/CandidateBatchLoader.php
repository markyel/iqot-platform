<?php

namespace App\Services\Identify;

use Illuminate\Support\Facades\DB;

/**
 * Сбор кандидат-заявок для неопознанного письма — порт n8n-узла «Find Batches by
 * Domain».
 *
 * Кандидаты — письма, отправленные с того же ящика (email_queue.from_email =
 * unidentified_emails.to_email) за окно lookback_days по активным статусам, чей
 * поставщик имеет ТОТ ЖЕ домен, что и отправитель ответа, ЛИБО совпавший токен
 * (queue_id/batch_id из MailboxTokenMatcher). Возвращаем с подгруженными позициями
 * заявки (request_items по JSON-массиву email_batches.request_items) и метаданными
 * для AI-промпта. Совпавший по токену кандидат идёт первым (ORDER BY CASE).
 */
class CandidateBatchLoader
{
    private const ACTIVE_STATUSES = "'sent','opened','replied','in_conversation'";

    public function __construct(
        private readonly int $lookbackDays = 60,
        private readonly int $limit = 50,
    ) {
    }

    /**
     * @param array{queue_id:int,batch_id:?int}|null $tokenMatch результат MailboxTokenMatcher
     * @return array<int,object> строки кандидатов (поля: queue_id, batch_id, supplier_id,
     *         sent_to_email, sent_from_email, sent_at, tracking_token, supplier_name,
     *         supplier_email, days_since_sent, request_items_data(JSON|null))
     */
    public function load(string $mailbox, string $fromEmail, ?array $tokenMatch): array
    {
        $fromDomain = $this->domain($fromEmail);
        $matchedQueueId = $tokenMatch['queue_id'] ?? 0;
        $matchedBatchId = $tokenMatch['batch_id'] ?? 0;

        $statuses = self::ACTIVE_STATUSES;

        $sql = <<<SQL
            SELECT
              eq.id AS queue_id,
              eq.batch_id,
              eq.supplier_id,
              eq.to_email AS sent_to_email,
              eq.from_email AS sent_from_email,
              eq.sent_at,
              eb.tracking_token,
              s.name AS supplier_name,
              s.email AS supplier_email,
              DATEDIFF(NOW(), eq.sent_at) AS days_since_sent,
              (
                SELECT JSON_ARRAYAGG(
                  JSON_OBJECT('id', ri.id, 'name', ri.name, 'brand', ri.brand, 'article', ri.article, 'quantity', ri.quantity)
                )
                FROM request_items ri
                WHERE JSON_CONTAINS(eb.request_items, CAST(ri.id AS JSON))
              ) AS request_items_data
            FROM email_queue eq
            JOIN email_batches eb ON eb.id = eq.batch_id
            JOIN suppliers s ON s.id = eq.supplier_id
            WHERE eq.status IN ({$statuses})
              AND eq.sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND eq.from_email = ?
              AND (
                SUBSTRING_INDEX(s.email, '@', -1) = ?
                OR eq.id = ?
                OR eq.batch_id = ?
              )
            ORDER BY
              CASE WHEN eq.id = ? THEN 0 ELSE 1 END,
              eq.sent_at DESC
            LIMIT {$this->limit}
            SQL;

        return DB::connection('reports')->select($sql, [
            $this->lookbackDays,
            $mailbox,
            $fromDomain,
            $matchedQueueId,
            $matchedBatchId,
            $matchedQueueId,
        ]);
    }

    private function domain(string $email): string
    {
        $at = strrchr($email, '@');

        return $at !== false ? strtolower(substr($at, 1)) : '';
    }
}
