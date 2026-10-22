<?php

namespace App\Services\Monitoring\Extract;

use App\Models\Monitoring\MpProduct;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\LlmCostCalculator;
use App\Support\Monitoring\KeyAttributes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Ключевые признаки товара из наименования (один ИИ-вызов на товар, хранится в mp_products.key_attrs).
 * Ими проверяется, что страница поставщика про тот же вариант: сторона, исполнение, типоразмер, этаж
 * на кнопке, модель/серия оборудования, бренд (M02384, 22.10.2026: «правая» отводка получала цену «левой»
 * со страницы-списка и цены китайских отводок BLT/Fermator).
 */
class KeyAttributeExtractor
{
    public const VERSION = 'monitoring-key-attrs-v2';

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
     * Признаки уже есть — ничего не делаем; нет — извлекаем и сохраняем. Сбой ИИ — повтор не раньше чем через час.
     *
     * @return float стоимость вызова, $
     */
    public function ensure(MpProduct $product): float
    {
        if ($product->key_attrs_at !== null || !$this->client->isConfigured()) {
            return 0.0;
        }
        $failKey = 'mon:keyattrs:fail:' . $product->id;
        if (Cache::has($failKey)) {
            return 0.0;
        }
        try {
            $attrs = $this->extract($product);
        } catch (\Throwable $e) {
            Cache::put($failKey, 1, 3600);
            Log::warning('KeyAttributeExtractor: LLM failed', ['product_id' => $product->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return $this->lastCost();
        }
        $product->forceFill(['key_attrs' => $attrs, 'key_attrs_at' => now()])->save();

        return $this->lastCost();
    }

    /** @return array<int,array<string,mixed>> */
    public function extract(MpProduct $product): array
    {
        $json = $this->client->jsonCompletion(
            (string) config('monitoring.key_attrs.model', 'gpt-4.1-mini'),
            $this->systemPrompt(),
            $this->userPrompt($product),
            700,
        );

        return KeyAttributes::normalize($json['attrs'] ?? []);
    }

    public function lastCost(): float
    {
        $u = $this->client->lastUsage();

        return $u ? $this->cost->usd($u['model'], $u['prompt_tokens'], $u['completion_tokens']) : 0.0;
    }

    private function systemPrompt(): string
    {
        return <<<TXT
Ты разбираешь наименование запчасти/оборудования (B2B: лифты, эскалаторы, промышленность) и выделяешь КЛЮЧЕВЫЕ признаки — те, при расхождении которых на сайте поставщика это уже ДРУГОЙ вариант товара, который заказчику не подойдёт.

Бери только то, что есть в наименовании или производителе:
- side — сторона/направление: левая/правая, верхний/нижний, вверх/вниз, передний/задний;
- variant — исполнение: механическая/электрическая, открытого/закрытого типа, нормально открытый/закрытый, с подсветкой/без;
- size — типоразмер: длина, ширина, диаметр, сечение, шаг, ход (L=510мм, D=4мм, 1200 мм);
- number — номер/обозначение на детали: этаж или символ на кнопке («кнопка 5», «СТОП», «▲»), номер позиции;
- electrical — напряжение, ток, мощность (24V, 220 В, 0,75 кВт);
- model — модель/серия оборудования, к которому деталь (Techna, DO2000, ГЛ-3, ПЛ-6, OTIS 2000, KDO);
- brand — производитель или марка оборудования (OTIS, KONE, Schindler, ЩЛЗ, МЛМ, Fermator);
- other — иное отличительное (цвет, материал), только если явно отличает вариант.

НЕ бери: сам тип детали («отводка», «кнопка», «ролик» — по нему и так ищут), общие слова («лифта», «для дверей», «запчасть»), количество/упаковку, артикулы и каталожные коды (их сверяют отдельно).

Для каждого признака:
- key — короткое название по-русски («сторона», «длина», «модель привода», «бренд»);
- value — значение как в наименовании;
- kind — один из: side, variant, size, number, electrical, model, brand, other;
- match — как то же значение пишут на сайтах: рус. формы и сокращения, англ., транслит адресов страниц (правая → правая, правый, прав., right, RH, pravaya, pravaja, pravyj). Не давай однобуквенных («R», «П»);
- conflict — ТОЛЬКО для перечислимых признаков (side, variant, иногда number): написания взаимоисключающих значений (для «правая»: левая, левый, лев., left, LH, levaya, levaja, levyj). Для size, electrical, model, brand — пустой список.

БРЕНД: один признак brand. Если в наименовании и в поле «Производитель» разные марки (деталь Sematic для лифтов Schindler, Fermator для OTIS) — обе в match: сайты пишут то производителя детали, то марку лифта. Совместимые марки, перечисленные в названии («для OTIS/Sigma»), — тоже в match.

Признаков нет — пустой список. Не выдумывай признаки, которых нет в наименовании.
Отвечай строго JSON: {"attrs": [{"key": "…", "value": "…", "kind": "…", "match": ["…"], "conflict": ["…"]}]}
TXT;
    }

    private function userPrompt(MpProduct $product): string
    {
        $codes = implode(', ', $product->articleValues()) ?: '(нет)';
        $brand = $product->brand ?: '(не указан)';

        return "Наименование: {$product->name}\nПроизводитель: {$brand}\nАртикулы (не признаки): {$codes}";
    }
}
