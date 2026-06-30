<?php

namespace App\Services\Generate;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Порт n8n-узлов «Get Email Template» + «Get Email Tone» + «Prepare AI Prompt» +
 * «AI Agent» + «Parse AI Response».
 *
 * Генерит ОДНО тело письма на батч (greeting/introduction/closing) для всех
 * поставщиков. Стиль письма (шаблон/тон) привязан к отправителю
 * (senders.preferred_template_id → email_templates → email_tones) —
 * АНТИ-ФИНГЕРПРИНТИНГ. Модель выше качеством (gpt-4o, temp 0.7), 1 AI-вызов
 * на батч (не на поставщика). Загруженные шаблон/тон сохраняются в батче для
 * последующего рендера (CampaignEmailBuilder).
 */
class CampaignBodyGenerator
{
    private const CONN = 'reports';

    public function __construct(
        private readonly OpenAIClassifierClient $ai,
        private readonly string $bodyModel,
        private readonly float $temperature,
        private readonly int $maxTokens,
    ) {
    }

    /**
     * @return array<string,string> ['greeting'=>..,'introduction'=>..,'closing'=>..]
     */
    public function generate(Batch $batch): array
    {
        $senderId = (int) ($batch->sender['id'] ?? 0);
        $template = $this->loadEmailTemplate($senderId);
        $tone = $this->loadEmailTone($template['ai_tone'] ?? null);

        $batch->emailTemplate = $template;
        $batch->emailTone = $tone;

        [$system, $user] = $this->buildPrompts($batch, $template ?? [], $tone ?? []);

        $body = $this->fallbackBody();
        try {
            if ($this->ai->isConfigured()) {
                $parsed = $this->ai->jsonCompletion($this->bodyModel, $system, $user, $this->maxTokens, $this->temperature);
                $body = $this->normalizeBody($parsed);
            }
        } catch (\Throwable $e) {
            Log::warning('CampaignBodyGenerator: AI body failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
        }

        $batch->aiBody = $body;
        $batch->aiModel = $this->bodyModel;
        return $body;
    }

    /**
     * Порт «Get Email Template».
     *
     * @return array<string,mixed>|null
     */
    private function loadEmailTemplate(int $senderId): ?array
    {
        if ($senderId <= 0) {
            return null;
        }
        $row = DB::connection(self::CONN)->selectOne(
            'SELECT et.id, et.name, et.description, et.subject_template, et.blocks,
                    et.signature_format, et.items_format, et.items_display_config,
                    et.style_preset, et.ai_tone
             FROM email_templates et
             JOIN senders s ON s.preferred_template_id = et.id
             WHERE s.id = ? AND et.is_active = 1
             LIMIT 1',
            [$senderId]
        );
        return $row ? (array) $row : null;
    }

    /**
     * Порт «Get Email Tone».
     *
     * @return array<string,mixed>|null
     */
    private function loadEmailTone(mixed $aiTone): ?array
    {
        if (empty($aiTone)) {
            return null;
        }
        $row = DB::connection(self::CONN)->selectOne(
            'SELECT code, name, description, example_greeting, example_introduction,
                    example_closing, allowed_greetings, intro_sentences_min,
                    intro_sentences_max, closing_sentences_min, closing_sentences_max
             FROM email_tones
             WHERE code = ? AND is_active = TRUE
             LIMIT 1',
            [(string) $aiTone]
        );
        return $row ? (array) $row : null;
    }

    /**
     * Порт «Prepare AI Prompt»: строит systemMessage + userPrompt.
     *
     * @param array<string,mixed> $template
     * @param array<string,mixed> $tone
     * @return array{0:string,1:string}
     */
    private function buildPrompts(Batch $batch, array $template, array $tone): array
    {
        $sender = $batch->sender ?? [];
        $senderName = $sender['sender_name'] ?? '';
        $senderEmail = $sender['email'] ?? '';

        $items = $batch->items;
        $isCustomer = $batch->isCustomerRequest;

        // Список товаров для промпта.
        $itemsLines = [];
        foreach ($items as $i => $item) {
            $name = $item['name'] ?? '';
            $brand = !empty($item['brand']) ? ' ' . $item['brand'] : '';
            $article = !empty($item['article']) ? ' (арт. ' . $item['article'] . ')' : '';
            $quantity = $item['quantity'] ?? '';
            $unit = $item['unit'] ?? '';
            $desc = !empty($item['description']) ? "\n   " . $item['description'] : '';
            $itemsLines[] = ($i + 1) . ". {$name}{$brand}{$article}, {$quantity} {$unit}{$desc}";
        }
        $itemsList = implode("\n", $itemsLines);

        $templateName = $template['name'] ?? 'Стандартный';
        $aiTone = $template['ai_tone'] ?? 'neutral';
        $stylePreset = $template['style_preset'] ?? 'professional';

        $toneDescription = $tone['description'] ?? 'Стандартная деловая переписка';
        $toneName = $tone['name'] ?? 'Нейтральный';
        $allowedGreetings = $tone['allowed_greetings'] ?? 'Добрый день,|Добрый день.';
        $introMin = $tone['intro_sentences_min'] ?? 2;
        $introMax = $tone['intro_sentences_max'] ?? 4;
        $closingMin = $tone['closing_sentences_min'] ?? 2;
        $closingMax = $tone['closing_sentences_max'] ?? 3;

        $toneExample = json_encode([
            'greeting' => $tone['example_greeting'] ?? 'Добрый день,',
            'introduction' => $tone['example_introduction'] ?? 'Прошу предоставить коммерческое предложение на указанные позиции.',
            'closing' => $tone['example_closing'] ?? 'Укажите цены и сроки. Жду ответа.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $greetingsList = implode(' / ', array_map(
            static fn ($g) => '"' . $g . '"',
            explode('|', (string) $allowedGreetings)
        ));

        $globalProhibitions = <<<TXT
КАТЕГОРИЧЕСКИ ЗАПРЕЩЕНО (для ЛЮБОГО тона):
- Приветствия: "Хей", "Йо", "Здарова", "Приветик", "Привет"
- Жаргон, сленг, мемы
- Фамильярность и панибратство
- Несколько восклицательных/вопросительных знаков подряд (!!, !!!, ??)
- Выдумывать фамилию или отчество отправителя
- Перечислять товары в тексте (они будут в таблице)
- Добавлять подпись (она добавляется автоматически)
TXT;

        $customerBlock = '';
        if ($isCustomer) {
            $customerBlock = "══ ИМЕННАЯ ЗАЯВКА ══\n"
                . "Обращаемся от имени заказчика:\n"
                . '- Компания: ' . ($batch->customerCompany ?: 'Не указана') . "\n"
                . '- Контактное лицо: ' . ($batch->customerContactPerson ?: 'Не указано') . "\n"
                . '- Email: ' . ($batch->customerEmail ?: 'Не указан') . "\n"
                . '- Телефон: ' . ($batch->customerPhone ?: 'Не указан') . "\n";
        }

        $introCustomer = $isCustomer ? '- ОБЯЗАТЕЛЬНО укажи заказчика: ' . $batch->customerCompany : '';
        $closingCustomer = $isCustomer
            ? '- Ответ направить заказчику: ' . $batch->customerEmail
            : '- Просьба ответить';

        $toneUpper = mb_strtoupper((string) $toneName);
        $itemsCount = count($items);

        $systemMessage = <<<TXT
Ты эксперт по составлению деловых писем для запроса коммерческих предложений.

═══════════════════════════════════════
ШАБЛОН: "{$templateName}"
ТРЕБУЕМЫЙ ТОН: {$toneUpper}
═══════════════════════════════════════

{$toneDescription}

═══════════════════════════════════════
ВАЖНО: ЭТО ДЕЛОВАЯ ПЕРЕПИСКА
═══════════════════════════════════════
Независимо от выбранного тона, письмо ОБЯЗАТЕЛЬНО должно быть:
- Грамотным (без ошибок)
- Уважительным
- Профессиональным

{$globalProhibitions}

ДАННЫЕ ОТПРАВИТЕЛЯ:
- Имя: {$senderName}
- Email: {$senderEmail}

{$customerBlock}

ТОВАРЫ ({$itemsCount} поз.):
{$itemsList}

═══════════════════════════════════════
ЗАДАЧА
═══════════════════════════════════════

Сгенерируй JSON:
{
  "greeting": "Приветствие",
  "introduction": "Вступление ({$introMin}-{$introMax} предложения)",
  "closing": "Заключение ({$closingMin}-{$closingMax} предложения)",
  "found_intro": "1 короткое предложение"
}

ТРЕБОВАНИЯ:

1. greeting:
   - Приветствие в стиле "{$toneName}"
   - Без имени получателя
   - Допустимые варианты: {$greetingsList}

2. introduction:
   - Цель письма — запрос КП
   {$introCustomer}
   - БЕЗ перечисления товаров (будут в таблице)
   - {$introMin}-{$introMax} предложения

3. closing:
   - Что нужно в КП: цена, сроки, условия
   {$closingCustomer}
   - БЕЗ подписи (она добавляется отдельно)
   - {$closingMin}-{$closingMax} предложения

4. found_intro (мягкий намёк к ссылкам):
   - 1 предложение в тоне "{$toneName}": мы заметили, что часть позиций, похоже,
     представлена у вас на сайте, и будем рады, если поможете проработать всю заявку
   - БЕЗ самих ссылок (они подставятся отдельной строкой для каждого поставщика)
   - Мягко, как ПРЕДПОЛОЖЕНИЕ, НЕ как утверждение/требование

═══════════════════════════════════════
УНИКАЛЬНОСТЬ
═══════════════════════════════════════
- Каждое письмо должно быть УНИКАЛЬНЫМ
- НЕ копируй пример дословно
- Используй РАЗНЫЕ формулировки
- Сохраняй заданный тон, но варьируй слова
- Представь что пишешь реальному поставщику

ПРИМЕР для тона "{$toneName}":
{$toneExample}

КРИТИЧЕСКИЕ ТРЕБОВАНИЯ:
- Только валидный JSON
- БЕЗ markdown (```json)
- Русский язык
- БЕЗ HTML
- БЕЗ списка товаров в тексте
- БЕЗ подписи
TXT;

        $userPrompt = <<<TXT
Сгенерируй УНИКАЛЬНЫЙ JSON для письма-запроса КП.
Тон: {$toneName}
Шаблон: {$templateName}

ВАЖНО:
- НЕ копируй пример — создай свой вариант
- Сохрани тон "{$toneName}", но используй другие слова
- Это ДЕЛОВОЕ письмо — будь профессионален
- Верни ТОЛЬКО JSON
TXT;

        // $stylePreset/$aiTone сохраняются в emailTemplate; здесь — только промпты.
        unset($stylePreset, $aiTone);

        return [$systemMessage, $userPrompt];
    }

    /**
     * Порт проверок «Parse AI Response» поверх уже распарсенного JSON.
     *
     * @param array<string,mixed> $parsed
     * @return array<string,string>
     */
    private function normalizeBody(array $parsed): array
    {
        $greeting = isset($parsed['greeting']) ? trim((string) $parsed['greeting']) : '';
        $introduction = isset($parsed['introduction']) ? trim((string) $parsed['introduction']) : '';
        $closing = isset($parsed['closing']) ? trim((string) $parsed['closing']) : '';
        $foundIntro = isset($parsed['found_intro']) ? trim((string) $parsed['found_intro']) : '';

        if ($greeting === '' || mb_strlen($greeting) < 3) {
            $greeting = 'Добрый день,';
        }
        if ($introduction === '' || mb_strlen($introduction) < 10) {
            $introduction = 'Прошу предоставить коммерческое предложение на следующие товары:';
        }
        if ($closing === '' || mb_strlen($closing) < 10) {
            $closing = 'Буду признателен за оперативный ответ на это письмо.';
        }
        if ($foundIntro === '' || mb_strlen($foundIntro) < 10) {
            $foundIntro = 'Кажется, часть позиций представлена у вас на сайте — будем рады, если поможете проработать всю заявку.';
        }

        return [
            'greeting' => $greeting,
            'introduction' => $introduction,
            'closing' => $closing,
            'found_intro' => $foundIntro,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function fallbackBody(): array
    {
        return [
            'greeting' => 'Добрый день,',
            'introduction' => 'Прошу предоставить коммерческое предложение на следующие товары:',
            'closing' => 'Буду признателен за оперативный ответ на это письмо.',
            'found_intro' => 'Кажется, часть позиций представлена у вас на сайте — будем рады, если поможете проработать всю заявку.',
        ];
    }
}
