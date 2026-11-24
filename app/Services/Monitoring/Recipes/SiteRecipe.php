<?php

namespace App\Services\Monitoring\Recipes;

use App\Support\Monitoring\PriceNumberParser;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Рецепт карточки товара сайта (пилот профиля сайта, 24.11.2026): CSS-селекторы названия, артикула, цены и наличия.
 * Выводится один раз на сайт (SiteRecipeLearner) и проверяется на известных ценах; применяется без ИИ.
 */
final class SiteRecipe
{
    /** @param array{title?:?string, sku?:?string, price?:?string, availability?:?string} $selectors */
    public function __construct(public readonly array $selectors)
    {
    }

    /** @return array{title:?string, sku:?string, price:?float, price_text:?string, availability:?string, price_nodes:int} */
    public function apply(string $html): array
    {
        $xp = self::xpath($html);
        $text = function (?string $css) use ($xp): array {
            if ($xp === null || $css === null || trim($css) === '') {
                return [];
            }
            try {
                $nodes = $xp->query((new CssSelectorConverter())->toXPath($css));
            } catch (\Throwable) {
                return [];
            }
            $out = [];
            foreach ($nodes ?: [] as $n) {
                $v = trim(preg_replace('/\s+/u', ' ', (string) $n->textContent) ?? '');
                if ($v === '' && $n instanceof \DOMElement) {
                    $v = trim((string) ($n->getAttribute('content') ?: $n->getAttribute('value')));
                }
                if ($v !== '') {
                    $out[] = $v;
                }
            }

            return $out;
        };
        $prices = $text($this->selectors['price'] ?? null);
        $title = $text($this->selectors['title'] ?? null);
        $sku = $text($this->selectors['sku'] ?? null);
        $avail = $text($this->selectors['availability'] ?? null);

        return [
            'title' => $title[0] ?? null,
            'sku' => $sku[0] ?? null,
            'price' => isset($prices[0]) ? PriceNumberParser::parse($prices[0]) : null,
            'price_text' => $prices[0] ?? null,
            'availability' => $avail[0] ?? null,
            'price_nodes' => count($prices),
        ];
    }

    public static function xpath(string $html): ?\DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $ok ? new \DOMXPath($doc) : null;
    }
}
