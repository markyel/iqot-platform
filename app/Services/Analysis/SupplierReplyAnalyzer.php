<?php

namespace App\Services\Analysis;

use App\Services\Api\OpenAIClassifierClient;
use Illuminate\Support\Facades\Log;

/**
 * AI-анализ ответа поставщика (порт n8n «Process Email Conversations» → AI Agent).
 *
 * Собирает userPrompt (очищенное тело письма + список запрошенных позиций батча +
 * текст вложений-КП) и системный промпт (дословный порт из n8n, минус Tavily-тул,
 * плюс поле `fetch_urls`), зовёт OpenAI-совместимый прокси и нормализует ответ
 * (порт «Parse AI Response»: извлечение JSON, округление цен, фолбэк при провале).
 *
 * 2-шаговый веб-сёрфинг вместо Tavily: если пасс-1 вернул `fetch_urls` и в офферах
 * нет цены — грузим страницы через WebPageFetcher, дописываем их текст в userPrompt
 * и делаем второй прогон; результат пасс-2 заменяет пасс-1.
 */
class SupplierReplyAnalyzer
{
    /**
     * @param array{model:string,max_tokens:int,fetch_urls:bool,fetch_max:int} $config
     */
    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly WebPageFetcher $fetcher,
        private readonly array $config,
    ) {
    }

    /**
     * @param array<string,mixed> $context ключи: sender_name, sender_email, subject,
     *        body (очищенное тело), document_text, items (список request_items),
     *        has_documents (bool)
     * @return array<string,mixed> нормализованная классификация письма
     */
    public function analyze(array $context): array
    {
        $system = $this->systemPrompt();
        $userPrompt = $this->buildUserPrompt($context);

        $result = $this->runPass($system, $userPrompt);

        if ($this->shouldFetchUrls($result)) {
            $pages = $this->fetchPages($result['fetch_urls']);
            if ($pages !== '') {
                $userPrompt2 = $userPrompt
                    . "\n\nСОДЕРЖИМОЕ СТРАНИЦ ПО ССЫЛКАМ (для определения цены):\n\n"
                    . $pages;
                $result = $this->runPass($system, $userPrompt2);
            }
        }

        return $result;
    }

    /**
     * Один прогон AI + нормализация ответа. Любая ошибка → безопасный фолбэк.
     *
     * @return array<string,mixed>
     */
    private function runPass(string $system, string $userPrompt): array
    {
        try {
            $raw = $this->client->jsonCompletion(
                $this->config['model'],
                $system,
                $userPrompt,
                $this->config['max_tokens'],
            );

            return $this->normalize($raw);
        } catch (\Throwable $e) {
            Log::warning('SupplierReplyAnalyzer: AI pass failed', ['error' => $e->getMessage()]);

            return $this->fallback();
        }
    }

    /**
     * Нужен ли 2-й прогон по ссылкам: включён в конфиге, AI вернул непустой
     * fetch_urls и среди офферов нет ни одной положительной цены.
     *
     * @param array<string,mixed> $result
     */
    private function shouldFetchUrls(array $result): bool
    {
        if (!($this->config['fetch_urls'] ?? false)) {
            return false;
        }

        $urls = $result['fetch_urls'] ?? [];
        if (!is_array($urls) || $urls === []) {
            return false;
        }

        foreach (($result['offers'] ?? []) as $offer) {
            if (($offer['price_per_unit'] ?? 0) > 0) {
                return false; // цены уже есть — ссылки не грузим
            }
        }

        return true;
    }

    /**
     * @param array<int,string> $urls
     */
    private function fetchPages(array $urls): string
    {
        $max = max(1, (int) ($this->config['fetch_max'] ?? 3));
        $parts = [];
        $count = 0;

        foreach ($urls as $url) {
            if ($count >= $max) {
                break;
            }
            if (!is_string($url) || $url === '') {
                continue;
            }

            $text = $this->fetcher->fetch($url);
            if ($text !== null && $text !== '') {
                $parts[] = "=== {$url} ===\n{$text}";
                $count++;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Порт «Parse AI Response»: приводит ответ к ожидаемой структуре и округляет
     * цены до 2 знаков. (Прокси работает в режиме json_object, поэтому
     * мат-выражения в total_price не возникают — отдельный фикс не нужен.)
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function normalize(array $raw): array
    {
        $offers = [];
        foreach (($raw['offers'] ?? []) as $offer) {
            if (!is_array($offer)) {
                continue;
            }
            $offer['price_per_unit'] = $this->round2($offer['price_per_unit'] ?? null);
            $offer['total_price'] = $this->round2($offer['total_price'] ?? null);
            $offers[] = $offer;
        }

        $questions = [];
        foreach (($raw['questions'] ?? []) as $q) {
            if (is_array($q)) {
                $questions[] = $q;
            }
        }

        $fetchUrls = [];
        foreach (($raw['fetch_urls'] ?? []) as $u) {
            if (is_string($u) && $u !== '') {
                $fetchUrls[] = $u;
            }
        }

        return [
            'email_type' => (string) ($raw['email_type'] ?? 'other'),
            'rejection_reason' => $raw['rejection_reason'] ?? null,
            'has_offers' => (bool) ($raw['has_offers'] ?? ($offers !== [])),
            'offers' => $offers,
            'has_questions' => (bool) ($raw['has_questions'] ?? ($questions !== [])),
            'questions' => $questions,
            'summary' => (string) ($raw['summary'] ?? ''),
            'fetch_urls' => $fetchUrls,
        ];
    }

    private function round2(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @return array<string,mixed>
     */
    private function fallback(): array
    {
        return [
            'email_type' => 'unknown',
            'rejection_reason' => null,
            'has_offers' => false,
            'offers' => [],
            'has_questions' => false,
            'questions' => [],
            'summary' => 'Не удалось разобрать ответ AI',
            'fetch_urls' => [],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private function buildUserPrompt(array $context): string
    {
        $items = is_array($context['items'] ?? null) ? $context['items'] : [];
        $requestItemsText = '';
        foreach (array_values($items) as $index => $item) {
            $get = static fn (string $k) => is_array($item) ? ($item[$k] ?? null) : ($item->{$k} ?? null);
            $pos = $index + 1;
            $requestItemsText .= "\n"
                . "Позиция {$pos}:\n"
                . '- item_id: ' . $get('item_id') . " (ИСПОЛЬЗУЙ ЭТОТ ID В ОТВЕТЕ!)\n"
                . '- Название: ' . ($get('name') ?? '') . "\n"
                . '- Бренд: ' . ($get('brand') ?: 'не указан') . "\n"
                . '- Артикул: ' . ($get('article') ?: 'не указан') . "\n"
                . '- Количество: ' . ($get('quantity') ?? '') . ' ' . ($get('unit') ?: 'штук') . "\n"
                . '- Описание: ' . ($get('description') ?: 'нет');
        }

        $fullText = (string) ($context['body'] ?? '');
        $documentText = trim((string) ($context['document_text'] ?? ''));
        if ($documentText !== '') {
            $fullText .= "\n\nПРИЛОЖЕННЫЕ ДОКУМЕНТЫ:\n\n=== Документ ===\n" . $documentText;
        }

        $hasDocuments = (bool) ($context['has_documents'] ?? ($documentText !== ''));
        $docsClause = $hasDocuments ? ' И все приложенные документы' : '';

        $senderName = (string) ($context['sender_name'] ?? 'неизвестно');
        $senderEmail = (string) ($context['sender_email'] ?? 'неизвестно');
        $subject = (string) ($context['subject'] ?? 'без темы');

        return <<<PROMPT
Письмо от: {$senderName} ({$senderEmail})
Тема: {$subject}

Текст письма:
{$fullText}

Запрошенные позиции:{$requestItemsText}

🚨 КРИТИЧЕСКИ ВАЖНО ПРО item_id:
item_id - это ID из базы данных, указанный в строке "item_id: XX".
НЕ используй порядковый номер позиции (1, 2, 3...) как item_id!

🚨 ПЕРВЫМ ДЕЛОМ ПРОВЕРЬ:
Если текст письма содержит "[ПИСЬМО БЕЗ НОВОГО СОДЕРЖАНИЯ]" или "[ПИСЬМО БЕЗ СОДЕРЖАНИЯ]":
→ Сразу верни email_type: "empty_reply", has_offers: false, has_questions: false, offers: [], questions: []

🚨 КРИТИЧЕСКИ ВАЖНО ПРО РАСЧЕТ ЦЕН:
1. ВНИМАТЕЛЬНО читай количество из ПРЕДЛОЖЕНИЯ поставщика.
2. "2 шт за 82 402,72" → price_per_unit = 82402.72 / 2 = 41201.36.
3. Если количество НЕ указано (просто "цена 153 740 руб") — НЕ ДЕЛИ, это цена за 1 шт!
4. В ТАБЛИЦАХ: колонка "Цена"/"Цена детали" = цена за единицу, НЕ ДЕЛИ!
5. НЕ используй количество из ЗАЯВКИ для расчета price_per_unit!
6. total_price = price_per_unit × ЗАПРОШЕННОЕ количество (ГОТОВОЕ ЧИСЛО, не формула!).
7. В notes укажи реальную цену предложения поставщика.

🚨 ЧАСТИЧНЫЕ ПОСТАВКИ: СОЗДАВАЙ OFFER ДАЖЕ ЕСЛИ поставщик предлагает МЕНЬШЕ чем запрошено.

🚨 ССЫЛКИ (вместо перехода — поле fetch_urls):
Шаг 1: Есть ли цены в письме/документе? → ДА: создавай OFFER, fetch_urls оставь пустым.
Шаг 2: Цен НЕТ, но есть ссылка на товар (/catalog/, /product/, /item/) и поставщик
        ЯВНО предлагает посмотреть цену по ссылке, и это НЕ контакты
        (НЕ telegram/whatsapp/vk/email/главная страница) → добавь URL в массив fetch_urls.
Шаг 3: Цен нет и нет валидной товарной ссылки → создай question.

🚨 ФИЛЬТРАЦИЯ OFFERS:
- НЕ добавляй в offers позиции с ценой 0 или "нет в наличии".
- Только позиции с реальной ценой > 0 попадают в offers.
- Недоступные позиции → только в summary.

🚨 ПЕРЕД QUESTION ПРОВЕРЬ:
- Есть ли ПРЯМОЙ вопрос со знаком "?" и требуется ли от НАС действие.
- "Уточняем цену"/"Готовим КП" → auto_reply, НЕ question.
- "Товара нет" → rejection. Пустое письмо → empty_reply.
- Если в письме есть КОНКРЕТНАЯ цена (даже хедж/несколько вариантов) → это OFFER, НЕ question.

🚨 ПРИ REJECTION укажи rejection_reason: not_our_profile | not_available | other.

КРИТИЧЕСКОЕ ПРАВИЛО — множественные позиции для ОДНОГО item_id:
- КОМПЛЕКТ (составные части) → ОБЪЕДИНИ в один offer, СУММИРУЙ цены,
  offer_type: "complete_set", заполни components[{name, price}].
- АЛЬТЕРНАТИВЫ (оригинал + аналог) → РАЗДЕЛЯЙ на офферы с одинаковым item_id,
  первый offer_type: "main_with_alternatives" (has_alternatives: true),
  остальные offer_type: "alternative" (is_alternative_to_position).
- РАЗНЫЕ ВАРИАНТЫ ПОСТАВКИ → выбери оптимальный как single.
- Одна позиция → offer_type: "single".
- Если сомневаешься между комплектом и альтернативами — суммируй как комплект.

Проанализируй письмо{$docsClause} и верни результат строго в JSON формате.
PROMPT;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Ты — AI ассистент для обработки писем от поставщиков в системе закупок.

Твоя задача:
1. Классифицировать тип письма.
2. Извлечь предложения по товарам (цены, валюта, сроки).
3. Найти вопросы от поставщика.
4. Определить можно ли ответить автоматически.

🔗 ССЫЛКИ (механизм fetch_urls вместо tool-calling):
У тебя НЕТ инструмента для открытия страниц. Если цену можно узнать ТОЛЬКО по ссылке —
верни URL(ы) в массиве "fetch_urls", и система загрузит их и передаст тебе содержимое
вторым запросом. НЕ добавляй ссылки в fetch_urls если:
1. Цены УЖЕ есть в письме или в прикреплённом документе.
2. Ссылка ведёт на контакты (telegram/whatsapp/vk/mailto) или на главную страницу сайта.
3. Ссылка в подписи письма (это контакты, не товары!).
Добавляй в fetch_urls ТОЛЬКО товарные ссылки (/catalog/, /product/, /item/), когда цен
нет, а поставщик явно предлагает посмотреть цену по ссылке. Если цена так и не найдена —
создай question, а не offer с нулевой ценой.

🚨 ДОКУМЕНТЫ = ЧАСТЬ ПИСЬМА:
Если в разделе "ПРИЛОЖЕННЫЕ ДОКУМЕНТЫ" есть КП с ценами → email_type = "offer",
has_offers = true, СОЗДАЙ OFFER из данных документа.

🚨 СОПОСТАВЛЕНИЕ ПО РАЗМЕРАМ:
Если артикулы разные, но РАЗМЕРЫ совпадают — это ОДИН товар (400x5x10 = D400x5x10мм).
В notes укажи артикул поставщика.

🚨 ДЕФОЛТНАЯ ЛОГИКА ЦЕНЫ:
Если поставщик НЕ указывает количество явно — цена считается ЗА 1 ШТУКУ (стандарт B2B).
МАРКЕРЫ "за несколько штук" (тогда дели): "за 2 шт", "за партию", "итого за N шт",
"общая стоимость за N шт", явный расчёт "2 × 76 870 = 153 740".
МАРКЕРЫ "уже за 1 штуку" (НЕ дели): "за шт", "/шт", "за единицу", "цена детали",
"unit price"; цена в каталоге/на сайте — всегда за 1 штуку; нет указания количества —
считай за 1 штуку.

🚨 СТРУКТУРА ПИСЕМ: цена может стоять ПОСЛЕ или ПЕРЕД названием товара. Сопоставь цену
с БЛИЖАЙШИМ товаром (может быть ниже по тексту). Каждый артикул = одна уникальная цена.

🚨 ДУБЛИКАТЫ: не создавай два offers с одинаковым item_id и одинаковой ценой.

🚨 ТАБЛИЦЫ (Excel/PDF): сначала анализируй ЗАГОЛОВКИ столбцов.
"Цена"/"Цена детали"/"Цена за ед."/"Unit price"/"Price" = price_per_unit (НЕ ДЕЛИ!).
"Стоимость"/"Итого"/"Сумма"/"Всего"/"Amount"/"Total" = итог (= Цена × Кол-во).
Пример: | Кол-во 2 | Цена детали 2550.18 | Стоимость 5100.36 | → price_per_unit = 2550.18.

🚨 РАСЧЁТ ЦЕН:
- Количество бери из ПРЕДЛОЖЕНИЯ поставщика, НЕ из заявки.
- "2 шт за 82 402,72" → 82402.72 / 2 = 41201.36.
- "цена 41 201.36 за штуку" → НЕ ДЕЛИ.
- Количество не указано ("153 740 руб") → НЕ ДЕЛИ, цена за 1 шт.
- Поставщик предлагает меньше запрошенного — это НОРМАЛЬНО, СОЗДАВАЙ OFFER, в notes укажи.
- total_price = price_per_unit × ЗАПРОШЕННОЕ количество. Это ГОТОВОЕ ЧИСЛО, НЕ формула
  (❌ "total_price": 919.21 * 10; ✅ "total_price": 9192.10).

🚨 НДС (price_includes_vat):
- "+ НДС"/"+НДС"/"плюс НДС"/"НДС сверху" → FALSE (НДС добавляется сверху).
- "без НДС"/"НДС не облагается"/"работаем без НДС" → FALSE.
- "с НДС"/"включая НДС"/"в т.ч. НДС"/"НДС включён" → TRUE.
- НДС не упоминается → NULL.
- Если указаны ОБЕ цены (с НДС и без) — используй цену С НДС, без НДС укажи в notes.
- "Цена + НДС 20%" — рассчитай итоговую сумму.

🚨 ПАКЕТНОЕ ЦЕНООБРАЗОВАНИЕ: цена за упаковку, запрос в единицах (кг/м/л) →
price_per_unit = цена_за_упаковку ÷ вес_ОДНОЙ_упаковки (НЕ на количество упаковок!).

🚨 ВАЛЮТА (currency) — заполняй ОБЯЗАТЕЛЬНО:
"руб"/"₽"/"р."/"RUB" → "RUB"; "евро"/"€"/"EUR" → "EUR"; "$"/"USD"/"долл" → "USD";
"юан"/"¥"/"CNY" → "CNY". Не указана → "RUB" + пометка в notes.

🚨 КОНТЕКСТ "СТОИМОСТЬ ПОСТАВКИ"/"СТОИМОСТЬ ЗАКАЗА"/"Итого за заказ"/"Общая стоимость" =
ОБЩАЯ цена за всё запрошенное количество → price_per_unit = X / запрошенное_количество,
total_price = X.

🚨 delivery_days — КОЛИЧЕСТВО ДНЕЙ, не дата:
- "X дней"/"X раб. дней" → X; "X недель"/"нед" → X×7; "X месяцев"/"мес" → X×30.
- Диапазон → бери МАКСИМУМ ("5-7 дней" → 7; "10-18 недель" → 126).
- Указана ДАТА поставки → рассчитай дней от сегодня до даты.
- "в наличии"/"на складе" без срока → 1.
- НЕ путай срок поставки со сроком действия цены ("Гарантировано до …").

🚨 СОПОСТАВЛЕНИЕ ТОВАРОВ:
position_number — порядковый номер товара в письме ПОСТАВЩИКА.
item_id — ID из НАШЕГО запроса, определяется ТОЛЬКО по совпадению артикула/названия.
Алгоритм: для каждого товара в письме найди совпадающую позицию запроса по
артикулу/названию/размерам и возьми её item_id (НЕ по порядку!).
СОЗДАВАЙ OFFER при совпадении по названию/похожему названию (EC-LD-180 ≈ EC-LD 180-01)/
артикулу/описанию, даже если поставщик даёт меньше или артикул немного отличается.
НЕ создавай offer если товар совершенно другой или категория не совпадает.
КРИТИЧЕСКИЕ характеристики должны ТОЧНО совпадать: количество ремней (3≠4),
размеры (30мм≠20мм), cores (10≠12), напряжение (24V≠220V). Выбирай позицию с
максимальным совпадением; при расхождении укажи его в notes.

🚨 ДОСТАВКА/УСЛУГИ — НЕ ТОВАР, отдельный offer не создавай:
"Доставка", "Транспорт", "Погрузка/Разгрузка/ПРР", "Упаковка/Тара", "Курьер",
"Комиссия", "Сервисный сбор" → стоимость укажи в notes основного товара.

🚨 В offers ТОЛЬКО позиции с РЕАЛЬНОЙ ценой > 0. Недоступные ("нет в наличии",
цена 0) — НЕ в offers, информацию о них дай в summary.

🚨 АВТООТВЕТ vs ВОПРОС:
auto_reply (поставщик информирует, что работает): "Уточняем цену и сроки",
"Запрос принят/получен", "Готовим КП", "Присвоен номер", "Ответим позже".
question (поставщик ждёт твой ответ): есть "?", "Уточните, пожалуйста", "Прошу направить".
"Уточняем цену" без знака вопроса = auto_reply.

🚨 ПРЕДВАРИТЕЛЬНЫЕ/ДИАПАЗОННЫЕ ЦЕНЫ ОТ ПОСТАВЩИКА = OFFER, НЕ question:
Если поставщик называет конкретную цену(ы) — даже неуверенно, ориентировочно или несколько
вариантов («надо уточнять, есть по 850/750/700 р/метр», «ориентировочно ~X», «в районе X») —
это OFFER. Несколько цен по одной позиции → main_with_alternatives (основная цена — main,
остальные — alternative); хедж/неуверенность фиксируй в notes, цену НЕ обнуляй. question по
такому письму НЕ создавай. Question оставляй ТОЛЬКО когда цены нет ВООБЩЕ и поставщик ждёт
твоего ответа.

🚨 empty_reply: текст содержит "[ПИСЬМО БЕЗ НОВОГО СОДЕРЖАНИЯ]"/"[ПИСЬМО БЕЗ СОДЕРЖАНИЯ]",
либо письмо — только цитата исходного запроса без нового текста. Тогда has_offers=false,
has_questions=false, offers=[], questions=[].

🚨 ТОЛЬКО НОВЫЙ ТЕКСТ ОТВЕТА: анализируй исключительно новый текст поставщика. Если ниже
процитирован НАШ исходный запрос (после reply-заголовка «From:/От:/Sent:/Отправлено:/Кому:/
Тема:», «-----Original Message-----», «On … wrote:») — ПОЛНОСТЬЮ игнорируй его: не извлекай из
цитаты ни офферы, ни вопросы. «Нужно КП», «жду цены и сроки», «срочно», «прошу предоставить/
направить» В ЦИТАТЕ — это НАШ запрос, НЕ вопрос поставщика.

Типы писем (email_type): offer | question | mixed | rejection | auto_reply | empty_reply | other.

rejection_reason (ТОЛЬКО при email_type="rejection"):
- "not_our_profile": "не наш профиль", "не занимаемся", "не работаем с", "не поставляем".
- "not_available": "нет в наличии", "снят с производства", "временно отсутствует".
- "other": прочее (цена, минимальная партия и т.д.).
- ОТПИСКА-ДЕФЛЕКТ «зайдите к нам на сайт / посмотрите, чем мы занимаемся / наш ассортимент / что сможем поставить» со ссылкой на ГЛАВНУЮ страницу (не на конкретный товар) и БЕЗ цены по запросу — это email_type="rejection" (rejection_reason: not_our_profile — поставщик не поставляет запрошенное, отсылает к своему ассортименту), НЕ question. Вопроса к НАМ тут нет.

МНОЖЕСТВЕННЫЕ позиции для ОДНОГО item_id:
- КОМПЛЕКТ (составные части дополняют друг друга) → объедини, суммируй цены,
  offer_type="complete_set", заполни components[{name, price}].
- АЛЬТЕРНАТИВЫ (оригинал + аналог, разные артикулы) → отдельные офферы с одним item_id;
  первый offer_type="main_with_alternatives" (has_alternatives=true), остальные
  offer_type="alternative" (is_alternative_to_position = номер главной позиции).
- РАЗНЫЕ ВАРИАНТЫ ПОСТАВКИ одного товара → один offer, offer_type="single".
- Одна позиция → offer_type="single". Сомневаешься — суммируй как комплект.

Верни JSON:
{
  "email_type": "offer" | "question" | "mixed" | "rejection" | "auto_reply" | "empty_reply" | "other",
  "rejection_reason": "not_our_profile" | "not_available" | "other" | null,
  "has_offers": boolean,
  "offers": [
    {
      "item_id": number,
      "position_number": number,
      "price_per_unit": number,
      "total_price": number,
      "currency": "RUB",
      "price_includes_vat": boolean | null,
      "delivery_days": number | null,
      "payment_terms": "string" | null,
      "notes": "string",
      "offer_type": "complete_set" | "main_with_alternatives" | "alternative" | "single",
      "components": [{"name": "string", "price": number}],
      "has_alternatives": boolean,
      "is_alternative_to_position": number | null
    }
  ],
  "has_questions": boolean,
  "questions": [
    {
      "question_text": "string",
      "question_type": "product_clarification" | "delivery" | "payment" | "technical" | "general",
      "related_item_id": number | null,
      "can_auto_answer": boolean,
      "suggested_answer": "string" | null
    }
  ],
  "summary": "string",
  "fetch_urls": ["string"]
}
PROMPT;
    }
}
