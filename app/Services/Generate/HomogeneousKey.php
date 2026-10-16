<?php

namespace App\Services\Generate;

use App\Support\Monitoring\BrandNormalizer;

/**
 * Ключ «однотипности» позиции для объединения в одно письмо (решение владельца 16.10.2026).
 *
 * Письмо по умолчанию — на одну позицию (разношёрстные письма из 4–5 позиций разных брендов
 * не похожи на реальную потребность ремонта и снижают отклик). Исключение — однотипные позиции:
 * кнопки 1–6 этажей, ролики одной серии разных диаметров. Однотипны позиции, у которых
 *  - один тип товара (product_type_id),
 *  - один бренд (нормализованный),
 *  - наименования совпадают, если убрать слова с цифрами (номер этажа, размер, артикул).
 * Без типа или бренда ключа нет — такие позиции идут по одной.
 */
final class HomogeneousKey
{
    public static function of(?int $productTypeId, ?string $brand, ?string $name): ?string
    {
        $brandNorm = BrandNormalizer::normalize($brand);
        if (!$productTypeId || $brandNorm === '' || trim((string) $name) === '') {
            return null;
        }
        $skeleton = self::skeleton((string) $name);
        // Слишком короткий «скелет» («Ролик #») не отличает товары — не объединяем.
        if (preg_match_all('/\p{L}/u', $skeleton) < 6) {
            return null;
        }

        return $productTypeId . '|' . $brandNorm . '|' . $skeleton;
    }

    /** Наименование без слов, содержащих цифры, и без пунктуации. */
    public static function skeleton(string $name): string
    {
        $s = mb_strtolower($name);
        $s = preg_replace('/[«»"\'`()\[\]{},.;:!?№]+/u', ' ', $s) ?? $s;
        $out = [];
        foreach (preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $t) {
            $t = preg_match('/\d/u', $t) ? '#' : $t;
            if ($t === '#' && end($out) === '#') {
                continue;
            }
            $out[] = $t;
        }

        return implode(' ', $out);
    }
}
