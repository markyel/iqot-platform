<?php

namespace App\Support\Monitoring;

/**
 * НДС в цене сайта по тексту страницы (24.11.2026): разметка schema.org поля НДС не имеет — у цен из разметки признак
 * был пуст (1 468 из 1 474), и клиент (mzCorp) считал их ценами с НДС: «28 696 ₽ (+НДС)» у lzpro делил на 1,22.
 * Сначала — подпись рядом с самой ценой, затем общая фраза сайта («Цены указаны без НДС»). Не нашли — null.
 */
final class VatDetector
{
    private const NET_RE = '~(\+|плюс)\s*ндс|без\s+(учета\s+|учёта\s+)?ндс|ндс\s+не\s+(включ|облага|начисл)|не\s+облагается\s+ндс|без\s+налога|не\s+является\s+плательщиком\s+ндс|усн~u';

    private const GROSS_RE = '~(?<![\p{L}])(с|со|вкл\.?|включая|в\s*т\.\s*ч\.?|в\s+том\s+числе|учитывая)\s*ндс|ндс\s*(\d{1,2}\s*%\s*)?(включ|учт[её]н)|с\s+учетом\s+ндс|с\s+учётом\s+ндс~u';

    private const SITE_NET_RE = '~цен[аы]?\s+(на\s+сайте\s+)?(указан[аы]?\s+|приведен[аы]?\s+)?без\s+(учета\s+|учёта\s+)?ндс~u';

    private const SITE_GROSS_RE = '~(все\s+)?цен[аы]?\s+(на\s+сайте\s+)?(указан[аы]?\s+|приведен[аы]?\s+)?(с\s+учетом|с\s+учётом|с|включая)\s+ндс~u';

    /**
     * Преобладающий признак НДС сайта: не меньше $min определённых цен и доля одного значения не ниже $share; иначе null.
     */
    public static function siteDefault(int $withVat, int $withoutVat, int $min = 5, float $share = 0.9): ?bool
    {
        $n = $withVat + $withoutVat;
        if ($n < $min) {
            return null;
        }

        return match (true) {
            $withVat / $n >= $share => true,
            $withoutVat / $n >= $share => false,
            default => null,
        };
    }

    public static function detect(string $text, ?float $price): ?bool
    {
        $t = str_replace(["\u{00A0}", "\u{202F}", 'ё'], [' ', ' ', 'е'], mb_strtolower($text));
        if ($price !== null && $price > 0) {
            // Сначала подпись после цены («28 696 ₽ (+НДС)»), затем перед ней («Цена с НДС: …») — в пределах предложения.
            foreach (self::priceSnippets($t, $price) as [$before, $after]) {
                foreach ([$after, $before] as $snippet) {
                    if (preg_match(self::NET_RE, $snippet)) {
                        return false;
                    }
                    if (preg_match(self::GROSS_RE, $snippet)) {
                        return true;
                    }
                }
            }
        }
        if (preg_match(self::SITE_NET_RE, $t)) {
            return false;
        }
        if (preg_match(self::SITE_GROSS_RE, $t)) {
            return true;
        }

        return null;
    }

    /** @return array<int,array{0:string,1:string}> для каждого вхождения цены: [до цены (до 30 симв., в пределах предложения), после цены (40 симв.)] */
    private static function priceSnippets(string $t, float $price): array
    {
        $int = (string) (int) floor($price);
        // Разряды могут разделяться пробелом/точкой: 28696 → 28[ .]?696
        $groups = str_split(strrev($int), 3);
        $pattern = implode('[ .]?', array_map('strrev', array_reverse($groups)));
        if (!preg_match_all('~(?<!\d)' . $pattern . '(?!\d)~u', $t, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $out = [];
        foreach (array_slice($m[0], 0, 5) as [$match, $byteOffset]) {
            $pos = mb_strlen(substr($t, 0, $byteOffset));
            $before = mb_substr($t, max(0, $pos - 30), min(30, $pos));
            $before = (string) preg_replace('~^.*[.!?;\n](?=\s|$)~su', '', $before);
            $out[] = [$before, mb_substr($t, $pos + mb_strlen($match), 40)];
        }

        return $out;
    }
}
