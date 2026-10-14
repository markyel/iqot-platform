<?php

namespace App\Support\Monitoring;

/**
 * Нормализация артикулов/OEM для сравнения «по токену».
 *
 * Отличия от ArticleNormalizer Content Engine / LazyLift:
 *  - похожие кириллические буквы сводятся к латинице (А/A, Р/P, Н/H…) — на русских
 *    сайтах артикул часто набран вперемешку и иначе не совпадает;
 *  - сравнение — целым токеном, не подстрокой (ABC123 ≠ ABC1234), см. containsToken().
 */
final class ArticleNormalizer
{
    /** Кириллица, внешне совпадающая с латиницей (верхний регистр). */
    private const CYR_TO_LAT = [
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'Ё' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H',
        'О' => 'O', 'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'У' => 'Y', 'Х' => 'X',
    ];

    /** Разделители, которые не различают артикул. */
    private const SEPARATORS = '/[\s\-_.\/\\\\,:;()\[\]«»"\'`+]+/u';

    public const MIN_LENGTH = 3;

    /** Нормализованный артикул или '' (слишком короткий / пустой). */
    public static function normalize(?string $value): string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $v = \Normalizer::normalize($v, \Normalizer::FORM_KC) ?: $v;
        }
        $v = mb_strtoupper($v);
        $v = self::homoglyphsToLatin($v);
        $v = preg_replace(self::SEPARATORS, '', $v) ?? $v;

        return mb_strlen($v) >= self::MIN_LENGTH ? $v : '';
    }

    /**
     * Похожие кириллические → латиница, если в строке есть латиница или цифры
     * (артикул). Чисто русское слово («РЕДУКТОР») не трогаем — это не код.
     */
    public static function homoglyphsToLatin(string $upper): string
    {
        if (!preg_match('/[A-Z0-9]/', $upper)) {
            return $upper;
        }

        return strtr($upper, self::CYR_TO_LAT);
    }

    /**
     * Токены-кандидаты из произвольного текста (страница, наименование): слова с
     * цифрами или латиницей, нормализованные. Склеиваем соседние части через
     * разделители («KM 763 640 G01» → также «KM763640G01»), чтобы находить коды,
     * набранные с пробелами.
     *
     * @return array<string,true> множество нормализованных токенов
     */
    public static function tokens(string $text, int $maxJoin = 4): array
    {
        $upper = self::homoglyphsToLatin(mb_strtoupper($text));
        $parts = preg_split('/[\s,;:()\[\]«»"\'`<>|]+/u', $upper, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $n = count($parts);
        for ($i = 0; $i < $n; $i++) {
            $acc = '';
            for ($j = $i; $j < min($n, $i + $maxJoin); $j++) {
                // Склеиваем только части длиной ≥2: «ABC123 4 шт» не должно дать «ABC1234».
                if ($j > $i && mb_strlen($parts[$j]) < 2) {
                    break;
                }
                $acc .= $parts[$j];
                $norm = preg_replace(self::SEPARATORS, '', $acc) ?? $acc;
                if (mb_strlen($norm) >= self::MIN_LENGTH
                    && (preg_match('/[0-9]/', $norm) || preg_match('/^[A-Z]{4,}$/', $norm))) {
                    $out[$norm] = true;
                }
            }
        }

        return $out;
    }

    /** Есть ли нормализованный артикул в тексте как отдельный токен (не подстрока). */
    public static function containsToken(string $text, string $articleNorm): bool
    {
        if ($articleNorm === '') {
            return false;
        }

        return isset(self::tokens($text)[$articleNorm]);
    }

    /**
     * Похож ли токен на код модели (буквы+цифры или длинное число), а не на число/размер.
     */
    public static function looksLikeModelCode(string $norm): bool
    {
        if (mb_strlen($norm) < 4) {
            return false;
        }
        $hasDigit = (bool) preg_match('/[0-9]/', $norm);
        $hasLetter = (bool) preg_match('/[A-ZА-Я]/u', $norm);

        return ($hasDigit && $hasLetter) || ($hasDigit && mb_strlen($norm) >= 6);
    }
}
