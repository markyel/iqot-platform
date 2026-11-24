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
    /** Рецепт в работе, если на проверке дал ту же цену хотя бы в 5 из 6 известных карточек. */
    public const MIN_SCORE = 0.83;

    /** Цена по селектору встречается больше стольких раз — страница-список, рецепт карточки не применяем. */
    public const MAX_PRICE_NODES = 2;

    /** @param array{title?:?string, sku?:?string, price?:?string, availability?:?string} $selectors */
    public function __construct(public readonly array $selectors)
    {
    }

    /** Рабочий рецепт сайта или null. */
    public static function forSite(\App\Models\Monitoring\MpSite $site): ?self
    {
        $sel = (array) ($site->recipe ?? []);
        if (($sel['price'] ?? null) === null || (float) $site->recipe_score < self::MIN_SCORE) {
            return null;
        }

        return new self($sel);
    }

    /**
     * Результат в форме StructuredPriceExtractor (источник 'recipe') — только для карточки с ценой; иначе null.
     *
     * @param array<int,string> $codes нормализованные коды товара
     * @return array<string,mixed>|null
     */
    public function asStructured(string $html, array $codes): ?array
    {
        $r = $this->apply($html);
        if ($r['price'] === null || $r['price'] <= 0 || $r['price_nodes'] > self::MAX_PRICE_NODES) {
            return null;
        }
        $evidence = trim((string) $r['title'] . ' | ' . (string) $r['sku'], ' |');
        $tokens = \App\Support\Monitoring\ArticleNormalizer::tokens($evidence);
        $skuMatched = $codes !== [] && array_intersect(array_map('strval', $codes), array_map('strval', array_keys($tokens))) !== [];

        return ['price' => $r['price'], 'currency' => 'RUB', 'availability' => self::availability($r['availability']),
            'price_kind' => 'retail', 'vat_included' => null, 'price_unit' => null, 'price_unit_qty' => null,
            'source' => 'recipe', 'sku_matched' => $skuMatched, 'evidence' => mb_substr($evidence, 0, 400)];
    }

    private static function availability(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $t = mb_strtolower($text);

        return match (true) {
            (bool) preg_match('/под заказ|на заказ|ожидается|предзаказ/u', $t) => 'on_order',
            (bool) preg_match('/нет в наличии|отсутств|снят с производства/u', $t) => 'out_of_stock',
            (bool) preg_match('/в наличии|есть|на складе/u', $t) => 'in_stock',
            default => null,
        };
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
