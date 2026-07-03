<?php

namespace App\Services\Generate;

use App\Services\Api\OpenAIClassifierClient;

/**
 * LLM-склейка родственных позиций (смежные категории одного объекта).
 *
 * Заявка сферы лифты/эскалаторы — это номенклатура на КОНКРЕТНЫЙ объект (лифт/эскалатор
 * определённого бренда). Разные типы деталей одного объекта (ролик ДШ + замок ДШ +
 * отводка KONE) уместны в ОДНОМ запросе поставщику. Строгая маршрутизация бьёт их по
 * product_type в отдельные тонкие письма; здесь LLM решает, какие отложенные одиночные
 * позиции (сироты) правдоподобно с того же объекта, что позиции текущей заявки-якоря,
 * — чтобы приклеить их в одну сборную заявку. Без хардкода таксономии: 1 вызов на домен.
 */
class RelatedItemsClusterer
{
    public function __construct(
        private readonly OpenAIClassifierClient $client,
        private readonly string $model,
        private readonly int $maxTokens = 800,
    ) {
    }

    /**
     * Какие КАНДИДАТЫ (отложенные сироты) приклеить к ЯКОРЮ (позиции новой заявки).
     *
     * @param array<int,array<string,mixed>> $anchorItems позиции заявки-якоря (name/brand/article/type)
     * @param array<int,array<string,mixed>> $candidates сироты [{id,name,brand,article,type}]
     * @param string $domainName направление (Лифты/Эскалаторы)
     * @return array<int,int> item_id кандидатов «с того же объекта»
     */
    public function pick(array $anchorItems, array $candidates, string $domainName): array
    {
        if ($candidates === [] || $anchorItems === []) {
            return [];
        }

        $system = 'Ты помогаешь формировать один запрос коммерческого предложения (RFQ) поставщику '
            . 'запчастей для оборудования: ' . $domainName . '. Дан НАБОР позиций текущей заявки (ЯКОРЬ — '
            . 'номенклатура на конкретный объект/агрегат) и список КАНДИДАТОВ (отдельные отложенные позиции). '
            . 'Верни СТРОГО JSON {"attach":[id,...]} — id тех кандидатов, которые правдоподобно относятся к ТОМУ ЖЕ '
            . 'типу оборудования/объекту, что якорные позиции (совпадает бренд, узел/подсистема, класс детали) и '
            . 'уместны в одном запросе тому же кругу поставщиков. Сомневаешься — НЕ добавляй. Только JSON, без пояснений.';

        $user = "ЯКОРЬ (текущая заявка):\n" . $this->fmt($anchorItems, false)
            . "\n\nКАНДИДАТЫ (что можно приклеить):\n" . $this->fmt($candidates, true);

        try {
            $resp = $this->client->jsonCompletion($this->model, $system, $user, $this->maxTokens, 0.0);
        } catch (\Throwable $e) {
            return [];
        }

        $attach = is_array($resp['attach'] ?? null) ? $resp['attach'] : [];
        $valid = [];
        foreach ($candidates as $c) {
            $id = (int) ($c['id'] ?? 0);
            if ($id > 0) {
                $valid[$id] = true;
            }
        }
        $out = [];
        foreach ($attach as $x) {
            $x = (int) $x;
            if ($x > 0 && isset($valid[$x]) && !in_array($x, $out, true)) {
                $out[] = $x;
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function fmt(array $items, bool $withId): string
    {
        $lines = [];
        foreach ($items as $it) {
            $prefix = $withId ? '#' . (int) ($it['id'] ?? 0) . ' ' : '- ';
            $brand = ($it['brand'] ?? '') !== '' ? ' [' . $it['brand'] . ']' : '';
            $art = ($it['article'] ?? '') !== '' ? ' ' . $it['article'] : '';
            $type = ($it['type'] ?? '') !== '' ? ' (' . $it['type'] . ')' : '';
            $lines[] = $prefix . trim((string) ($it['name'] ?? '')) . $brand . $art . $type;
        }
        return implode("\n", $lines);
    }
}
