<?php

namespace App\Support\Monitoring;

/**
 * Страница-список (категория, прайс): цена должна стоять в той же карточке, что и наш товар.
 * M02384, 22.10.2026: на liftcomplete у карточки «TECHNA/DO2000, правая» цены нет («Заказать»), модель
 * взяла 9 847 ₽ соседней карточки «Prima 3100RII». Карточка — ближайший предок узла с нашим товаром,
 * у родителя которого ≥ 3 однотипных (тег + классы) соседа: так устроены сетки и строки каталогов.
 */
final class ListCards
{
    private const MAX_HTML = 2_000_000;

    /**
     * @param array<int,string> $codes нормализованные коды товара (ArticleNormalizer)
     * @param string $quote цитата, по которой опознан товар (название в карточке)
     * @return bool|null true — цена в карточке нашего товара; false — карточки нашли, цены в них нет;
     *                   null — не понять (нет повторяющихся карточек, якорь не найден) — не мешаем
     */
    public static function priceInOwnCard(string $html, array $codes, string $quote, float $price): ?bool
    {
        if ($html === '' || strlen($html) > self::MAX_HTML || $price <= 0) {
            return null;
        }
        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        foreach (['script', 'style', 'noscript', 'template'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $n) {
                $n->parentNode?->removeChild($n);
            }
        }

        $anchors = self::anchors($dom, $codes, $quote);
        $cards = [];
        foreach ($anchors as $node) {
            if (($card = self::cardOf($node)) !== null) {
                $cards[spl_object_id($card)] = $card;
            }
        }
        if ($cards === []) {
            return null;
        }
        foreach ($cards as $card) {
            if (self::hasPrice(self::text($card), $price)) {
                return true;
            }
        }

        return false;
    }

    /** Самые глубокие элементы с нашим кодом (токеном) или с цитатой-названием. @return array<int,\DOMElement> */
    private static function anchors(\DOMDocument $dom, array $codes, string $quote): array
    {
        $q = self::norm($quote);
        if (mb_strlen($q) > 80) {
            $q = mb_substr($q, 0, 80); // заголовок карточки — начало цитаты; хвост модель могла склеить из соседних узлов
            $q = mb_substr($q, 0, (int) mb_strrpos($q, ' ')) ?: $q;
        }
        $useQuote = mb_strlen(str_replace(' ', '', $q)) >= 8;
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return [];
        }
        $found = [];
        $walk = function (\DOMElement $el) use (&$walk, &$found, $codes, $q, $useQuote): bool {
            $text = self::text($el);
            $hit = ($useQuote && str_contains(self::norm($text), $q))
                || ($codes !== [] && array_intersect($codes, array_map('strval', array_keys(ArticleNormalizer::tokens($text)))) !== []);
            if (!$hit) {
                return false;
            }
            $deeper = false;
            foreach ($el->childNodes as $c) {
                if ($c instanceof \DOMElement && $walk($c)) {
                    $deeper = true;
                }
            }
            if (!$deeper) {
                $found[] = $el;
            }

            return true;
        };
        $walk($body);

        return array_slice($found, 0, 20);
    }

    /**
     * Карточка: самый верхний предок, у родителя которого ≥ 3 однотипных («тег.классы») соседа и в котором
     * не больше 2 ссылок на разные страницы. Строки свойств внутри карточки («Артикул: …» ×3) тоже
     * повторяются — поэтому не ближайший, а верхний; блок со многими товарами отсекают ссылки.
     */
    private static function cardOf(\DOMElement $node): ?\DOMElement
    {
        $card = null;
        for ($el = $node, $depth = 0; $el instanceof \DOMElement && $el->parentNode instanceof \DOMElement && $depth < 30; $el = $el->parentNode, $depth++) {
            if (in_array(strtolower($el->tagName), ['body', 'html'], true)) {
                break;
            }
            if (self::links($el) > 2) {
                break;
            }
            $sig = self::signature($el);
            $same = 0;
            foreach ($el->parentNode->childNodes as $sib) {
                if ($sib instanceof \DOMElement && self::signature($sib) === $sig) {
                    $same++;
                }
            }
            if ($same >= 3 && mb_strlen(trim(self::text($el))) >= 15) {
                $card = $el;
            }
        }

        return $card;
    }

    /** Ссылки на разные страницы (без якорей, javascript:, параметров). */
    private static function links(\DOMElement $el): int
    {
        $hrefs = [];
        foreach ($el->getElementsByTagName('a') as $a) {
            $h = trim((string) $a->getAttribute('href'));
            if ($h === '' || $h[0] === '#' || stripos($h, 'javascript:') === 0 || stripos($h, 'tel:') === 0 || stripos($h, 'mailto:') === 0) {
                continue;
            }
            $hrefs[rtrim((string) preg_replace('/[?#].*$/', '', $h), '/')] = true;
            if (count($hrefs) > 2) {
                break;
            }
        }

        return count($hrefs);
    }

    private static function signature(\DOMElement $el): string
    {
        $classes = preg_split('/\s+/', trim($el->getAttribute('class'))) ?: [];
        // Модификаторы состояния («active», «in-stock», «product-item--sale») не должны ломать однотипность.
        $classes = array_filter($classes, fn ($c) => $c !== '' && !preg_match('/active|selected|first|last|odd|even|stock|sale|hit|new|--/i', $c));
        sort($classes);

        return strtolower($el->tagName) . '.' . implode('.', $classes);
    }

    private static function hasPrice(string $text, float $price): bool
    {
        // Разделители разрядов (пробел, неразрывный, тонкий) → слитно: «9 738₽» → «9738₽».
        $t = preg_replace('/(?<=\d)[\s\x{00A0}\x{202F}\x{2009}](?=\d{3}(?!\d))/u', '', $text) ?? $text;
        $int = (string) (int) floor($price);
        $frac = round($price - floor($price), 2);
        $re = '/(?<![\d.,])' . $int . ($frac > 0 ? '[.,]' . str_pad((string) (int) round($frac * 100), 2, '0', STR_PAD_LEFT) : '(?:[.,]0{1,2})?') . '(?![\d])/u';

        return preg_match($re, $t) === 1;
    }

    /** Текст узла: соседние блоки через пробел (textContent склеивает «FAA24390D1Вес:»). */
    private static function text(\DOMNode $n): string
    {
        static $cache;
        $cache ??= new \WeakMap();
        if (isset($cache[$n])) {
            return $cache[$n];
        }
        $parts = [];
        foreach ((new \DOMXPath($n->ownerDocument ?? $n))->query('.//text()', $n) ?: [] as $t) {
            $v = trim((string) $t->nodeValue);
            if ($v !== '') {
                $parts[] = $v;
            }
        }

        return $cache[$n] = implode(' ', $parts);
    }

    private static function norm(string $t): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('ё', 'е', mb_strtolower($t))));
    }
}
