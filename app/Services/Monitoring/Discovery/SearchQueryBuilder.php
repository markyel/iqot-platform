<?php

namespace App\Services\Monitoring\Discovery;

use App\Models\Monitoring\MpProduct;
use App\Services\Api\OpenAIClassifierClient;
use App\Services\Monitoring\LlmCostCalculator;
use App\Services\Monitoring\MonitoringGovernor;

/**
 * Поисковые запросы для поиска страниц продавцов товара. LLM составляет N разных
 * формулировок (все варианты артикула, бренд + название, «купить/цена/в наличии»,
 * один английский для иностранного бренда) — замер 14.10.2026: при тех же 2 запросах
 * полнота против CE 11,5% → 28% только за счёт формулировок и числа кандидатов.
 * Нет LLM-бюджета/ответа — простые шаблоны «"артикул" бренд цена» / «"артикул" купить».
 * Код каталога клиента (LW-…) в поиск не идёт.
 */
class SearchQueryBuilder
{
    public function __construct(
        private readonly ?OpenAIClassifierClient $llm,
        private readonly MonitoringGovernor $governor,
        private readonly LlmCostCalculator $cost,
    ) {
    }

    public static function make(): self
    {
        $client = OpenAIClassifierClient::fromConfig();

        return new self($client->isConfigured() ? $client : null, app(MonitoringGovernor::class), new LlmCostCalculator());
    }

    /** @return array<int,string> не больше $count запросов, самые точные первыми */
    public function build(MpProduct $product, int $count): array
    {
        $product->loadMissing('identifiers');
        $codes = [];
        foreach ($product->identifiers as $i) {
            if (in_array($i->kind, ['oem', 'mpn'], true)) {
                $codes[] = $i->value;
            }
        }
        $codes = array_values(array_unique($codes));
        $brand = trim((string) $product->brand);

        $queries = [];
        if ($this->llm !== null && $this->governor->hasLlmBudget()) {
            $queries = $this->fromLlm($product, $codes, $brand, $count);
        }
        if ($queries === []) {
            $queries = $this->simple($product, $codes, $brand);
        }

        return array_slice(array_values(array_unique($queries)), 0, max(1, $count));
    }

    /** @return array<int,string> */
    private function fromLlm(MpProduct $product, array $codes, string $brand, int $count): array
    {
        $system = 'Ты составляешь поисковые запросы для Яндекса, чтобы найти СТРАНИЦЫ ПРОДАВЦОВ этой запчасти с ценой '
            . '(интернет-магазины и поставщики лифтового/эскалаторного оборудования). Правила: артикулы/OEM — в кавычках; '
            . 'используй ВСЕ варианты артикула (с точками/без, короткие/длинные формы); сочетай бренд + артикул, бренд + название, '
            . 'название + ключевой параметр; добавляй «купить», «цена», «в наличии»; 1 запрос на английском, если бренд иностранный. '
            . 'Не ищи документацию и форумы. Отвечай строго JSON.';
        $user = "Товар: {$product->name}\nБренд: {$brand}\nOEM/артикулы: " . ($codes ? implode(', ', $codes) : '(нет)')
            . "\n\nJSON: {\"queries\": [\"…\"]} — ровно {$count} запросов, самые точные первыми.";
        try {
            $json = $this->llm->jsonCompletion((string) config('monitoring.llm.model', 'gpt-4o-mini'), $system, $user, 600, 0.0);
            $u = $this->llm->lastUsage();
            $this->governor->record('llm_call', 1, $u ? $this->cost->usd($u['model'], $u['prompt_tokens'], $u['completion_tokens']) : 0.0);

            return array_values(array_filter(array_map(
                fn ($q) => is_string($q) ? mb_substr(trim($q), 0, 200) : '',
                (array) ($json['queries'] ?? [])
            )));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int,string> */
    private function simple(MpProduct $product, array $codes, string $brand): array
    {
        $a = $codes[0] ?? null;
        if ($a !== null) {
            return [trim("\"{$a}\" {$brand} цена"), "\"{$a}\" купить", trim("{$brand} " . mb_substr((string) $product->name, 0, 100))];
        }
        $name = mb_substr(trim((string) $product->name), 0, 120);

        return [trim("{$name} {$brand} цена"), "{$name} купить"];
    }
}
