<?php

namespace App\Services\Monitoring;

/**
 * Стоимость LLM-вызова в $ по фактической модели из ответа прокси.
 *
 * Поиск цены: сначала точное имя, затем САМЫЙ ДЛИННЫЙ подходящий префикс.
 * Урок Content Engine: перебор префиксов в порядке конфига находил «gpt-4o» раньше
 * «gpt-4o-mini» для «gpt-4o-mini-2024-07-18» → учёт завышался в ~5 раз.
 */
class LlmCostCalculator
{
    /** @param array<string,array{0:float,1:float}>|null $prices $ за 1M токенов [вход, выход] */
    public function __construct(private readonly ?array $prices = null)
    {
    }

    public function usd(string $model, int $promptTokens, int $completionTokens): float
    {
        $price = $this->priceFor($model);
        if ($price === null) {
            return 0.0;
        }

        return round(($promptTokens * $price[0] + $completionTokens * $price[1]) / 1_000_000, 6);
    }

    /** @return array{0:float,1:float}|null */
    public function priceFor(string $model): ?array
    {
        $prices = $this->prices ?? (array) config('monitoring.llm_prices', []);
        $model = mb_strtolower(trim($model));
        if (isset($prices[$model])) {
            return $prices[$model];
        }
        $best = null;
        $bestLen = 0;
        foreach ($prices as $name => $price) {
            $name = mb_strtolower($name);
            if (str_starts_with($model, $name) && mb_strlen($name) > $bestLen) {
                $best = $price;
                $bestLen = mb_strlen($name);
            }
        }

        return $best;
    }
}
