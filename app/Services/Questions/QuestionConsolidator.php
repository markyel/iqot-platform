<?php

namespace App\Services\Questions;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI #2 триажа (ветка «can_auto_answer=false»): дедупликация вопроса по позиции —
 * порт n8n «Get Existing Consolidations» + «Prepare Consolidation Check» +
 * «AI Compare Questions» (gpt-4o-mini) + «Parse Compare Response» + ветвей
 * Has Existing Consolidations? / Is Similar? / Has Existing Answer? и узлов
 * Create/Assign Consolidation / Prepare Consolidation Auto Answer.
 *
 * Решает: новый вопрос относится к существующей группе вопросов по той же позиции
 * или это новая группа. Если относится к группе, у которой уже есть ответ автора —
 * апгрейдит решение до авто-ответа (auto_answer_source='consolidation').
 */
class QuestionConsolidator
{
    private const CONN = 'reports';

    /**
     * @param array{model:string,max_tokens:int} $config
     */
    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly array $config,
    ) {
    }

    /**
     * Возвращает резолв консолидации для вопроса, направляемого автору.
     *
     * @return array{
     *   consolidation_id:?int,
     *   can_auto_answer:bool,
     *   answer_text:?string,
     *   auto_answer_source:?string,
     *   is_similar:bool,
     *   compare_reasoning:string
     * }
     */
    public function consolidate(object $question, ?int $relatedItemId): array
    {
        $questionText = (string) ($question->question_text ?? '');
        $groups = $this->loadExistingGroups($relatedItemId);

        // Порт «Has Existing Consolidations?» = FALSE → Create New Consolidation +
        // Assign New Consolidation (групп нет — заводим новую).
        if ($groups === []) {
            return $this->newGroup($relatedItemId, $questionText, false, '');
        }

        // Порт «Prepare Compare Prompt» + «AI Compare Questions» + «Parse Compare Response».
        $compare = $this->compare($questionText, $groups);
        $isSimilar = (bool) ($compare['is_similar'] ?? false);
        $matchingId = isset($compare['matching_consolidation_id']) && $compare['matching_consolidation_id'] !== null
            ? (int) $compare['matching_consolidation_id']
            : null;
        $reasoning = (string) ($compare['reasoning'] ?? '');

        // Порт «Is Similar?» = FALSE → Create New Consolidation 2 + Assign New Consolidation 2.
        if (!$isSimilar || $matchingId === null) {
            return $this->newGroup($relatedItemId, $questionText, false, $reasoning);
        }

        $matched = $this->findGroup($groups, $matchingId);
        // AI вернул id, которого нет среди групп → трактуем как «не похоже», новая группа.
        if ($matched === null) {
            return $this->newGroup($relatedItemId, $questionText, false, $reasoning);
        }

        // Порт «Has Existing Answer?» = TRUE → Prepare Consolidation Auto Answer
        // (в группе уже есть ответ автора — используем его как авто-ответ).
        if ($matched['has_answer'] && $matched['answer_text'] !== null && $matched['answer_text'] !== '') {
            return [
                'consolidation_id' => $matchingId,
                'can_auto_answer' => true,
                'answer_text' => $matched['answer_text'],
                'auto_answer_source' => 'consolidation',
                'is_similar' => true,
                'compare_reasoning' => $reasoning,
            ];
        }

        // Порт «Has Existing Answer?» = FALSE → Assign Existing Consolidation
        // (похоже, но ответа ещё нет — цепляем к существующей группе, идёт автору).
        return [
            'consolidation_id' => $matchingId,
            'can_auto_answer' => false,
            'answer_text' => null,
            'auto_answer_source' => null,
            'is_similar' => true,
            'compare_reasoning' => $reasoning,
        ];
    }

    /**
     * Порт «Get Existing Consolidations» + «Prepare Consolidation Check»: группы
     * вопросов по позиции с флагом наличия ответа автора.
     *
     * @return array<int,array{consolidation_id:int,consolidated_text:?string,questions:array<int,array{id:int,text:?string,status:?string}>,has_answer:bool,answer_text:?string}>
     */
    private function loadExistingGroups(?int $relatedItemId): array
    {
        if ($relatedItemId === null || $relatedItemId <= 0) {
            return [];
        }

        $rows = DB::connection(self::CONN)->table('question_consolidation as c')
            ->join('supplier_questions as sq_existing', 'sq_existing.consolidation_id', '=', 'c.id')
            ->where('c.request_item_id', $relatedItemId)
            ->orderBy('c.id')
            ->orderBy('sq_existing.id')
            ->get([
                'c.id as consolidation_id',
                'c.consolidated_text',
                'sq_existing.id as existing_question_id',
                'sq_existing.question_text as existing_question_text',
                'sq_existing.status as existing_status',
                'sq_existing.author_answer as existing_answer',
            ]);

        $groups = [];
        foreach ($rows as $row) {
            $cid = (int) $row->consolidation_id;
            if (!isset($groups[$cid])) {
                $groups[$cid] = [
                    'consolidation_id' => $cid,
                    'consolidated_text' => $row->consolidated_text,
                    'questions' => [],
                    'has_answer' => false,
                    'answer_text' => null,
                ];
            }

            $groups[$cid]['questions'][] = [
                'id' => (int) $row->existing_question_id,
                'text' => $row->existing_question_text,
                'status' => $row->existing_status,
            ];

            if ($row->existing_status === 'author_answered' && !empty($row->existing_answer)) {
                $groups[$cid]['has_answer'] = true;
                $groups[$cid]['answer_text'] = $row->existing_answer;
            }
        }

        return array_values($groups);
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     * @return array<string,mixed>|null
     */
    private function findGroup(array $groups, int $consolidationId): ?array
    {
        foreach ($groups as $group) {
            if ((int) $group['consolidation_id'] === $consolidationId) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Порт «Create New Consolidation» + «Assign New Consolidation»: новая группа.
     *
     * @return array{consolidation_id:?int,can_auto_answer:bool,answer_text:?string,auto_answer_source:?string,is_similar:bool,compare_reasoning:string}
     */
    private function newGroup(?int $relatedItemId, string $questionText, bool $isSimilar, string $reasoning): array
    {
        $consolidationId = $this->createConsolidation($relatedItemId, $questionText);

        return [
            'consolidation_id' => $consolidationId,
            'can_auto_answer' => false,
            'answer_text' => null,
            'auto_answer_source' => null,
            'is_similar' => $isSimilar,
            'compare_reasoning' => $reasoning,
        ];
    }

    /**
     * Порт INSERT-узла «Create New Consolidation»: текст группы = первые 500 симв.
     * вопроса. request_item_id может быть null (как `|| 'NULL'` в n8n).
     */
    private function createConsolidation(?int $relatedItemId, string $questionText): int
    {
        return (int) DB::connection(self::CONN)->table('question_consolidation')->insertGetId([
            'request_item_id' => $relatedItemId,
            'consolidated_text' => mb_substr($questionText, 0, 500),
        ]);
    }

    /**
     * Порт «Prepare Compare Prompt» + «AI Compare Questions» + «Parse Compare
     * Response»: спрашиваем AI, относится ли новый вопрос к одной из групп.
     *
     * @param array<int,array<string,mixed>> $groups
     * @return array{is_similar:bool,matching_consolidation_id:?int,reasoning:string}
     */
    private function compare(string $questionText, array $groups): array
    {
        $user = $this->comparePrompt($questionText, $groups);

        try {
            $raw = $this->client->jsonCompletion(
                $this->config['model'],
                'Ты сравниваешь вопросы поставщиков. Отвечай ТОЛЬКО валидным JSON без markdown.',
                $user,
                $this->config['max_tokens'],
            );

            return [
                'is_similar' => (bool) ($raw['is_similar'] ?? false),
                'matching_consolidation_id' => $raw['matching_consolidation_id'] ?? null,
                'reasoning' => (string) ($raw['reasoning'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('QuestionConsolidator: AI compare failed', [
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);

            return [
                'is_similar' => false,
                'matching_consolidation_id' => null,
                'reasoning' => 'Parse failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Дословный порт текста промпта из n8n «Prepare Compare Prompt».
     *
     * @param array<int,array<string,mixed>> $groups
     */
    private function comparePrompt(string $questionText, array $groups): string
    {
        $lines = [];
        foreach (array_values($groups) as $i => $group) {
            $num = $i + 1;
            $cid = (int) $group['consolidation_id'];
            $text = (string) ($group['consolidated_text'] ?? '');
            $lines[] = "{$num}. [ID:{$cid}] \"{$text}\"";
        }
        $groupsList = implode("\n", $lines);

        return <<<PROMPT
Новый вопрос от поставщика:
"{$questionText}"

Существующие группы вопросов по этой же позиции:
{$groupsList}

Определи, относится ли новый вопрос к одной из существующих групп.

КРИТЕРИИ ОДИНАКОВОСТИ:
- Просят уточнить артикул/модель/маркировку/тип
- Просят фото/изображение/шильдик
- Спрашивают "какой именно?", "какой вариант?"
- Требуют ИДЕНТИФИЦИРОВАТЬ товар

РАЗНЫЕ вопросы - про РАЗНЫЕ темы:
- Напряжение ≠ Размер
- Количество ≠ Артикул

JSON ответ:
{
  "is_similar": true/false,
  "matching_consolidation_id": число или null,
  "reasoning": "пояснение"
}
PROMPT;
    }
}
