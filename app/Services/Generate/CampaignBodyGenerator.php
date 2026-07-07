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

ЗАПРЕЩЁННЫЕ ШТАМПЫ И КАНЦЕЛЯРИТ (пиши как живой снабженец, а не бланк). Запрещены
НЕ только эти фразы, но и ЛЮБЫЕ их варианты/синонимы — это ПАТТЕРНЫ, а не точный список:
- Казённые зачины: "Обращаемся с просьбой/с запросом", "Настоящим письмом", "Доводим до
  вашего сведения", "Просим рассмотреть возможность". Начинай по-человечески и по делу.
- Штампы про сотрудничество: ЛЮБЫЕ "надеемся на … сотрудничество" (плодотворное/
  долгосрочное/взаимовыгодное), "информация о возможностях сотрудничества".
- Штампы-ожидания в конце: "с нетерпением ждём", "надеемся на скорый/быстрый ответ",
  "ждём вашего ответа", "в кратчайшие сроки", "заранее благодарим за понимание".
  ЗАКАНЧИВАЙ ПРОСТО И ПО-ДЕЛОВОМУ — без «надежд», «ожиданий» и благодарностей авансом.
- Пустые общие слова вместо сути: "оборудование"/"продукция"/"товары" там, где можно
  назвать ЧТО именно запрашивается (тип/назначение из списка товаров выше).
- Повторять просьбу о цене/сроках/условиях более одного раза (это только в closing).
- Канцелярские зачины вступления: "Нам требуется", "Требуется КП", "Направляем запрос",
  "из списка ниже"/"из перечня ниже" как обязательный хвост. Пиши по-живому.
- Канцелярские просьбы в concовке: "Просим указать", "Просим сообщить", "Прошу
  предоставить информацию". Лучше: "Подскажите…", "Сообщите…", "Напишите…", "Сколько…".
- Хвосты про ответ: "ответьте в ближайшее время", "жду оперативного ответа",
  "просьба ответить оперативно". Заканчивай на самой просьбе о цене/сроках — без хвоста.
TXT;

        // Few-shot контраст: показать РАЗНИЦУ, а не только запреты. Это работает сильнее.
        $howto = <<<TXT
КАК НЕ НАДО (казённо, как бланк) → КАК НАДО (как живой снабженец быстро пишет):
✗ "Нам требуется коммерческое предложение по лифтовым запчастям из списка ниже."
✓ "Собираем цены по лифтовым запчастям — позиции ниже."
✗ "Просим указать условия оплаты, цену и сроки поставки. Пожалуйста, ответьте в ближайшее время."
✓ "Подскажите, пожалуйста, цену, сроки и условия оплаты."
✗ "Обращаемся с просьбой рассмотреть возможность поставки оборудования."
✓ "Нужны вот эти позиции — что можете предложить?"
Пиши КОРОТКО, по делу, живыми словами. Одно-два предложения лучше, чем четыре казённых.
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

        // Структурный «угол» захода — СТАБИЛЕН per-sender (у одного ящика письма
        // похожи → анти-фингерпринт), но РАЗНЫЙ между отправителями. Поставщик получает
        // до ~30 писем/день от разных ящиков; чтобы они не были на один скелет, каждый
        // ящик ведёт письмо по-своему. Комбинируется с тоном (8 тонов × 6 углов).
        $angles = [
            'ЗАХОД: сразу к делу — короткая просьба о КП без вводных.',
            'ЗАХОД: одно предложение контекста (планируем закупку / комплектуем объект / подбираем поставщика), затем просьба.',
            'ЗАХОД: начни с вопроса («Сможете подобрать?» / «Поможете с поставкой?»), дальше по делу.',
            'ЗАХОД: очень лаконично — минимум слов, только суть.',
            'ЗАХОД: чуть теплее, будто пишешь знакомому поставщику, но по-деловому.',
            'ЗАХОД: деловито и структурно, но живым языком.',
        ];
        $angle = $angles[((int) ($sender['id'] ?? 0)) % count($angles)];

        // found_intro осведомлён о числе позиций: при ОДНОЙ позиции нельзя «всю заявку»,
        // «остальные/часть позиций», «по всем» — их нет, звучит абсурдно.
        $foundIntroExamples = $itemsCount <= 1
            ? "В ЗАЯВКЕ ОДНА позиция → пиши в ЕДИНСТВЕННОМ числе ПРО НЕЁ. НЕЛЬЗЯ «всю заявку»,\n"
                . "     «остальные/некоторые/часть позиций», «по всем», «заявку целиком» — их НЕТ. Примеры:\n"
                . "       • «Похоже, эта позиция у вас есть — подскажете по ней?»\n"
                . "       • «Кажется, это у вас найдётся — сориентируете по цене?»\n"
                . "       • «Это, вроде, ваше — гляньте, пожалуйста.»"
            : "Примеры РАЗНЫХ подходов (НЕ копируй дословно, придумай своё в тоне):\n"
                . "       • «Часть, кажется, у вас есть — гляньте, пожалуйста, и по остальному.»\n"
                . "       • «Кое-что из списка вроде ваше. Поможете с полной заявкой?»\n"
                . "       • «Пару позиций нашёл у вас — буду рад предложению по всем.»\n"
                . "       • «Это, похоже, найдётся у вас — посмотрите заявку целиком?»";

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

