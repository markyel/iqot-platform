<?php

namespace App\Support\Monitoring;

/**
 * HTML → текст, приведение к UTF-8 (порт HtmlText Content Engine).
 *
 * В отличие от CE не вырезаем <form>/шапку товара (removeChrome): на многих витринах
 * блок цены с кнопкой «В корзину» лежит внутри формы — цена терялась бы.
 */
final class HtmlText
{
    public static function toText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }
        $html = preg_replace('~<(script|style|noscript|template|svg|iframe)[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
        $html = preg_replace('~<(nav|footer)[^>]*>.*?</\1>~is', ' ', $html) ?? $html;
        $html = preg_replace('~<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</(p|div|li|h[1-6]|tr|td|th|blockquote|section|article|header|dd|dt)>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], ' ', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n[ \t]+/u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function toUtf8(string $body, ?string $contentType = null): string
    {
        $charset = null;
        if ($contentType && preg_match('/charset\s*=\s*["\']?([a-z0-9_\-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        }
        if (!$charset && preg_match('/<meta[^>]+charset\s*=\s*["\']?\s*([a-z0-9_\-]+)/i', substr($body, 0, 4096), $m)) {
            $charset = $m[1];
        }
        $charset = $charset ? strtolower($charset) : null;
        if ($charset && in_array($charset, ['utf-8', 'utf8'], true)) {
            $charset = null;
        }
        if (!$charset && !mb_check_encoding($body, 'UTF-8')) {
            $charset = 'windows-1251';
        }
        if ($charset) {
            $map = ['cp1251' => 'windows-1251', 'win-1251' => 'windows-1251', 'koi8r' => 'KOI8-R'];
            $converted = @mb_convert_encoding($body, 'UTF-8', $map[$charset] ?? $charset);
            if (is_string($converted) && $converted !== '') {
                $body = $converted;
            }
        }

        return preg_replace('/(<meta[^>]+charset\s*=\s*["\']?)([a-z0-9_\-]+)/i', '${1}utf-8', $body, 1) ?? $body;
    }

    /**
     * Окно текста вокруг упоминаний «якорей» (артикул, слова названия): ±$radius
     * символов вокруг каждого вхождения, склейка пересечений, общий потолок $max.
     * Не «первые N символов» — у многих витрин цена ниже шаблонной шапки (урок CE).
     *
     * @param array<int,string> $anchors
     */
    public static function window(string $text, array $anchors, int $radius, int $max): string
    {
        $upper = mb_strtoupper($text);
        $ranges = [];
        foreach ($anchors as $anchor) {
            $a = mb_strtoupper(trim($anchor));
            if (mb_strlen($a) < 3) {
                continue;
            }
            $offset = 0;
            $hits = 0;
            while (($pos = mb_strpos($upper, $a, $offset)) !== false && $hits < 5) {
                $ranges[] = [max(0, $pos - $radius), $pos + mb_strlen($a) + $radius];
                $offset = $pos + mb_strlen($a);
                $hits++;
            }
        }
        if ($ranges === []) {
            return mb_substr($text, 0, $max);
        }
        usort($ranges, fn ($x, $y) => $x[0] <=> $y[0]);
        $merged = [];
        foreach ($ranges as [$s, $e]) {
            if ($merged !== [] && $s <= $merged[count($merged) - 1][1]) {
                $merged[count($merged) - 1][1] = max($merged[count($merged) - 1][1], $e);
            } else {
                $merged[] = [$s, $e];
            }
        }
        $out = '';
        foreach ($merged as [$s, $e]) {
            $out .= ($out === '' ? '' : "\n…\n") . mb_substr($text, $s, $e - $s);
            if (mb_strlen($out) >= $max) {
                break;
            }
        }

        return mb_substr($out, 0, $max);
    }
}
