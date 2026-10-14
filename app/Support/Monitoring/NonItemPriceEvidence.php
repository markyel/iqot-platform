<?php

namespace App\Support\Monitoring;

/**
 * Число цены в цитате стоит в словах о доставке или пороге заказа, а не о товаре
 * (порт FactMerger::isNonItemPriceEvidence Content Engine; кейс vent7.ru:
 * «Стоимость 1800 руб в пределах МКАД» при цене товара 22 053 ₽).
 * Смотрим только часть фразы с самим числом: «5 000 ₽, доставка 1–3 дня» — законная цена;
 * «купить с доставкой по РФ» — рекламный оборот, не стоимость доставки.
 */
final class NonItemPriceEvidence
{
    public static function check(string $evidence, float $price): bool
    {
        if ($evidence === '' || $price <= 0) {
            return false;
        }
        $evidence = preg_replace('~(?<=\d)[\s\x{00A0}\x{202F}](?=\d{3}\b)~u', '', $evidence) ?? $evidence;
        $int = (string) (int) floor($price);
        foreach (preg_split('~[;.!?\n]|,(?!\d)~u', $evidence) ?: [] as $clause) {
            if (preg_match('~(?<!\d)' . preg_quote($int, '~') . '(?!\d)~', $clause)
                && preg_match('~(?<!с\s)доставк|мкад|самовывоз|курьер|при заказе от|заказ\w* на сумму|минимальн\w* (?:заказ|сумм)|бесплатн~iu', $clause)) {
                return true;
            }
        }

        return false;
    }
}
