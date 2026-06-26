<?php

namespace App\Services\Questions;

use Illuminate\Support\Facades\DB;

/**
 * Сбор контекста для триажа одного вопроса поставщика — порт MySQL-узлов n8n
 * «Process Supplier Questions» (Get Batch Data / Get Request Items / Get Sender
 * Data / Get Author Answers). Всё через query builder на коннекте `reports`.
 */
class QuestionContextLoader
{
    private const CONN = 'reports';

    public function __construct(private readonly int $historyLimit = 15)
    {
    }

    /**
     * Порт «Get Pending Questions» (точечно по id) + JOIN беседы/поставщика.
     * Возвращает null, если вопрос уже не в статусе pending (claim-проверка).
     */
    public function loadQuestion(int $questionId): ?object
    {
        return DB::connection(self::CONN)->table('supplier_questions as sq')
            ->join('email_conversations as ec', 'sq.conversation_id', '=', 'ec.id')
            ->join('suppliers as s', 'sq.supplier_id', '=', 's.id')
            ->where('sq.id', $questionId)
            ->where('sq.status', 'pending')
            ->first([
                'sq.id as question_id',
                'sq.question_text',
                'sq.question_type',
                'sq.supplier_id',
                'sq.conversation_id',
                'sq.batch_id',
                'sq.request_item_id',
                'ec.batch_id as conversation_batch_id',
                's.name as supplier_name',
                's.email as supplier_email',
            ]);
    }

    /**
     * Порт «Get Batch Data».
     */
    public function loadBatch(int $batchId): ?object
    {
        return DB::connection(self::CONN)->table('email_batches')
            ->where('id', $batchId)
            ->first(['id as batch_id', 'sender_id', 'request_items', 'tracking_token']);
    }

    /**
     * Порт «Get Request Items»: request_items батча — JSON-массив id; тянем детали
     * позиций в порядке position_number.
     *
     * @return array<int,object>
     */
    public function loadRequestItems(int $batchId): array
    {
        $batch = DB::connection(self::CONN)->table('email_batches')
            ->where('id', $batchId)
            ->value('request_items');

        $ids = $this->decodeItemIds(is_string($batch) ? $batch : null);
        if ($ids === []) {
            return [];
        }

        return DB::connection(self::CONN)->table('request_items')
            ->whereIn('id', $ids)
            ->orderBy('position_number')
            ->get(['id', 'request_id', 'position_number', 'name', 'brand', 'article', 'quantity', 'unit', 'category', 'description'])
            ->all();
    }

    /**
     * Порт «Get Sender Data»: отправитель + реквизиты организации.
     */
    public function loadSender(int $senderId): ?object
    {
        return DB::connection(self::CONN)->table('senders as s')
            ->leftJoin('client_organizations as co', 's.client_organization_id', '=', 'co.id')
            ->where('s.id', $senderId)
            ->first([
                's.id',
                's.sender_name',
                's.sender_full_name',
                's.email',
                's.phone',
                's.email_greeting',
                's.email_style',
                's.preferred_template_id',
                's.template_id',
                'co.name as organization_name',
                'co.inn as organization_inn',
                'co.kpp as organization_kpp',
                'co.legal_address as organization_legal_address',
                'co.actual_address as organization_actual_address',
                'co.phone as organization_phone',
                'co.email as organization_email',
                'co.director_name as organization_director_name',
            ]);
    }

    /**
     * Порт «Get Author Answers»: до historyLimit прошлых ответов автора по этой
     * заявке (status='author_answered'), с дедупликацией по тексту ответа и поиском
     * original_reply_id с вложениями (COALESCE: reply с файлами → любой reply) и
     * счётчиком файлов. Используется как образец для похожих вопросов + копирование
     * вложений.
     *
     * @return array<int,object>
     */
    public function loadAuthorAnswers(int $batchId): array
    {
        $sql = <<<SQL
SELECT
  t.original_question_id,
  t.question_text,
  t.question_type,
  t.author_answer,
  t.answered_at,
  t.item_name,
  t.item_article,
  COALESCE(
    (SELECT orep.id
     FROM outgoing_replies orep
     JOIN outgoing_reply_attachments ora ON ora.outgoing_reply_id = orep.id
     JOIN supplier_questions sq2 ON orep.supplier_question_id = sq2.id
     WHERE sq2.author_answer = t.author_answer
       AND sq2.answered_at = t.answered_at
       AND sq2.batch_id = t.batch_id
     LIMIT 1),
    (SELECT orep.id
     FROM outgoing_replies orep
     WHERE orep.supplier_question_id = t.original_question_id
     LIMIT 1)
  ) as original_reply_id,
  (SELECT COUNT(*)
   FROM outgoing_reply_attachments ora
   JOIN outgoing_replies orep ON ora.outgoing_reply_id = orep.id
   JOIN supplier_questions sq2 ON orep.supplier_question_id = sq2.id
   WHERE sq2.author_answer = t.author_answer
     AND sq2.answered_at = t.answered_at
     AND sq2.batch_id = t.batch_id
   LIMIT 1
  ) as files_count
FROM (
  SELECT
    MIN(sq.id) as original_question_id,
    MIN(sq.question_text) as question_text,
    sq.question_type,
    sq.author_answer,
    sq.answered_at,
    sq.batch_id,
    ri.name as item_name,
    ri.article as item_article
  FROM supplier_questions sq
  LEFT JOIN request_items ri ON sq.request_item_id = ri.id
  WHERE sq.batch_id = ?
    AND sq.status = 'author_answered'
    AND sq.author_answer IS NOT NULL
    AND sq.author_answer != ''
    AND sq.question_text IS NOT NULL
    AND sq.question_text != ''
  GROUP BY sq.author_answer, sq.answered_at, sq.question_type, sq.batch_id, ri.name, ri.article
) t
ORDER BY t.answered_at DESC
LIMIT {$this->historyLimit};
SQL;

        return DB::connection(self::CONN)->select($sql, [$batchId]);
    }

    /**
     * Порт «Get Author User ID»: автор заявки (requests.user_id) по батчу.
     */
    public function loadAuthorUserId(int $batchId): ?int
    {
        $row = DB::connection(self::CONN)->table('email_batches as eb')
            ->join('request_items as ri', function ($join): void {
                $join->whereRaw('JSON_CONTAINS(eb.request_items, CAST(ri.id AS JSON))');
            })
            ->join('requests as r', 'ri.request_id', '=', 'r.id')
            ->where('eb.id', $batchId)
            ->limit(1)
            ->value('r.user_id');

        return $row !== null ? (int) $row : null;
    }

    /**
     * Порт «Get Original Email Message»: последнее входящее письмо беседы — для
     * заголовков треда (in_reply_to / references) и цитирования.
     */
    public function loadOriginalMessage(int $conversationId): ?object
    {
        return DB::connection(self::CONN)->table('email_messages')
            ->where('conversation_id', $conversationId)
            ->where('direction', 'incoming')
            ->orderByDesc('received_at')
            ->first(['message_id', 'subject', 'references_header', 'body_text', 'body_html', 'from_email', 'to_email', 'received_at']);
    }

    /**
     * Порт «Get Email Template».
     */
    public function loadTemplate(?int $templateId): ?object
    {
        if ($templateId === null || $templateId <= 0) {
            return null;
        }

        return DB::connection(self::CONN)->table('email_templates')
            ->where('id', $templateId)
            ->first();
    }

    /**
     * @return array<int,int>
     */
    private function decodeItemIds(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $decoded), static fn ($v) => $v > 0));
    }
}
