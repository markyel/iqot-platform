<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\DB;

/**
 * Какие страницы товара показывать позиции клиента (инцидент 15.10.2026, M02502).
 *
 * Канонический товар общий для клиентов, и его набор OEM/MPN — объединение того, что прислали все
 * клиенты. Кросс-номера одного клиента (liftpages перечислил на карточке «Ремень круглый D=4мм»
 * ремни длиной 550/600/650 мм) не должны действовать для другого: позиция «D4*600, KM247024»
 * получала страницы FI264123 (D4×550) по 5 000 ₽.
 *
 * Правило: если у товара есть OEM/MPN, которых позиция не заявляла («узкая» позиция), — ей видны
 * только страницы, на которых найден хотя бы один ЕЁ код. Страницы без кода (сопоставлены по
 * названию моделью) узкой позиции не показываются: проверить, тот ли это вариант, нечем.
 * Позиция без кодов или заявившая все коды товара видит всё, как раньше.
 */
final class ItemScope
{
    /** @return array<int,string> нормализованные коды позиции */
    public static function itemCodes(object $item): array
    {
        $codes = $item->identity_codes ?? null;
        if (is_string($codes)) {
            $codes = json_decode($codes, true);
        }

        return array_values(array_map('strval', (array) ($codes ?: [])));
    }

    /**
     * OEM/MPN товаров (без алиасов: каталожные коды клиентов — их личное, на «узость» не влияют).
     *
     * @param array<int,int> $productIds
     * @return array<int, array<int,string>> product_id => коды
     */
    public static function productCodes(array $productIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_filter($productIds))), 1000) as $chunk) {
            foreach (DB::table('mp_identifiers')->whereIn('product_id', $chunk)->whereIn('kind', ['oem', 'mpn'])
                ->get(['product_id', 'value_norm']) as $r) {
                $out[(int) $r->product_id][] = (string) $r->value_norm;
            }
        }

        return $out;
    }

    /** @param array<int,string> $itemCodes @param array<int,string> $productCodes */
    public static function isNarrow(array $itemCodes, array $productCodes): bool
    {
        return $itemCodes !== [] && array_diff($productCodes, $itemCodes) !== [];
    }

    /**
     * Коды, найденные на странице. До первой перепроверки после 16.10.2026 matched_codes пуст —
     * тогда берём код из доказательства сопоставления (oem_exact хранит найденный код).
     *
     * @return array<int,string>
     */
    public static function listingCodes(mixed $matchedCodes, ?string $method, ?string $evidence): array
    {
        if (is_string($matchedCodes)) {
            $matchedCodes = json_decode($matchedCodes, true);
        }
        if (is_array($matchedCodes)) {
            return array_values(array_map('strval', $matchedCodes));
        }
        if ($method === 'oem_exact' && $evidence) {
            $n = ArticleNormalizer::normalize($evidence);

            return $n !== '' ? [$n] : [];
        }

        return [];
    }

    /**
     * @param array<int,string> $itemCodes
     * @param array<int,string> $productCodes
     * @param array<int,string> $listingCodes
     */
    public static function allows(array $itemCodes, array $productCodes, array $listingCodes): bool
    {
        if (!self::isNarrow($itemCodes, $productCodes)) {
            return true;
        }

        return array_intersect($listingCodes, $itemCodes) !== [];
    }
}