{$howto}

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

⚡ РАЗНООБРАЗИЕ ЭТОГО ПИСЬМА (важно): {$angle}

ТРЕБОВАНИЯ:

1. greeting:
   - Приветствие в стиле "{$toneName}"
   - Без имени получателя
   - Допустимые варианты: {$greetingsList}

2. introduction:
   - Цель письма — запрос КП. Пиши живо и по делу, как реальный снабженец коллеге.
   {$introCustomer}
   - Можно ОДНОЙ фразой обозначить, ЧТО за позиции запрашиваешь (тип/назначение из
     списка товаров выше, напр. "по лифтовым запчастям", "на комплектующие ОТИС"), но
     БЕЗ перечисления самих товаров (они в таблице) и БЕЗ артикулов
   - Без штампов и «воды», без общих слов "оборудование/сотрудничество"
   - {$introMin}-{$introMax} предложения

3. closing:
   - ЕДИНСТВЕННОЕ место, где просишь, что указать в КП: цену, сроки поставки, условия
     оплаты — ОДНОЙ естественной фразой (не списком, не по пунктам)
   {$closingCustomer}
   - Живое человеческое завершение без штампов ("с нетерпением ждём" — нельзя)
   - Варьируй формулировку — НЕ всегда «напишите цену, сроки и условия оплаты». Примеры
     РАЗНЫХ (придумай своё в тоне): «Сориентируйте по цене и срокам, пожалуйста.» /
     «Сколько выйдет и когда сможете отгрузить?» / «Дайте, пожалуйста, стоимость, срок и
     как по оплате.» / «Интересует цена, сроки и условия.»
   - БЕЗ подписи (она добавляется отдельно)
   - {$closingMin}-{$closingMax} предложения

4. found_intro (короткая подводка к ссылкам ниже — ОДНО предложение):
   - Смысл: под этой фразой пойдут ссылки на позиции, которые, ПОХОЖЕ, есть на сайте
     получателя. Мягко (как предположение, не утверждение) подведи к ним и попроси
     помочь с заявкой. БЕЗ самих ссылок (подставятся отдельно).
   - ⛔ НЕ ПОД КОПИРКУ! ЗАПРЕЩЕНЫ штампованные зачины «Обратили внимание, что…»,
     «Мы заметили, что…», «Похоже, некоторые позиции…» и штампованные хвосты «будем
     признательны/рады за помощь в обработке/проработке (всей) заявки» — именно из-за
     них все письма выходят одинаковыми.
   - Варьируй ЗАЧИН, длину и структуру, пиши живо и по-разному в тоне "{$toneName}".
     {$foundIntroExamples}

