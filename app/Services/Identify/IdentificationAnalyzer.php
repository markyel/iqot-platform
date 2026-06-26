<?php

namespace App\Services\Identify;

use App\Services\Api\OpenAIClassifierClient;

/**
 * AI-идентификация неопознанного письма к заявке (queue_id) — порт n8n-узлов
 * «Prepare AI Prompt» + «AI Agent» + «Parse AI Response» (активная ветка графа;
 * усиленный «Parse AI Response1» в графе не подключён).
 *
 * Собирает системный промпт (дословный порт: классификация письма, сопоставление
 * ПО НАЗВАНИЮ товара, токен) и userPrompt (письмо + текст вложений + список
 * кандидатов с товарами и пометкой ⭐ по токену), зовёт OpenAIClassifierClient
 * (json_object, temperature 0) и валидирует ответ: queue_id обязан быть среди
 * кандидатов, confidence ≥ min. Совпадение по токену поднимает confidence до 0.9.
 */
class IdentificationAnalyzer
{
    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly float $minConfidence,
    ) {
    }

    /**
     * @param array<int,object> $candidates строки CandidateBatchLoader
     * @param array{queue_id:int}|null $tokenMatch
     * @return array{identified_queue_id:?int,identified_batch_id:?int,identified_supplier_id:?int,confidence:float,reasoning:string,matched_items:array<int,string>,is_price_offer:bool,validation_passed:bool}
     */
    public function analyze(
        string $fromEmail,
        string $toEmail,
        ?string $subject,
        string $bodyClean,
        string $documentText,
        array $candidates,
        ?array $tokenMatch,
    ): array {
        $system = $this->systemMessage();
        $user = $this->userPrompt($fromEmail, $toEmail, $subject, $bodyClean, $documentText, $candidates, $tokenMatch);

        try {
            $parsed = $this->client->jsonCompletion($this->model, $system, $user, $this->maxTokens);
        } catch (\Throwable $e) {
            return $this->fallback('Ошибка AI: ' . mb_substr($e->getMessage(), 0, 200));
        }

        return $this->parse($parsed, $candidates, $tokenMatch);
    }

    /**
     * Порт «Parse AI Response»: доверяем выбору AI, но проверяем что queue_id есть
     * среди кандидатов и confidence ≥ min. Токен-матч поднимает уверенность.
     *
     * @param array<string,mixed> $parsed
     * @param array<int,object> $candidates
     * @param array{queue_id:int}|null $tokenMatch
     * @return array{identified_queue_id:?int,identified_batch_id:?int,identified_supplier_id:?int,confidence:float,reasoning:string,matched_items:array<int,string>,is_price_offer:bool,validation_passed:bool}
     */
    private function parse(array $parsed, array $candidates, ?array $tokenMatch): array
    {
        $result = $this->fallback('');
        $result['confidence'] = (float) ($parsed['confidence'] ?? 0);
        $result['reasoning'] = (string) ($parsed['reasoning'] ?? '');
        $result['matched_items'] = array_values(array_filter(
            (array) ($parsed['matched_items'] ?? []),
            static fn ($v) => is_string($v) && $v !== ''
        ));
        $result['is_price_offer'] = (bool) ($parsed['is_price_offer'] ?? false);

        $queueId = $parsed['identified_queue_id'] ?? null;
        $queueId = is_numeric($queueId) ? (int) $queueId : null;

        if ($queueId !== null && $result['confidence'] >= $this->minConfidence) {
            $matched = null;
            foreach ($candidates as $c) {
                if ((int) $c->queue_id === $queueId) {
                    $matched = $c;
                    break;
                }
            }

            if ($matched !== null) {
                $result['identified_queue_id'] = (int) $matched->queue_id;
                $result['identified_batch_id'] = $matched->batch_id !== null ? (int) $matched->batch_id : null;
                $result['identified_supplier_id'] = $matched->supplier_id !== null ? (int) $matched->supplier_id : null;
                $result['validation_passed'] = true;

                if ($tokenMatch !== null && ($tokenMatch['queue_id'] ?? 0) === $queueId) {
                    $result['confidence'] = max($result['confidence'], 0.9);
                    $result['reasoning'] .= ' [Токен совпал]';
                }
            } else {
                $result['reasoning'] .= ' [ОШИБКА: queue_id не найден в кандидатах]';
            }
        }

        return $result;
    }

    /**
     * @return array{identified_queue_id:null,identified_batch_id:null,identified_supplier_id:null,confidence:float,reasoning:string,matched_items:array<int,string>,is_price_offer:bool,validation_passed:bool}
     */
    private function fallback(string $reason): array
    {
        return [
            'identified_queue_id' => null,
            'identified_batch_id' => null,
            'identified_supplier_id' => null,
            'confidence' => 0.0,
            'reasoning' => $reason,
            'matched_items' => [],
            'is_price_offer' => false,
            'validation_passed' => false,
        ];
    }

    /**
     * @param array<int,object> $candidates
     * @param array{queue_id:int}|null $tokenMatch
     */
    private function userPrompt(
        string $fromEmail,
        string $toEmail,
        ?string $subject,
        string $bodyClean,
        string $documentText,
        array $candidates,
        ?array $tokenMatch,
    ): string {
        $candidatesText = '';
        foreach ($candidates as $c) {
            $items = $this->parseItems($c->request_items_data ?? null);
            if ($items !== []) {
                $lines = array_map(
                    static fn (array $i) => '  - ' . trim(($i['brand'] ?? '') . ' ' . ($i['name'] ?? ''))
                        . ' / Артикул: ' . ($i['article'] ?? 'без артикула')
                        . ' (' . ($i['quantity'] ?? 1) . ' шт)',
                    $items
                );
                $itemsStr = implode("\n", $lines);
            } else {
                $itemsStr = '  (нет данных о товарах)';
            }

            $star = ($tokenMatch !== null && ($tokenMatch['queue_id'] ?? 0) === (int) $c->queue_id)
                ? ' ⭐ СОВПАДЕНИЕ ПО ТОКЕНУ'
                : '';

            $candidatesText .= "\n\n--- queue_id={$c->queue_id} (batch={$c->batch_id}, supplier={$c->supplier_id}){$star} ---\n"
                . 'Токен: ' . ($c->tracking_token ?? 'нет') . "\n"
                . 'Отправлена: ' . ($c->sent_at ?? '') . ' на ' . ($c->sent_to_email ?? '') . "\n"
                . 'Поставщик: ' . ($c->supplier_name ?? '') . ' (' . ($c->supplier_email ?? '') . ")\n"
                . 'Дней назад: ' . ($c->days_since_sent ?? '') . "\n"
                . "Товары:\n" . $itemsStr;
        }

        return 'ВХОДЯЩЕЕ ПИСЬМО:'
            . "\nОт: " . $fromEmail
            . "\nКому: " . $toEmail
            . "\nТема: " . ($subject !== null && $subject !== '' ? $subject : '(без темы)')
            . "\n\nТекст письма:\n" . ($bodyClean !== '' ? $bodyClean : '(пусто)')
            . ($documentText !== '' ? "\n\nПРИЛОЖЕННЫЕ ДОКУМЕНТЫ:\n" . $documentText : '')
            . "\n\n---\n\nКАНДИДАТЫ (возможные заявки):\n" . ($candidatesText !== '' ? $candidatesText : '(не найдено)')
            . "\n\n---\n\nОпредели к какому queue_id относится это письмо. Сравни НАЗВАНИЯ товаров из письма с НАЗВАНИЯМИ товаров кандидатов. Ответь ТОЛЬКО JSON.";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function parseItems(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Дословный порт системного промпта n8n «Prepare AI Prompt».
     */
    private function systemMessage(): string
    {
        return <<<'PROMPT'
Ты — AI ассистент для идентификации входящих писем от поставщиков.

Твоя задача: определить к какой заявке (queue_id) относится это письмо.

=== ГЛАВНОЕ: ЧТО С ЧЕМ СРАВНИВАТЬ ===

Ты сравниваешь:
  ТОВАРЫ ИЗ ВХОДЯЩЕГО ПИСЬМА  ←→  ТОВАРЫ ИЗ СПИСКА КАНДИДАТОВ

НЕ сравнивай данные внутри письма между собой!
НЕ сравнивай артикул из КП с артикулом из того же КП — это бессмысленно!

Пример ПРАВИЛЬНОГО сопоставления:
  Письмо: "Плата RS-14" (артикул поставщика L00000785)
  Кандидат queue_id=5262: "Плата RS-14 GAA25005B1" (наш артикул GDA25005B10)
  → Совпадение по НАЗВАНИЮ "Плата RS-14" → выбираем queue_id=5262

=== ПРО АРТИКУЛЫ — ВАЖНО! ===

Артикулы поставщика (L00000785, L00012118) — это ИХ внутренние коды.
Наши артикулы другие (GDA25005B10, FAA24350DJ2, KM1363959).
Артикулы почти НИКОГДА не совпадают напрямую!

Поэтому ОСНОВНОЙ способ сопоставления — по НАЗВАНИЮ товара, а не по артикулу.

=== ШАГ 0: КЛАССИФИКАЦИЯ ПИСЬМА ===

СНАЧАЛА определи тип письма:

1. АВТООТВЕТ / ПРИВЕТСТВИЕ — письмо НЕ содержит информации о конкретном товаре:
   - "Ваша заявка принята / получена / зарегистрирована"
   - "Спасибо за обращение"
   - "Меня зовут [имя], я ваш менеджер"
   - "Мы обрабатываем ваш запрос"
   - "Ознакомьтесь с презентацией о компании"
   → СРАЗУ верни null с reasoning "Автоответ/приветствие без информации о товаре"

2. УТОЧНЯЮЩИЙ ВОПРОС — поставщик спрашивает детали:
   - "Уточните количество / сроки / характеристики"
   - "Какой именно артикул вам нужен?"
   → Можно идентифицировать, если понятно о каком товаре речь

3. КОММЕРЧЕСКОЕ ПРЕДЛОЖЕНИЕ — содержит:
   - Конкретные цены (руб, ₽, USD, EUR)
   - Сроки поставки
   - Наименования товаров с артикулами
   → Анализируй и сопоставляй с кандидатами

4. ОТКАЗ / НЕТ В НАЛИЧИИ:
   - "К сожалению, не поставляем"
   - "Нет в наличии"
   - "Не работаем с данным брендом"
   → Можно идентифицировать, если понятно о каком товаре речь

=== ШАГ 1: АНАЛИЗ ВЛОЖЕНИЙ ===

Если есть приложенные документы, СНАЧАЛА определи их тип:

❌ ИГНОРИРУЙ маркетинговые материалы:
- Презентации "О компании", "Наши услуги", "Наши клиенты"
- Общие каталоги без цен
- Сертификаты, лицензии
- Реквизиты компании

✅ АНАЛИЗИРУЙ только релевантные документы:
- КП с конкретными ценами и товарами
- Счета
- Спецификации на конкретные позиции

=== ШАГ 2: ТОКЕН ===

Система УЖЕ проверила наличие токена в письме ПЕРЕД тобой.
- Если кандидат помечен "⭐ СОВПАДЕНИЕ ПО ТОКЕНУ" — токен найден
- Если метки НЕТ ни у одного кандидата — токен в письме ОТСУТСТВУЕТ
- НЕ ВЫДУМЫВАЙ совпадение токена! Если метки ⭐ нет — значит токена нет

=== ШАГ 3: СОПОСТАВЛЕНИЕ ПО НАЗВАНИЮ ===

Ищи совпадение НАЗВАНИЯ товара из письма с НАЗВАНИЕМ товара у кандидатов.
Совпадение = 2-3+ ключевых слова ТИПА товара совпадают.

Примеры совпадений:
  ✅ "Плата RS-14" ↔ "Плата RS-14 GAA25005B1" (совпадает!)
  ✅ "Плата удаленной станции RS-14" ↔ "Плата RS-14" (совпадает!)
  ✅ "Датчик шахтной информации" ↔ "Фотоэлектрический датчик шахтной информации" (совпадает!)
  ✅ "Частотный преобразователь KDL16L" ↔ "Частотный преобразователь Kone KDL16L" (совпадает!)
  ❌ "Плата RS-14" ↔ "Мотор-редуктор OTIS" (НЕ совпадает — разные типы!)
  ❌ "Трансформатор" ↔ "Плата RS-14" (НЕ совпадает!)

=== КРИТЕРИИ УВЕРЕННОСТИ ===
- 0.9-1.0: Метка ⭐ + название товара совпадает
- 0.7-0.9: Название товара точно совпадает (тип + модель)
- 0.5-0.7: Название частично совпадает (только тип товара)
- 0.0: Автоответ, презентация, нет совпадений → верни null

=== ФОРМАТ ОТВЕТА (СТРОГО JSON) ===
{
  "identified_queue_id": <number или null>,
  "confidence": <0.0-1.0>,
  "reasoning": "<тип письма + какое НАЗВАНИЕ товара из письма совпало с каким товаром кандидата>",
  "matched_items": ["<название товара из письма>"],
  "is_price_offer": <true если есть цены, иначе false>
}
PROMPT;
    }
}
