<?php

namespace App\Services\Monitoring\Extract;

use App\Models\Monitoring\MpProduct;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\LlmCostCalculator;
use App\Support\Monitoring\KeyAttributes;
use App\Support\Monitoring\NonItemPriceEvidence;
use App\Support\Monitoring\PriceNumberParser;
use App\Support\Monitoring\UnitNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Цена и признаки товара со страницы через LLM (gpt-4o-mini), когда разметки нет.
 * Промпт monitoring-price-extract-v1 объединяет PriceCheckPrompt / правило 5
 * research-extract-v2 (Content Engine) и правила единиц/аналогов EmailReplyParserService
 * (LazyLift). На вход — окно текста вокруг упоминаний товара, а не начало страницы.
 */
class LlmPriceExtractor
{
    public const VERSION = 'monitoring-price-extract-v2';

    private const AVAIL = ['in_stock', 'on_order', 'out_of_stock', 'unknown'];
    private const KINDS = ['retail', 'from', 'wholesale_tier', 'on_request'];

    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly LlmCostCalculator $cost,
    ) {
    }

    public static function make(): self
    {
        return new self(OpenAIClassifierClient::fromConfig(), new LlmCostCalculator());
    }

    /**
     * @return array{found: bool, price: ?float, currency: string, price_kind: ?string, vat_included: ?bool,
     *   price_unit: ?string, price_unit_qty: ?float, min_order_qty: ?float, availability: ?string, stock_qty: ?float,
     *   lead_days: ?int, evidence_price: string, evidence_identity: string, is_same_product: ?bool, is_analog: bool,
     *   cost_usd: float, error: ?string}
     */
    public function extract(MpProduct $product, string $url, string $textWindow): array
    {
        $empty = $this->emptyResult();
        if (!$this->client->isConfigured()) {
            return ['error' => 'llm_not_configured'] + $empty;
        }

        try {
            $json = $this->client->jsonCompletion(
                (string) config('monitoring.llm.model', 'gpt-4o-mini'),
                $this->systemPrompt(),
                $this->userPrompt($product, $url, $textWindow),
                (int) config('monitoring.llm.max_tokens', 400),
            );
        } catch (\Throwable $e) {
            Log::warning('LlmPriceExtractor: LLM failed', ['url' => $url, 'error' => $e->getMessage()]);

            return ['error' => mb_substr($e->getMessage(), 0, 200), 'cost_usd' => $this->lastCost()] + $empty;
        }

        $price = !empty($json['found']) ? PriceNumberParser::parse($json['price'] ?? null) : null;
        $evidencePrice = mb_substr((string) ($json['evidence_price'] ?? ''), 0, 400);
        if ($price !== null && NonItemPriceEvidence::check($evidencePrice, $price)) {
            $price = null; // цена доставки / порога заказа, не товара
        }
        // Цена-заглушка / эхо примера из шаблона (бенч 14.10: модель вернула «12345» со страницы категории).
        if ($price !== null && ($price <= 1.0 || in_array($price, [12345.0, 123456.0, 1234.0, 99999.0], true))) {
            $price = null;
        }
        $kind = in_array($json['price_kind'] ?? null, self::KINDS, true) ? $json['price_kind'] : null;
        if ($price === null && $kind !== 'on_request') {
            $kind = null;
        }
        $unit = $json['price_unit'] ?? null;

        return [
            'found' => $price !== null,
            'price' => $price,
            'currency' => strtoupper((string) ($json['currency'] ?? 'RUB')) === 'RUR' ? 'RUB' : (strtoupper((string) ($json['currency'] ?? '')) ?: 'RUB'),
            'price_kind' => $price !== null ? ($kind ?? 'retail') : $kind,
            'vat_included' => is_bool($json['vat_included'] ?? null) ? $json['vat_included'] : null,
            'price_unit' => $unit !== null ? UnitNormalizer::normalize((string) $unit) : null,
            'price_unit_qty' => is_numeric($json['price_unit_qty'] ?? null) && (float) $json['price_unit_qty'] > 0 ? (float) $json['price_unit_qty'] : null,
            'min_order_qty' => is_numeric($json['min_order_qty'] ?? null) && (float) $json['min_order_qty'] > 0 ? (float) $json['min_order_qty'] : null,
            'availability' => in_array($json['availability'] ?? null, self::AVAIL, true) ? $json['availability'] : null,
            'stock_qty' => is_numeric($json['stock_qty'] ?? null) ? (float) $json['stock_qty'] : null,
            'lead_days' => is_numeric($json['lead_days'] ?? null) ? (int) $json['lead_days'] : null,
            'evidence_price' => $evidencePrice,
            'evidence_identity' => mb_substr((string) ($json['evidence_identity'] ?? ''), 0, 400),
            'is_same_product' => is_bool($json['is_same_product'] ?? null) ? $json['is_same_product'] : null,
            'is_analog' => (bool) ($json['is_analog'] ?? false),
            'attrs' => self::attrVerdicts($json['attrs'] ?? []),
            'cost_usd' => $this->lastCost(),
            'error' => null,
        ];
    }

    private function lastCost(): float
    {
        $u = $this->client->lastUsage();

        return $u ? $this->cost->usd($u['model'], $u['prompt_tokens'], $u['completion_tokens']) : 0.0;
    }

    private function emptyResult(): array
    {
        return [
            'found' => false, 'price' => null, 'currency' => 'RUB', 'price_kind' => null, 'vat_included' => null,
            'price_unit' => null, 'price_unit_qty' => null, 'min_order_qty' => null, 'availability' => null,
            'stock_qty' => null, 'lead_days' => null, 'evidence_price' => '', 'evidence_identity' => '',
            'is_same_product' => null, 'is_analog' => false, 'attrs' => [], 'cost_usd' => 0.0, 'error' => null,
        ];
    }

    /**
     * Сверка ключевых признаков моделью: [{key, page, verdict: match|conflict|absent}].
     *
     * @return array<int,array{key:string,page:string,verdict:string}>
     */
    public static function attrVerdicts(mixed $raw): array
    {
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $a) {
            if (!is_array($a) || !in_array($a['verdict'] ?? null, ['match', 'conflict', 'absent'], true)) {
                continue;
            }
            $out[] = ['key' => mb_substr((string) ($a['key'] ?? ''), 0, 60), 'page' => mb_substr((string) ($a['page'] ?? ''), 0, 160), 'verdict' => $a['verdict']];
        }

        return $out;
    }

    private function systemPrompt(): string
    {
        return <<<TXT
Ты читаешь фрагменты страницы сайта поставщика (B2B: запчасти, оборудование) и находишь текущую цену и наличие ОДНОГО заданного товара.

ИДЕНТИЧНОСТЬ (is_same_product):
- true — на странице именно этот товар: тот же артикул/модель (с точностью до пробелов, дефисов, регистра), тот же производитель или производитель не указан.
- false — другой товар: другой артикул/модель, другой тип детали (даже той же серии), другие параметры (размер, диаметр, длина, левый/правый, напряжение), другой производитель.
- null — данных недостаточно, чтобы уверенно сказать.
- is_analog=true — страница предлагает АНАЛОГ / замену / совместимый товар другого производителя вместо запрошенного.

КЛЮЧЕВЫЕ ПРИЗНАКИ (attrs) — если в запросе есть список «Ключевые признаки», для КАЖДОГО верни, что про него сказано у товара на странице (в его карточке/строке, не у соседних товаров):
- verdict="match" — на странице то же значение;
- verdict="conflict" — на странице у этого товара явно ДРУГОЕ значение (левая вместо правой, 550 мм вместо 510, «Подходит для: BLT, Fermator» при бренде OTIS, другая модель привода);
- verdict="absent" — признак у товара не указан. Неуказанный признак (часто бренд или модель) — НЕ противоречие.
page — дословно, что написано на странице (или пусто). Хотя бы один conflict → is_same_product=false.

ЦЕНА:
- Цена именно этого товара. Если на странице несколько товаров и нельзя уверенно определить цену нужного — found=false.
- НЕ цена товара: стоимость доставки, «в пределах МКАД», самовывоз, курьер, «при заказе от …», минимальная сумма заказа, бесплатная доставка, монтаж, цены соседних/похожих товаров, старая зачёркнутая цена.
- «Цена по запросу», «договорная», «уточняйте», нет цифры → found=false, price_kind="on_request" (наличие всё равно укажи).
- Несколько цен (розница/опт/от количества): price — розничная (за минимальное количество); если есть только «от N ₽» — price_kind="from"; только оптовая — "wholesale_tier".
- price_unit — за что цена: "шт", "м" (руб/м, за п.м., погонный метр), "кг", "л", "компл", "уп". «За отрезок/бухту 190 м» → price_unit="м", price_unit_qty=190 (цена за весь отрезок). Не пересчитывай цену.
- vat_included: true если указано «с НДС»/«в т.ч. НДС», false если «без НДС», null если не указано.
- availability: in_stock (в наличии, на складе, N шт.), on_order (под заказ, срок поставки), out_of_stock (нет в наличии, снят с производства), unknown.
- evidence_price — короткая дословная цитата со страницы, где стоит цена (с числом). evidence_identity — цитата, по которой опознан товар (артикул/название).
Отвечай строго JSON.
TXT;
    }

    private function userPrompt(MpProduct $product, string $url, string $text): string
    {
        $codes = implode(', ', $product->articleValues()) ?: '(нет)';
        $brand = $product->brand ?: '(не указан)';
        $attrs = KeyAttributes::promptLines((array) ($product->key_attrs ?? []));
        $attrs = $attrs !== '' ? "Ключевые признаки:\n{$attrs}\n" : '';
        $attrsJson = $attrs !== '' ? '"attrs": [{"key": "…", "page": "…", "verdict": "match|conflict|absent"}], ' : '';

        return <<<TXT
Товар: {$product->name}
Производитель: {$brand}
Артикул/модель: {$codes}
{$attrs}URL: {$url}

Фрагменты страницы:
{$text}

JSON: {{$attrsJson}"is_same_product": true|false|null, "is_analog": true|false, "found": true|false, "price": число|null, "currency": "RUB|USD|EUR|CNY", "price_kind": "retail|from|wholesale_tier|on_request|null", "vat_included": true|false|null, "price_unit": "шт|м|кг|л|компл|уп|null", "price_unit_qty": число|null, "min_order_qty": число|null, "availability": "in_stock|on_order|out_of_stock|unknown", "stock_qty": число|null, "lead_days": число|null, "evidence_price": "…", "evidence_identity": "…"}
TXT;
    }
}
