<?php

namespace App\Services\Questions;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\Log;

/**
 * AI #1 триажа: решает, можно ли ответить на вопрос поставщика автоматически —
 * порт n8n «Prepare AI Context» + «AI Agent» (gpt-4o-mini) + «Parse AI Response».
 *
 * Системный и пользовательский промпты портированы дословно из n8n. Зовёт
 * OpenAI-совместимый прокси (json_object). На выходе нормализованная структура:
 * can_auto_answer / answer_text / related_item_index / related_item_id /
 * used_history_index / original_reply_id / has_files_to_copy.
 */
class QuestionAutoAnswerClassifier
{
    /**
     * @param array{model:string,max_tokens:int} $config
     */
    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly array $config,
    ) {
    }

    /**
     * @param object $question строка из QuestionContextLoader::loadQuestion
     * @param object $sender строка из loadSender
     * @param array<int,object> $items позиции батча (loadRequestItems)
     * @param array<int,object> $authorAnswers история ответов автора (loadAuthorAnswers)
     * @return array<string,mixed>
     */
    public function classify(object $question, object $sender, array $items, array $authorAnswers): array
    {
        $system = $this->systemPrompt($items, $authorAnswers);
        $user = $this->userPrompt($question, $sender, $items, $authorAnswers);

        try {
            $raw = $this->client->jsonCompletion(
                $this->config['model'],
                $system,
                $user,
                $this->config['max_tokens'],
            );

            $classification = $this->validate($raw);
        } catch (\Throwable $e) {
            Log::warning('QuestionAutoAnswerClassifier: AI failed', [
                'question_id' => $question->question_id ?? null,
                'error' => mb_substr($e->getMessage(), 0, 300),
            ]);
            $classification = [
                'can_auto_answer' => false,
                'answer_text' => null,
                'reasoning' => 'Failed to parse AI response: ' . $e->getMessage(),
                'related_item_index' => 1,
            ];
        }

        return $this->resolve($classification, $items, $authorAnswers);
    }

    /**
     * Порт валидации из «Parse AI Response»: проверка типа can_auto_answer.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function validate(array $raw): array
    {
        if (!is_bool($raw['can_auto_answer'] ?? null)) {
            throw new \RuntimeException('Invalid can_auto_answer value');
        }

        return $raw;
    }

    /**
     * Порт «Parse AI Response» (хвост): резолв related_item_id по related_item_index
     * и original_reply_id/has_files_to_copy по used_history_index.
     *
     * @param array<string,mixed> $classification
     * @param array<int,object> $items
     * @param array<int,object> $authorAnswers
     * @return array<string,mixed>
     */
    private function resolve(array $classification, array $items, array $authorAnswers): array
    {
        $itemIndex = (int) ($classification['related_item_index'] ?? 1);
        if ($itemIndex < 1) {
            $itemIndex = 1;
        }

        $relatedItem = $items[$itemIndex - 1] ?? ($items[0] ?? null);
        $relatedItemId = $relatedItem !== null ? (int) ($relatedItem->id ?? 0) : null;
        if ($relatedItemId === 0) {
            $relatedItemId = null;
        }

        $usedHistoryIndex = $classification['used_history_index'] ?? null;
        $originalReplyId = null;
        $hasFilesToCopy = false;

        if (($classification['can_auto_answer'] ?? false) && $usedHistoryIndex) {
            $historyItem = $authorAnswers[((int) $usedHistoryIndex) - 1] ?? null;
            if ($historyItem !== null && !empty($historyItem->original_reply_id)) {
                $originalReplyId = (int) $historyItem->original_reply_id;
                $hasFilesToCopy = (int) ($historyItem->files_count ?? 0) > 0;
            }
        }

        return [
            'can_auto_answer' => (bool) ($classification['can_auto_answer'] ?? false),
            'answer_text' => $classification['answer_text'] ?? null,
            'reasoning' => (string) ($classification['reasoning'] ?? ''),
            'related_item_index' => $itemIndex,
            'related_item_id' => $relatedItemId,
            'used_history_index' => $usedHistoryIndex !== null ? (int) $usedHistoryIndex : null,
            'original_reply_id' => $originalReplyId,
            'has_files_to_copy' => $hasFilesToCopy,
        ];
    }

    /**
     * Дословный порт системного промпта n8n «Prepare AI Context» (v5.1). Список
     * позиций и история ответов автора подмешиваются в системный текст так же, как
     * в исходном узле.
     *
     * @param array<int,object> $items
     * @param array<int,object> $authorAnswers
     */
    private function systemPrompt(array $items, array $authorAnswers): string
    {
        return <<<'PROMPT'
Ты — помощник для АВТОМАТИЧЕСКИХ ответов на вопросы поставщиков.

КОНТЕКСТ: Мы (заказчик) отправили запрос на КП. Поставщик задает уточняющие вопросы.

ФИЛОСОФИЯ: Отвечаем МАКСИМАЛЬНО автоматически. Привлекаем человека ТОЛЬКО если нужен ВЫБОР между конкретными вариантами или информация, которой нет в заявке.

ЗАДАЧА:
1. Определить можем ли ответить автоматически (can_auto_answer)
2. Если НЕ можем (can_auto_answer: false) — определить К КАКОЙ ПОЗИЦИИ относится вопрос (related_item_index)
3. Если используем ответ из ИСТОРИИ — указать used_history_index

⚠️ ПЕРВЫМ ДЕЛОМ — ПРОВЕРЬ ИСТОРИЮ ОТВЕТОВ АВТОРА!

Перед тем как решить can_auto_answer: false, ОБЯЗАТЕЛЬНО:
1. Прочитай раздел "ИСТОРИЯ ОТВЕТОВ АВТОРА"
2. Найди похожие вопросы (про те же характеристики: длина, количество, размер, напряжение и т.д.)
3. Если ответ есть — используй его! can_auto_answer: true
4. ОБЯЗАТЕЛЬНО укажи used_history_index — номер использованного ответа из истории (1, 2, 3...)

🚨 КРИТИЧЕСКИ ВАЖНО — ОТВЕТЫ С ФАЙЛАМИ:
Если в истории у ответа есть 📎 (прикреплённые файлы) — и текущий вопрос похож:
- Используй этот ответ (can_auto_answer: true)
- ОБЯЗАТЕЛЬНО укажи used_history_index
- Файлы будут автоматически прикреплены к ответу!

ПРИМЕРЫ когда НАДО использовать историю:
- ИСТОРИЯ #2: "Пришлите фото" → "Фото" 📎 1 файл
- Новый вопрос: "Нужно фото для идентификации"
- Ответ: can_auto_answer: true, answer_text: "Фото", used_history_index: 2
- Файл из истории #2 будет прикреплён автоматически!

- История: "Длина?" → "5 шт по 58м"
- Новые вопросы которые ПОКРЫВАЮТСЯ этим ответом:
  * "Куски по 160м?" → "Нет, требуется 5 шт по 58м"
  * "Уточните количество и длину" → "Требуется 5 штук по 58 метров"
  * "Сколько метров?" → "58 метров на отрезок, всего 5 штук"
  * "Какой метраж?" → "По 58 метров, 5 штук"

✅ МОЖЕШЬ ответить АВТОМАТИЧЕСКИ на:

1. ВСЕ ОРГАНИЗАЦИОННЫЕ ДАННЫЕ (берём из контекста и ПОДСТАВЛЯЕМ РЕАЛЬНЫЕ ЗНАЧЕНИЯ):
   - Адрес / регион / город → используй actual_address из КОНТЕКСТА
   - ИНН / КПП / реквизиты → используй organization_inn, organization_kpp из КОНТЕКСТА
   - Контакты / телефон / email → используй данные sender из КОНТЕКСТА
   - Карточка предприятия / карта партнера / карточка организации →
     ЭТО НАШИ РЕКВИЗИТЫ для оформления КП на нас.
     ОБЯЗАТЕЛЬНО сформируй ответ с РЕАЛЬНЫМИ данными из КОНТЕКСТА:
     Наименование, ИНН, КПП, Адрес, Телефон, Email
   - "Кто вы?" / "Вы кто такие?" / "Представьтесь" / "Что за компания?" →
     "Мы - [organization_name]. Направляли вам запрос на коммерческое предложение. Будем рады сотрудничеству!"
   - "Где нашли наш адрес/контакт?" →
     "Нашли вашу компанию в открытых источниках как поставщика необходимого оборудования."

   КРИТИЧЕСКИ ВАЖНО:
   - Поставщик просит НАШИ данные, чтобы выставить НАМ предложение!
   - НЕ пиши "будет предоставлена позже" — предоставь данные СЕЙЧАС
   - ВСЕГДА подставляй РЕАЛЬНЫЕ значения из раздела КОНТЕКСТ

2. СТАНДАРТНЫЕ УСЛОВИЯ РАБОТЫ:
   - Условия оплаты → "Условия оплаты обсуждаются индивидуально. Пришлите КП с вашими условиями"
   - НДС → "Можем работать с НДС и без НДС. Укажите оба варианта в КП"
   - Формат КП → "Пришлите КП в любом удобном формате"
   - Срок рассмотрения КП → "Просим прислать КП в течение 3-5 рабочих дней"

3. ХАРАКТЕР ЗАПРОСА И ПРОЦЕДУРА:
   - Тендер или прямой запрос → "Это прямой запрос на коммерческое предложение"
   - Сроки принятия решения → "Решение о закупке будет принято по результатам анализа полученных КП"
   - Критерии выбора → "Выбор производится по совокупности факторов: цена, срок поставки, наличие на складе"

4. Б/У, REF, ВОССТАНОВЛЕННЫЕ - ОТКАЗ:
   - "Спасибо за предложение, но б/у и восстановленные запчасти не рассматриваем. Интересует только новое оборудование"

5. АНАЛОГИ И ЗАМЕНИТЕЛИ (новые - ДА):
   - "Да, рассмотрим аналоги. Укажите характеристики, артикул и цену аналога в КП"

6. СРОКИ ПОСТАВКИ:
   - "Укажите ваш реальный срок поставки в КП. Рассмотрим любые варианты"

7. ТАРГЕТ ПО ЦЕНЕ:
   - "Целевой цены нет. Ждём ваше лучшее предложение"

8. СЕРТИФИКАТЫ И ДОКУМЕНТАЦИЯ:
   - "Укажите в КП наличие или отсутствие сертификатов. Рассмотрим оба варианта"

9. ДОСТАВКА:
   - "Доставку организуем самостоятельно. Укажите цену на условиях самовывоза"

10. УТОЧНЕНИЕ АРТИКУЛА:
    → Если артикул ЕСТЬ в заявке: ✅ can_auto_answer: true - ПРЕДОСТАВЬ АРТИКУЛ
    → Если артикула НЕТ: ❌ can_auto_answer: false

11. ФОТО, СХЕМЫ, ЧЕРТЕЖИ ДЛЯ ИДЕНТИФИКАЦИИ:
    → Если в ИСТОРИИ есть ответ с 📎 файлами на похожий вопрос: ✅ can_auto_answer: true + used_history_index
    → Если в истории НЕТ такого ответа: ❌ can_auto_answer: false

12. ПОДТВЕРЖДЕНИЯ ПРЕДЛОЖЕНИЙ:
    → "Да, укажите условия в вашем коммерческом предложении"

13. ТЕХНИЧЕСКИЕ ВОПРОСЫ:
    → Если информация ЕСТЬ в заявке - используй её
    → Если НЕТ - can_auto_answer: false

14. ИСТОРИЯ ОТВЕТОВ АВТОРА — ПРИОРИТЕТНЫЙ ИСТОЧНИК:
    → ПЕРЕД анализом вопроса ВСЕГДА проверяй раздел "ИСТОРИЯ ОТВЕТОВ АВТОРА"
    → Если там есть ответ на ПОХОЖИЙ или СВЯЗАННЫЙ вопрос — используй его!
    → ✅ can_auto_answer: true — адаптируй ответ автора под текущий вопрос
    → 🚨 ОБЯЗАТЕЛЬНО укажи used_history_index!

    ВАЖНО: Поставщик может задать вопрос ИНАЧЕ, чем в истории:
    - История: "Какая длина?" → "5 шт по 58м"
    - Новый вопрос: "Куски по 160м?" → ЭТО ТОТ ЖЕ ВОПРОС О ДЛИНЕ!
    - Ответ: "Нет, требуется 5 шт по 58м"

    ВАЖНО: Цифры в маркировке товара (ГОСТ, артикул) — это НЕ ответы на вопросы!
    - Название: "Канат ЛК-0 12ГЛ-В-Н-Р-1570(160) ГОСТ3077-80"
    - "(160)" здесь — часть маркировки, НЕ длина в метрах
    - Реальную длину смотри в ИСТОРИИ ОТВЕТОВ АВТОРА

    ПРИМЕРЫ:
    ИСТОРИЯ #1: "Длина?" → "5 шт по 58м"
    Вопрос: "Куски по 160м?"
    → can_auto_answer: true, answer_text: "Нет, требуется 5 шт по 58м", used_history_index: 1

    ИСТОРИЯ #3: "Напряжение?" → "380В"
    Вопрос: "220 или 380?"
    → can_auto_answer: true, answer_text: "Напряжение 380В", used_history_index: 3

❌ НЕ МОЖЕШЬ ответить ТОЛЬКО если:

1. ВЫБОР между КОНКРЕТНЫМИ вариантами без данных в заявке
2. ЗАПРОС ФОТО/СХЕМ/ЧЕРТЕЖЕЙ — и в истории НЕТ ответа с файлами
3. Вопрос требует информации, которой НЕТ в заявке И НЕТ в истории

15. НАМЕРЕНИЯ / БЮДЖЕТ / ЗАКУПКА:
    - "Планируете бюджет или закупку?" → "Планируем закупку при получении подходящего предложения"
    - "Для бюджета или реальная потребность?" → "Реальная потребность в закупке"
    - "Это для планирования?" → "Нет, это запрос на закупку"
    - "Когда планируете закупать?" → "Закупка планируется по результатам анализа КП"
═══════════════════════════════════════════════════════════════
ОПРЕДЕЛЕНИЕ ПОЗИЦИИ (related_item_index) - ТОЛЬКО если can_auto_answer: false
═══════════════════════════════════════════════════════════════

Если вопрос направляется к автору (can_auto_answer: false), ОБЯЗАТЕЛЬНО определи к какой ПОЗИЦИИ он относится:

1. Посмотри на список ПОЗИЦИЙ в запросе (ПОЗИЦИЯ 1, ПОЗИЦИЯ 2, ...)
2. Определи, к какой позиции относится вопрос по:
   - Названию товара в вопросе
   - Артикулу или бренду
   - Техническим характеристикам
3. Укажи номер позиции в поле related_item_index

ПРАВИЛА:
- Если в батче ОДНА позиция → вопрос о товаре ОЧЕВИДНО относится к ней, related_item_index: 1 (НЕ проси уточнить позицию!)
- Если вопрос ЯВНО про конкретный товар → укажи номер этой позиции
- Если вопрос ОБЩИЙ (реквизиты, доставка, "кто вы") → укажи 1
- Если позиций МНОГО и НЕВОЗМОЖНО определить → укажи 1

ПРИМЕРЫ определения позиции:
- В заявке ОДНА позиция + "Пришлите фото шильда" → related_item_index: 1 (очевидно про неё!)
- "Пришлите фото шильдика мотора KEV" + ПОЗИЦИЯ 2 содержит "мотор KEV" → related_item_index: 2
- "Какой артикул преобразователя?" + ПОЗИЦИЯ 1 содержит "преобразователь" → related_item_index: 1
- "Пришлите карточку организации" → общий вопрос → related_item_index: 1
- "Нужно фото для всех позиций" → related_item_index: 1

═══════════════════════════════════════════════════════════════

ФОРМАТ ОТВЕТА (строго JSON):

Если МОЖЕШЬ ответить автоматически БЕЗ истории:
{
  "can_auto_answer": true,
  "answer_text": "текст ответа",
  "reasoning": "пояснение"
}

Если МОЖЕШЬ ответить ИСПОЛЬЗУЯ ИСТОРИЮ:
{
  "can_auto_answer": true,
  "answer_text": "текст ответа",
  "reasoning": "пояснение",
  "used_history_index": номер_истории
}

Если НЕ МОЖЕШЬ ответить (направляем автору):
{
  "can_auto_answer": false,
  "answer_text": null,
  "reasoning": "пояснение",
  "related_item_index": номер_позиции
}

ВАЖНО:
- НЕ добавляй приветствие и подпись
- Только валидный JSON без markdown
- Если используешь ответ из истории — ВСЕГДА указывай used_history_index
PROMPT;
    }

    /**
     * Дословный порт userPrompt из n8n «Prepare AI Context».
     *
     * @param array<int,object> $items
     * @param array<int,object> $authorAnswers
     */
    private function userPrompt(object $question, object $sender, array $items, array $authorAnswers): string
    {
        $itemsList = $this->renderItemsList($items);
        $authorAnswersText = $this->renderAuthorAnswers($authorAnswers);

        $questionText = (string) ($question->question_text ?? '');
        $questionType = (string) ($question->question_type ?? '');
        $itemsCount = count($items);

        $orgName = $this->val($sender->organization_name ?? null, 'не указана');
        $orgInn = $this->val($sender->organization_inn ?? null, 'не указан');
        $orgKpp = $this->val($sender->organization_kpp ?? null, 'не указан');
        $orgAddress = $this->val($sender->organization_actual_address ?? null, 'не указан');
        $senderFullName = (string) ($sender->sender_full_name ?? '');
        $phone = $this->val($sender->phone ?? null, 'не указан');
        $email = $this->val($sender->email ?? null, 'не указан');

        return <<<PROMPT

ВОПРОС: "{$questionText}"
Тип: {$questionType}

КОНТЕКСТ (используй эти РЕАЛЬНЫЕ данные в ответах):
Организация: {$orgName}
ИНН: {$orgInn}
КПП: {$orgKpp}
Адрес: {$orgAddress}
Отправитель: {$senderFullName}
Телефон: {$phone}
Email: {$email}

ПОЗИЦИИ В ЗАЯВКЕ (всего {$itemsCount}):
{$itemsList}
{$authorAnswersText}

ВАЖНО:
- При запросе реквизитов - подставляй РЕАЛЬНЫЕ значения из КОНТЕКСТА
- Если can_auto_answer: false - ОБЯЗАТЕЛЬНО укажи related_item_index (номер позиции)
- Если используешь ответ из ИСТОРИИ - ОБЯЗАТЕЛЬНО укажи used_history_index (номер истории)

PROMPT;
    }

    /**
     * @param array<int,object> $items
     */
    private function renderItemsList(array $items): string
    {
        $parts = [];
        foreach (array_values($items) as $i => $item) {
            $pos = $i + 1;
            $name = (string) ($item->name ?? '');
            $brand = $this->val($item->brand ?? null, 'не указан');
            $article = $this->val($item->article ?? null, 'не указан');
            $quantity = (string) ($item->quantity ?? '');
            $unit = (string) ($item->unit ?? '');
            $category = $this->val($item->category ?? null, 'не указана');
            $description = $this->val($item->description ?? null, 'нет');

            $parts[] = "\nПОЗИЦИЯ {$pos}:\n"
                . "   Название: {$name}\n"
                . "   Бренд: {$brand}\n"
                . "   Артикул: {$article}\n"
                . "   Количество: {$quantity} {$unit}\n"
                . "   Категория: {$category}\n"
                . "   Описание: {$description}\n";
        }

        return implode("\n", $parts);
    }

    /**
     * @param array<int,object> $authorAnswers
     */
    private function renderAuthorAnswers(array $authorAnswers): string
    {
        if ($authorAnswers === []) {
            return '';
        }

        $text = "\n\nИСТОРИЯ ОТВЕТОВ АВТОРА ПО ЭТОЙ ЗАЯВКЕ:\n";
        $text .= "(Используй релевантные ответы как образец для похожих вопросов)\n";
        $text .= "⚠️ Если используешь ответ из истории — ОБЯЗАТЕЛЬНО укажи used_history_index!\n\n";

        $blocks = [];
        foreach (array_values($authorAnswers) as $i => $a) {
            $num = $i + 1;
            $itemName = $a->item_name ?? null;
            if ($itemName) {
                $article = $a->item_article ?? null;
                $articlePart = ($article && $article !== 'null') ? ', арт. ' . $article : '';
                $itemInfo = "[Позиция: {$itemName}{$articlePart}]";
            } else {
                $itemInfo = '[Общий вопрос по заявке]';
            }

            $filesInfo = ((int) ($a->files_count ?? 0) > 0)
                ? "\n   📎 К ответу прикреплено файлов: " . (int) $a->files_count
                : '';

            $qText = (string) ($a->question_text ?? '');
            $aText = (string) ($a->author_answer ?? '');

            $blocks[] = "ИСТОРИЯ #{$num}: {$itemInfo}\n   Вопрос: \"{$qText}\"\n   Ответ автора: \"{$aText}\"{$filesInfo}";
        }

        return $text . implode("\n\n", $blocks);
    }

    private function val(mixed $value, string $default): string
    {
        $str = trim((string) ($value ?? ''));

        return $str !== '' ? $str : $default;
    }
}
