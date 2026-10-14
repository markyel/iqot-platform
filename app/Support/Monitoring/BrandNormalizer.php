<?php

namespace App\Support\Monitoring;

/**
 * Нормализация бренда: верхний регистр, без юр. форм, кавычек и разделителей,
 * синонимы → канон (config monitoring.brand_aliases).
 */
final class BrandNormalizer
{
    private const LEGAL_FORMS = '/\b(ООО|ОАО|ЗАО|АО|ПАО|ИП|LLC|LTD|INC|GMBH|AG|SA|SPA|S\.P\.A|CO|CORP|CORPORATION|GROUP)\b/u';

    /** @param array<string,string>|null $aliases */
    public static function normalize(?string $brand, ?array $aliases = null): string
    {
        $b = mb_strtoupper(trim((string) $brand));
        if ($b === '') {
            return '';
        }
        $b = preg_replace(self::LEGAL_FORMS, ' ', $b) ?? $b;
        $b = preg_replace('/[\s\-_.\/\\\\,:;()\[\]«»"\'`&+]+/u', '', $b) ?? $b;
        if ($b === '') {
            return '';
        }
        $aliases ??= (array) config('monitoring.brand_aliases', []);

        return $aliases[$b] ?? $b;
    }

    /**
     * Бренды явно разные: оба известны и после нормализации не совпадают
     * (и один не является префиксом другого — «KONE» vs «KONECRANES» считаем разными,
     * но «OTIS» vs «OTISRUS» — тем же: допускаем суффикс ≥ 4 символа общего префикса).
     */
    public static function differ(string $a, string $b): bool
    {
        if ($a === '' || $b === '' || $a === $b) {
            return false;
        }
        $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;

        return !(mb_strlen($short) >= 4 && str_starts_with($long, $short) && mb_strlen($long) - mb_strlen($short) <= 3);
    }
}