═══════════════════════════════════════
УНИКАЛЬНОСТЬ (КРИТИЧНО)
═══════════════════════════════════════
Поставщик получает ДО 30 таких писем в день от РАЗНЫХ фирм. Если они на один скелет —
рассылка палится как шаблонная. Сделай ИМЕННО ЭТО письмо непохожим на остальные:
- варьируй не только слова, но и СТРУКТУРУ, ДЛИНУ и порядок мыслей (следуй заходу выше);
- иногда 2 коротких фразы, иногда чуть развёрнутее — по тону;
- держи тон, но НЕ копируй пример дословно — пиши своими словами;
- живой снабженец печатает быстро и по-своему, каждый раз иначе.

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

        // Санитайзер концовки: gpt-4o упорно добавляет хвост-вежливость («ждём вашего
        // ответа», «заранее благодарим за сотрудничество») даже при запрете в промпте.
        // Детерминированно срезаем ХВОСТОВЫЕ предложения-вежливости про ответ/сотрудничество,
        // если в них НЕТ сути (цена/сроки/оплата) — сама просьба (с сутью) сохраняется.
        $closing = $this->stripPolitenessTail($closing);

        if ($greeting === '' || mb_strlen($greeting) < 3) {
            $greeting = 'Добрый день,';
        }
        if ($introduction === '' || mb_strlen($introduction) < 10) {
            $introduction = 'Собираем цены по позициям ниже — что можете предложить?';
        }
        if ($closing === '' || mb_strlen($closing) < 10) {
            $closing = 'Подскажите, пожалуйста, цену, сроки и условия оплаты.';
        }
        if ($foundIntro === '' || mb_strlen($foundIntro) < 10) {
            $foundIntro = 'Часть, кажется, у вас есть — гляньте, пожалуйста, и по остальному.';
        }

        return [
            'greeting' => $greeting,
            'introduction' => $introduction,
            'closing' => $closing,
            'found_intro' => $foundIntro,
        ];
    }

    /**
     * Срезает хвостовые предложения-вежливости концовки («Ждём вашего ответа»,
     * «Заранее благодарим за сотрудничество», «Будем признательны за быстрый ответ»),
     * оставляя суть-просьбу. Дропает предложение с конца, только если оно похоже на
     * politeness-штамп И не содержит сути (цена/срок/оплата/условия) — сама просьба
     * с сутью не трогается. Всегда оставляет минимум одно предложение.
     */
    private function stripPolitenessTail(string $closing): string
    {
        $closing = trim($closing);
        if ($closing === '') {
            return '';
        }

        $politeness = '/(с\s+нетерпением|жд[ёеу]м?\b|ожида[ею]\w*|наде[ёе]мся|надеюсь|рассчитыва[ею]\w*|заранее\s+благодар\w+|благодар\w+\s+за|призна\w+\s+за|спасибо|ответьте|ответ\w*\s+в\s+ближайш|в\s+ближайшее\s+время|в\s+кратчайш\w+|операт\w+)/iu';
        $substance = '/(цен|стоимост|сумм|срок|услов|оплат|доставк|наличи|прайс)/iu';

        $sentences = preg_split('/(?<=[.!?])\s+/u', $closing, -1, PREG_SPLIT_NO_EMPTY) ?: [$closing];

        while (count($sentences) > 1) {
            $last = trim((string) end($sentences));
            if (preg_match($politeness, $last) && !preg_match($substance, $last)) {
                array_pop($sentences);
                continue;
            }
            break;
        }

        return trim(implode(' ', $sentences));
    }

    /**
     * @return array<string,string>
     */
    private function fallbackBody(): array
    {
        return [
            'greeting' => 'Добрый день,',
            'introduction' => 'Собираем цены по позициям ниже — что можете предложить?',
            'closing' => 'Подскажите, пожалуйста, цену, сроки и условия оплаты.',
            'found_intro' => 'Часть, кажется, у вас есть — гляньте, пожалуйста, и по остальному.',
        ];
    }
}
