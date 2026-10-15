<?php

namespace App\Support\Monitoring;

/**
 * Выбросы цен в пределах ОДНОЙ позиции клиента (а не общего товара).
 *
 * ListingChecker помечает выброс относительно цен товара, а товар общий и может объединять варианты
 * (M02502, 15.10.2026: 725 ₽ за ремень D4×600 «подозрительна» рядом с 5 000 ₽ за другие длины; при
 * этом 32 810 ₽ у snab-lift — трёх цен мало для проверки товара, и она прошла). Здесь — по ценам,
 * которые видит сама позиция (после ItemScope):
 *  - от 4 цен: отрыв от медианы больше чем в 4 раза (как в проверке страниц);
 *  - 3 цены: отрыв от медианы больше чем в 10 раз (медиана трёх — средняя цена, устойчива к одному выбросу);
 *  - 2 цены и меньше: не решаем (непонятно, какая из двух ошибочна).
 * Пересчитываются только отметки «выброс от медианы» проверки страниц; «скачок цены» (повтор через
 * 1–2 дня) не трогаем — его снимет перепроверка.
 */
final class PriceSanity
{
    public const RATIO_MANY = 4.0;

    public const RATIO_THREE = 10.0;

    /**
     * @param array<int, array<string,mixed>> $rows источники позиции: price, currency, price_kind, status,
     *                                               _outlier (bool — «подозрительно» из-за выброса от медианы товара)
     * @return array<int, array<string,mixed>> те же строки с пересчитанным status
     */
    public static function apply(array $rows): array
    {
        $idx = [];
        foreach ($rows as $k => $r) {
            $eligibleStatus = ($r['status'] ?? null) === 'active' || (($r['status'] ?? null) === 'suspicious' && !empty($r['_outlier']));
            if ($eligibleStatus && ($r['price'] ?? null) !== null && in_array($r['currency'] ?? 'RUB', [null, 'RUB'], true)
                && ($r['price_kind'] ?? null) !== 'wholesale_tier' && (float) $r['price'] > 0) {
                $idx[] = $k;
            }
        }
        $n = count($idx);
        if ($n < 3) {
            // Мало цен для решения — возвращаем отметки проверки страниц как есть.
            return $rows;
        }
        $prices = array_map(fn ($k) => (float) $rows[$k]['price'], $idx);
        sort($prices);
        $median = $n % 2 ? $prices[intdiv($n, 2)] : ($prices[$n / 2 - 1] + $prices[$n / 2]) / 2;
        $ratio = $n >= 4 ? self::RATIO_MANY : self::RATIO_THREE;
        foreach ($idx as $k) {
            $p = (float) $rows[$k]['price'];
            $rows[$k]['status'] = ($median > 0 && ($p / $median > $ratio || $median / $p > $ratio)) ? 'suspicious' : 'active';
        }

        return $rows;
    }
}
