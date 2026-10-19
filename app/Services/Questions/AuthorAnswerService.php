<?php

namespace App\Services\Questions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Вопросы поставщиков, переданные автору заявки (forwarded_to_author): список, ответ автора, пропуск,
 * сгруппированные по консолидации. Заменяет n8n-воркфлоу «questions-management» (n8n выключен 10.2026;
 * ответы авторов не уходили с 29.06.2026).
 *
 * Ответ автора = как автоответ (ReplyEmailBuilder → outgoing_replies pending → emails:dispatch-replies),
 * плюс supplier_questions.status='author_answered' + author_answer/answered_at/answered_by, author_questions →
 * answered, беседа без «ждёт вопроса». Похожие вопросы той же позиции потом отвечает QuestionConsolidator
 * (берёт author_answer группы).
 */
class AuthorAnswerService
{
    private const CONN = 'reports';
    public const OPEN = ['forwarded_to_author', 'pending'];

    /**
     * @param array<string,mixed> $filters status (string|array), request_id, request_number, supplier_id, priority, question_type, search
     * @param array{field?:string, direction?:string} $sort
     * @param array{page?:int, per_page?:int} $pagination
     */
    public function listQuestions(array $filters = [], array $sort = [], array $pagination = []): array
    {
        $page = max(1, (int) ($pagination['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($pagination['per_page'] ?? 20)));
        $q = $this->baseQuery();
        $status = $filters['status'] ?? null;
        if (is_array($status) && $status !== []) {
            $q->whereIn('sq.status', $status);
        } elseif (is_string($status) && $status !== '' && $status !== 'all') {
            $q->where('sq.status', $status);
        } else {
            $q->where('sq.status', '!=', '');
        }
        if (!empty($filters['request_id'])) {
            $q->where('rq.id', (int) $filters['request_id']);
        }
        if (!empty($filters['request_number'])) {
            $q->where('rq.request_number', 'like', '%' . $filters['request_number'] . '%');
        }
        foreach (['supplier_id', 'priority', 'question_type'] as $f) {
            if (!empty($filters[$f])) {
                $q->where('sq.' . $f, $filters[$f]);
            }
        }
        if (!empty($filters['search'])) {
            $s = '%' . $filters['search'] . '%';
            $q->where(fn ($w) => $w->where('sq.question_text', 'like', $s)->orWhere('s.name', 'like', $s)->orWhere('ri.name', 'like', $s));
        }
        $total = (clone $q)->count();
        $field = in_array($sort['field'] ?? '', ['created_at', 'answered_at', 'priority', 'status'], true) ? $sort['field'] : 'created_at';
        $q->orderBy('sq.' . $field, strtolower((string) ($sort['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc')->orderByDesc('sq.id');
        $rows = $q->forPage($page, $perPage)->get()->map(fn ($r) => $this->present($r))->all();

        return [
            'success' => true,
            'questions' => $rows,
            'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, (int) ceil($total / $perPage))],
        ];
    }

    /** id боевой заявки вопроса (для проверки владельца в кабинете). */
    public function questionRequestId(int $questionId): ?int
    {
        $id = $this->baseQuery()->where('sq.id', $questionId)->value('rq.id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param array<int,array{file_name:string,mime_type:?string,file_type:string,file_size:int,content_base64:string}> $files
     */
    public function answerQuestion(int $questionId, string $answer, array $files = [], ?int $userId = null): array
    {
        $loader = new QuestionContextLoader();
        $question = $loader->loadQuestion($questionId, self::OPEN);
        if ($question === null) {
            return ['success' => false, 'message' => 'Вопрос не найден или уже отвечен'];
        }
        try {
            $reply = $this->buildReply($loader, $question, $answer);
            if ($reply['to_email'] === '' || $reply['from_email'] === '') {
                return ['success' => false, 'message' => 'Не найдено письмо поставщика или ящик-отправитель беседы'];
            }
            DB::connection(self::CONN)->transaction(function () use ($reply, $question, $answer, $files, $userId) {
                $db = DB::connection(self::CONN);
                $replyId = (int) $db->table('outgoing_replies')->insertGetId([
                    'conversation_id' => $reply['conversation_id'],
                    'supplier_question_id' => $reply['supplier_question_id'],
                    'sender_id' => $reply['sender_id'],
                    'supplier_id' => $reply['supplier_id'],
                    'from_email' => $reply['from_email'],
                    'to_email' => $reply['to_email'],
                    'subject' => mb_substr($reply['subject'], 0, 500),
                    'body_text' => $reply['body_text'],
                    'body_html' => $reply['body_html'],
                    'in_reply_to' => $reply['in_reply_to'],
                    'references_header' => $reply['references_header'],
                    'status' => 'pending',
                    'created_at' => now(),
                ]);
                foreach ($files as $f) {
                    $data = base64_decode((string) $f['content_base64'], true);
                    if ($data === false) {
                        continue;
                    }
                    $db->table('outgoing_reply_attachments')->insert([
                        'outgoing_reply_id' => $replyId,
                        'file_id' => 'upload_' . Str::uuid(),
                        'file_name' => mb_substr((string) $f['file_name'], 0, 255),
                        'mime_type' => mb_substr((string) ($f['mime_type'] ?? 'application/octet-stream'), 0, 100),
                        'file_size' => strlen($data),
                        'file_type' => in_array($f['file_type'] ?? '', ['photo', 'document', 'video', 'audio'], true) ? $f['file_type'] : 'document',
                        'file_data' => $data,
                        'created_at' => now(),
                    ]);
                }
                $db->table('supplier_questions')->where('id', $question->question_id)->update([
                    'status' => 'author_answered',
                    'author_answer' => $answer,
                    'answered_at' => now(),
                    'answered_by' => $userId,
                ]);
                $db->table('author_questions')->where('supplier_question_id', $question->question_id)->update(['status' => 'answered']);
                $this->releaseConversation((int) $question->conversation_id);
            });
        } catch (\Throwable $e) {
            Log::error('AuthorAnswerService: answer failed', ['question_id' => $questionId, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Ошибка при сохранении ответа: ' . $e->getMessage()];
        }

        return ['success' => true, 'files_attached' => count($files)];
    }

    public function skipQuestion(int $questionId, ?string $reason = null): array
    {
        $db = DB::connection(self::CONN);
        $q = $db->table('supplier_questions')->where('id', $questionId)->whereIn('status', self::OPEN)->first(['id', 'conversation_id']);
        if (!$q) {
            return ['success' => false, 'message' => 'Вопрос не найден или уже обработан'];
        }
        $db->transaction(function () use ($db, $q, $reason) {
            $db->table('supplier_questions')->where('id', $q->id)->update(['status' => 'skipped', 'answer_text' => $reason, 'answered_at' => now()]);
            $db->table('author_questions')->where('supplier_question_id', $q->id)->update(['status' => 'answered']);
            $this->releaseConversation((int) $q->conversation_id);
        });

        return ['success' => true];
    }

    /**
     * Открытые вопросы автору, сгруппированные по консолидации (похожие вопросы одной позиции).
     * Вопрос без группы — своя «группа» с consolidation_id = −id вопроса (answerConsolidated понимает).
     */
    public function getConsolidatedQuestions(?int $requestId = null): array
    {
        $q = $this->baseQuery()->where('sq.status', 'forwarded_to_author');
        if ($requestId) {
            $q->where('rq.id', $requestId);
        }
        $rows = $q->orderBy('sq.created_at')->get();
        $cons = DB::connection(self::CONN)->table('question_consolidation')
            ->whereIn('id', $rows->pluck('consolidation_id')->filter()->unique()->all())->pluck('consolidated_text', 'id');
        $groups = [];
        foreach ($rows as $r) {
            $key = $r->consolidation_id ? (int) $r->consolidation_id : -(int) $r->id;
            $g = $groups[$key] ?? [
                'consolidation_id' => $key,
                'consolidated_text' => $r->consolidation_id ? ($cons[$r->consolidation_id] ?? $r->question_text) : $r->question_text,
                'original_question_ids' => [],
                'supplier_names' => [],
                'item_name' => $r->item_name,
                'item_article' => $r->item_article,
                'request_id' => $r->request_id,
                'request_number' => $r->request_number,
                'first_question_at' => $r->created_at,
                'last_question_at' => $r->created_at,
            ];
            $g['original_question_ids'][] = (int) $r->id;
            if ($r->supplier_name && !in_array($r->supplier_name, $g['supplier_names'], true)) {
                $g['supplier_names'][] = $r->supplier_name;
            }
            $g['last_question_at'] = $r->created_at;
            $groups[$key] = $g;
        }
        $out = [];
        foreach (array_values($groups) as $i => $g) {
            $g['index'] = $i + 1;
            $g['questions_count'] = count($g['original_question_ids']);
            $g['suppliers_count'] = count($g['supplier_names']);
            $out[] = $g;
        }
        usort($out, fn ($a, $b) => strcmp((string) $b['last_question_at'], (string) $a['last_question_at']));

        return ['success' => true, 'consolidated_questions' => $out, 'total' => count($out)];
    }

    /** Один ответ всем открытым вопросам группы (каждому поставщику — своё письмо). */
    public function answerConsolidated(int $consolidationId, string $answer, array $files = [], ?int $userId = null): array
    {
        $ids = $consolidationId < 0
            ? [-$consolidationId]
            : DB::connection(self::CONN)->table('supplier_questions')->where('consolidation_id', $consolidationId)
                ->whereIn('status', self::OPEN)->pluck('id')->map(fn ($v) => (int) $v)->all();
        if ($ids === []) {
            return ['success' => false, 'message' => 'Открытых вопросов в группе нет'];
        }
        $ok = 0;
        $errors = [];
        foreach ($ids as $id) {
            $r = $this->answerQuestion($id, $answer, $files, $userId);
            $r['success'] ? $ok++ : $errors[] = "#{$id}: " . ($r['message'] ?? '');
        }
        if ($ok === 0) {
            return ['success' => false, 'message' => implode('; ', $errors)];
        }

        return ['success' => true, 'answered_count' => $ok, 'files_attached' => count($files), 'errors' => $errors];
    }

    private function buildReply(QuestionContextLoader $loader, object $question, string $answer): array
    {
        $batch = $loader->loadBatch((int) ($question->batch_id ?: $question->conversation_batch_id));
        $sender = $batch !== null ? $loader->loadSender((int) $batch->sender_id) : null;
        $templateId = $sender !== null ? (int) ($sender->preferred_template_id ?: $sender->template_id ?: 0) : 0;
        $context = [
            'question_id' => (int) $question->question_id,
            'conversation_id' => (int) $question->conversation_id,
            'sender_id' => $batch !== null ? (int) $batch->sender_id : 0,
            'supplier_id' => (int) $question->supplier_id,
            'sender_email' => $sender->email ?? null,
            'sender_full_name' => $sender->sender_full_name ?? null,
            'sender_phone' => $sender->phone ?? null,
            'sender_position' => null,
            'sender_greeting' => $sender->email_greeting ?? 'Здравствуйте',
            'organization_name' => $sender->organization_name ?? null,
            'tracking_token' => $batch->tracking_token ?? null,
            'answer_text' => $answer,
        ];

        return (new ReplyEmailBuilder())->build(
            $context,
            $loader->loadOriginalMessage((int) $question->conversation_id),
            $loader->loadTemplate($templateId > 0 ? $templateId : null)
        );
    }

    /** Беседа больше не ждёт ответа автора (если других открытых вопросов в ней нет). */
    private function releaseConversation(int $conversationId): void
    {
        $db = DB::connection(self::CONN);
        if ($db->table('supplier_questions')->where('conversation_id', $conversationId)->where('status', 'forwarded_to_author')->exists()) {
            return;
        }
        $db->table('email_conversations')->where('id', $conversationId)->update(['has_pending_question' => 0]);
        $db->table('email_conversations')->where('id', $conversationId)->where('status', 'needs_clarification')->update(['status' => 'waiting']);
    }

    private function baseQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(self::CONN)->table('supplier_questions as sq')
            ->leftJoin('suppliers as s', 's.id', '=', 'sq.supplier_id')
            ->leftJoin('email_batches as b', 'b.id', '=', 'sq.batch_id')
            ->leftJoin('request_items as ri', 'ri.id', '=', DB::raw("COALESCE(sq.request_item_id, CAST(JSON_UNQUOTE(JSON_EXTRACT(b.request_items, '$[0]')) AS UNSIGNED))"))
            ->leftJoin('requests as rq', 'rq.id', '=', 'ri.request_id')
            ->select([
                'sq.id', 'sq.question_text', 'sq.question_type', 'sq.priority', 'sq.status', 'sq.created_at', 'sq.answered_at',
                'sq.author_answer', 'sq.answer_text', 'sq.consolidation_id', 'sq.supplier_id', 'sq.conversation_id',
                's.name as supplier_name', 's.email as supplier_email',
                'ri.id as request_item_id', 'ri.name as item_name', 'ri.article as item_article', 'ri.position_number',
                'rq.id as request_id', 'rq.request_number',
            ]);
    }

    private function present(object $r): array
    {
        $a = (array) $r;
        $a['request'] = ['id' => $r->request_id, 'number' => $r->request_number];
        $a['request_item'] = ['id' => $r->request_item_id, 'name' => $r->item_name, 'article' => $r->item_article];

        return $a;
    }
}
