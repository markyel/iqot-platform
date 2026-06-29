<?php

namespace App\Jobs;

use App\Services\Api\OpenAIClassifierClient;
use App\Services\Questions\QuestionAutoAnswerClassifier;
use App\Services\Questions\QuestionConsolidator;
use App\Services\Questions\QuestionContextLoader;
use App\Services\Questions\ReplyEmailBuilder;
use App\Services\Questions\SupplierQuestionPersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Триаж одного вопроса поставщика — порт цикла n8n «Process Supplier Questions»
 * (Get Pending Questions → Get*Context → AI Agent → Parse → ветки Auto/Author).
 *
 * Поток:
 *  1. claim (Cache::lock + повторная проверка status='pending');
 *  2. сбор контекста (QuestionContextLoader);
 *  3. AI #1 (QuestionAutoAnswerClassifier) — можно ли ответить автоматически;
 *  4а. ДА → ReplyEmailBuilder → SupplierQuestionPersister::persistAuto;
 *  4б. НЕТ → QuestionConsolidator (AI #2, дедуп по позиции). Если группа уже имеет
 *      ответ автора → апгрейд до авто-ответа (persistAuto). Иначе → persistAuthor.
 *
 * Идемпотентность: вставки outgoing_replies/author_questions/question_consolidation
 * НЕ идемпотентны на уровне БД, поэтому двойную обработку отсекаем Cache::lock +
 * повторной проверкой status='pending'; вставки дедуплицируются в персистере.
 */
class ProcessSupplierQuestionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(private readonly int $questionId)
    {
        $this->onQueue('questions');
    }

    public function handle(): void
    {
        $lock = Cache::lock("questions:q:{$this->questionId}", 110);
        if (!$lock->get()) {
            return;
        }

        try {
            $cfg = config('services.email_questions');
            $loader = new QuestionContextLoader((int) ($cfg['history_limit'] ?? 15));

            $question = $loader->loadQuestion($this->questionId);
            if ($question === null) {
                return; // уже обработан (status != pending) или не найден — claim не прошёл
            }

            $batchId = (int) ($question->batch_id ?: $question->conversation_batch_id);
            $batch = $loader->loadBatch($batchId);
            $items = $loader->loadRequestItems($batchId);
            $authorAnswers = $loader->loadAuthorAnswers($batchId);
            $sender = $batch !== null ? $loader->loadSender((int) $batch->sender_id) : null;

            $decision = (new QuestionAutoAnswerClassifier($this->makeClient($cfg), [
                'model' => (string) ($cfg['model'] ?? 'gpt-4o-mini'),
                'max_tokens' => (int) ($cfg['max_tokens'] ?? 1024),
            ]))->classify($question, $sender ?? (object) [], $items, $authorAnswers);

            // Детерминированный override: фото-запрос+артикул → ответ артикулом;
            // «не вижу таблицу/вышлите ещё раз» → повтор позиций. LLM на длинном
            // промпте эти случаи стабильно не ловит, поэтому решаем в коде.
            if (!$decision['can_auto_answer']) {
                $forced = $this->forcedAnswer($question, $items, $decision);
                if ($forced !== null) {
                    $decision['can_auto_answer'] = true;
                    $decision['answer_text'] = $forced;
                    $decision['used_history_index'] = null;
                    $decision['original_reply_id'] = null;
                    $decision['has_files_to_copy'] = false;
                }
            }

            if ($decision['can_auto_answer']) {
                $this->autoAnswer($loader, $batch, $sender, $question, $decision);

                return;
            }

            // Ветка автору: сперва дедуп через консолидацию (AI #2). Может апгрейдить
            // решение до авто-ответа, если в группе уже есть ответ автора.
            $consolidation = (new QuestionConsolidator($this->makeClient($cfg), [
                'model' => (string) ($cfg['model'] ?? 'gpt-4o-mini'),
                'max_tokens' => (int) ($cfg['max_tokens'] ?? 1024),
            ]))->consolidate($question, $decision['related_item_id']);

            if ($consolidation['can_auto_answer']) {
                $decision['answer_text'] = $consolidation['answer_text'];
                $decision['original_reply_id'] = null;
                $decision['has_files_to_copy'] = false;
                $this->autoAnswer($loader, $batch, $sender, $question, $decision);

                return;
            }

            $this->forwardToAuthor($loader, $batchId, $question, $decision, $consolidation);
        } catch (\Throwable $e) {
            Log::error('ProcessSupplierQuestionJob: failed', [
                'question_id' => $this->questionId,
                'error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Ветка авто-ответа: сборка письма и запись в outgoing_replies (pending).
     *
     * @param array<string,mixed> $decision
     */
    private function autoAnswer(
        QuestionContextLoader $loader,
        ?object $batch,
        ?object $sender,
        object $question,
        array $decision
    ): void {
        $originalMessage = $loader->loadOriginalMessage((int) $question->conversation_id);
        $templateId = $sender !== null
            ? (int) ($sender->preferred_template_id ?: $sender->template_id ?: 0)
            : 0;
        $template = $loader->loadTemplate($templateId > 0 ? $templateId : null);

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
            'answer_text' => $decision['answer_text'] ?? '',
            'original_reply_id' => $decision['original_reply_id'] ?? null,
            'has_files_to_copy' => $decision['has_files_to_copy'] ?? false,
        ];

        $reply = (new ReplyEmailBuilder())->build($context, $originalMessage, $template);
        (new SupplierQuestionPersister())->persistAuto($reply);

        Log::info('ProcessSupplierQuestionJob: auto-answered', [
            'question_id' => $this->questionId,
            'has_files' => $context['has_files_to_copy'],
        ]);
    }

    /**
     * Ветка направления автору: автор заявки + author_questions + смена статусов.
     *
     * @param array<string,mixed> $decision
     * @param array<string,mixed> $consolidation
     */
    private function forwardToAuthor(
        QuestionContextLoader $loader,
        int $batchId,
        object $question,
        array $decision,
        array $consolidation
    ): void {
        $authorUserId = $loader->loadAuthorUserId($batchId);

        (new SupplierQuestionPersister())->persistAuthor([
            'question_id' => (int) $question->question_id,
            'conversation_id' => (int) $question->conversation_id,
            'batch_id' => $batchId,
            'related_item_id' => $decision['related_item_id'] ?? null,
            'consolidation_id' => $consolidation['consolidation_id'] ?? null,
            'author_user_id' => $authorUserId,
        ]);

        Log::info('ProcessSupplierQuestionJob: forwarded to author', [
            'question_id' => $this->questionId,
            'consolidation_id' => $consolidation['consolidation_id'] ?? null,
            'is_similar' => $consolidation['is_similar'] ?? false,
        ]);
    }

    /**
     * Детерминированный авто-ответ для случаев, которые LLM-классификатор стабильно
     * не ловит на длинном промпте: запрос фото при наличии артикула позиции, и
     * просьба повторить заявку («не вижу таблицу / вышлите ещё раз»).
     *
     * @param array<int,object> $items
     * @param array<string,mixed> $decision
     */
    private function forcedAnswer(object $question, array $items, array $decision): ?string
    {
        $text = mb_strtolower((string) ($question->question_text ?? ''));

        // Просьба повторить заявку / «не вижу таблицу».
        if (preg_match('/не\\s*вид\\w*\\s+табл|таблиц\\w*\\s+не\\s+(виж|вид|приш|откр)|(вышлите|отправьте|пришлите|скиньте|продублируйте)\\s+.{0,30}(ещ[её]\\s*раз|повторно|заново|снова)|продублир|повтор\\w*\\s+(спис|позиц|заявк|запрос)/u', $text)) {
            $repeat = $this->repeatItemsText($items);
            if ($repeat !== null) {
                return $repeat;
            }
        }

        // Запрос фото/файла + у позиции есть артикул → предлагаем артикул вместо фото.
        if (preg_match('/фото|фотограф|снимок|шильд|чертеж|чертёж|схем|изображени/u', $text)) {
            $art = $this->relatedArticle($items, $decision);
            if ($art !== null) {
                return 'Фотографию предоставить не можем, но известен артикул производителя: ' . $art
                    . '. Сможете подобрать/предложить по этому артикулу? При необходимости пришлём дополнительные данные из заявки.';
            }
        }

        return null;
    }

    /**
     * Артикул позиции, к которой относится вопрос (по related_item_id; для заявки
     * из одной позиции — её артикул). null, если артикула нет / неоднозначно.
     *
     * @param array<int,object> $items
     * @param array<string,mixed> $decision
     */
    private function relatedArticle(array $items, array $decision): ?string
    {
        $itemId = (int) ($decision['related_item_id'] ?? 0);
        if ($itemId > 0) {
            foreach ($items as $it) {
                if ((int) ($it->id ?? 0) === $itemId) {
                    $art = trim((string) ($it->article ?? ''));

                    return ($art !== '' && mb_strtolower($art) !== 'не указан') ? $art : null;
                }
            }

            return null;
        }
        if (count($items) === 1) {
            $art = trim((string) ($items[0]->article ?? ''));

            return ($art !== '' && mb_strtolower($art) !== 'не указан') ? $art : null;
        }

        return null;
    }

    /**
     * Текстовый повтор запрошенных позиций (дубль заявки без вложений).
     *
     * @param array<int,object> $items
     */
    private function repeatItemsText(array $items): ?string
    {
        if ($items === []) {
            return null;
        }
        $lines = ['Дублируем заявку — запрошенные позиции:'];
        $n = 0;
        foreach ($items as $it) {
            $n++;
            $name = trim((string) ($it->name ?? ''));
            $art = trim((string) ($it->article ?? ''));
            $qty = trim((string) ($it->quantity ?? ''));
            $qn = str_replace(',', '.', $qty);
            if (is_numeric($qn)) {
                $qf = (float) $qn;
                $qty = (floor($qf) === $qf) ? (string) (int) $qf : rtrim(rtrim(number_format($qf, 3, '.', ''), '0'), '.');
            }
            $unit = trim((string) ($it->unit ?? ''));
            $artPart = ($art !== '' && mb_strtolower($art) !== 'не указан') ? (', артикул: ' . $art) : '';
            $qtyPart = ($qty !== '') ? (', кол-во: ' . $qty . ' ' . $unit) : '';
            $lines[] = $n . '. ' . $name . $artPart . $qtyPart;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $cfg блок config('services.email_questions')
     */
    private function makeClient(array $cfg): OpenAIClassifierClient
    {
        $oc = config('services.openai_classifier');

        return new OpenAIClassifierClient(
            baseUrl: rtrim((string) ($oc['base_url'] ?? ''), '/'),
            apiKey: (string) ($oc['api_key'] ?? ''),
            proxyKey: (string) ($oc['proxy_key'] ?? ''),
            modelMini: (string) ($oc['model_mini'] ?? 'gpt-4o-mini'),
            modelFull: (string) ($oc['model_full'] ?? 'gpt-4o'),
            timeout: (int) ($cfg['timeout'] ?? 60),
        );
    }
}
