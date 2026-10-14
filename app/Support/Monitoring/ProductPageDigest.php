<?php

namespace App\Support\Monitoring;

use App\Services\Monitoring\Discovery\SitemapMatcher;

/**
 * Выжимка карточки товара для индекса сайта (Ф2): заголовок (H1 / og:title / title) и
 * артикулы со страницы — JSON-LD и microdata (sku, mpn, model, productID, gtin), подписи
 * «Артикул: …», «Код производителя», «Каталожный номер», «Part number», коды в заголовке.
 * Код сайта (RR3096) тоже попадает — безвреден: совпасть он может только с таким же кодом.
 */
final class ProductPageDigest
{
    private const LABEL_RE = '~(?:Артикул|Арт\.|Код\s+товара|Код\s+производителя|Каталожный\s+номер|Номер\s+детали|Заводской\s+номер|Part\s*(?:No\.?|Number|#)|OEM|SKU|MPN|Модель)\s*[:№#]?\s*([A-Za-zА-Яа-яЁё0-9][A-Za-zА-Яа-яЁё0-9\-./ ]{1,40})~u';

    /** @return array{title: ?string, codes: array<int,string>} */
    public static function fromHtml(string $html): array
    {
        $title = null;
        foreach ([
            '~<h1\b[^>]*>(.*?)</h1>~is',
            '~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']~i',
            '~<title\b[^>]*>(.*?)</title>~is',
        ] as $re) {
            if (preg_match($re, $html, $m)) {
                $t = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5)) ?? '');
                if ($t !== '') {
                    $title = mb_substr($t, 0, 500);
                    break;
                }
            }
        }

        $raw = [];
        // JSON-LD.
        if (preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $ld)) {
            foreach ($ld[1] as $json) {
                if (preg_match_all('~"(sku|mpn|model|productID|gtin\d*)"\s*:\s*"([^"]{2,60})"~i', $json, $m)) {
                    array_push($raw, ...$m[2]);
                }
            }
        }
        // Microdata.
        if (preg_match_all('~itemprop=["\'](?:sku|mpn|model|productID)["\'][^>]*?(?:content=["\']([^"\']{2,60})["\'][^>]*>|>([^<]{2,60})<)~i', $html, $md, PREG_SET_ORDER)) {
            foreach ($md as $m) {
                $raw[] = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
            }
        }
        // Подписи в тексте.
        // Любые пробелы (&nbsp; и т. п.) → обычный: иначе «3215-05-0037&nbsp;Цена» склеивается.
        $text = preg_replace('~[\s\p{Z}]+~u', ' ', HtmlText::toText($html)) ?? '';
        if (preg_match_all(self::LABEL_RE, mb_substr($text, 0, 60000), $lm)) {
            foreach (array_slice($lm[1], 0, 12) as $v) {
                // Значение — не больше 3 «слов» после подписи (дальше обычно уже текст).
                $raw[] = implode(' ', array_slice(explode(' ', trim($v)), 0, 3));
            }
        }

        $codes = [];
        foreach ($raw as $r) {
            $norm = ArticleNormalizer::normalize(html_entity_decode((string) $r, ENT_QUOTES));
            if ($norm !== '' && SitemapMatcher::isCode($norm)) {
                $codes[$norm] = true;
            }
            foreach (ArticleNormalizer::tokens((string) $r) as $c => $_) {
                if (SitemapMatcher::isCode($c)) {
                    $codes[$c] = true;
                }
            }
        }
        if ($title !== null) {
            foreach (ArticleNormalizer::tokens($title) as $c => $_) {
                if (SitemapMatcher::isCode($c)) {
                    $codes[$c] = true;
                }
            }
        }

        // Много разных кодов — это не карточка, а категория/листинг (revator: «Тяги канатные»
        // с десятками RR-кодов): коды не берём, иначе любой товар раздела «совпадёт» с ней.
        if (count($codes) > 10) {
            $codes = [];
        }

        // Числовые коды стали int-ключами массива — возвращаем строки.
        return ['title' => $title, 'codes' => array_map('strval', array_slice(array_keys($codes), 0, 40))];
    }
}
