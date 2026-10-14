<?php

namespace App\Support\Monitoring;

/**
 * Число цены из строки (порт RawPriceImportService::parseNumber LazyLift):
 * «13 903,91», «13,903.91», «13.903,91», «269,500» (тысячи), «12 400 ₽», «12400руб.».
 * Ноль/отрицательное/мусор → null.
 */
final class PriceNumberParser
{
    public static function parse(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_bool($value)) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? round((float) $value, 2) : null;
        }

        $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x89"], ' ', (string) $value);
        if (!preg_match('/\d[\d\s.,\']*/u', $s, $m)) {
            return null;
        }
        $num = preg_replace('/[\s\']/u', '', trim($m[0])) ?? '';
        $num = rtrim($num, '.,');
        if ($num === '') {
            return null;
        }

        $lastDot = strrpos($num, '.');
        $lastComma = strrpos($num, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Оба разделителя: последний — десятичный.
            if ($lastComma > $lastDot) {
                $num = str_replace('.', '', $num);
                $num = str_replace(',', '.', $num);
            } else {
                $num = str_replace(',', '', $num);
            }
        } elseif ($lastComma !== false || $lastDot !== false) {
            $sep = $lastComma !== false ? ',' : '.';
            $count = substr_count($num, $sep);
            $after = strlen($num) - strrpos($num, $sep) - 1;
            if ($count > 1 || $after === 3) {
                // «1.234.567» или «269,500» — разделитель тысяч.
                $num = str_replace($sep, '', $num);
            } else {
                $num = str_replace($sep, '.', $num);
            }
        }

        if (!is_numeric($num)) {
            return null;
        }
        $f = (float) $num;

        return $f > 0 ? round($f, 2) : null;
    }
}
