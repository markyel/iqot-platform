<?php

namespace App\Services\Monitoring\Extract;

use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\HtmlText;
use App\Support\Monitoring\PriceNumberParser;

/**
 * Цена из машиночитаемой разметки страницы — без LLM: JSON-LD (schema.org
 * Product/Offer), microdata (itemprop="price"), OpenGraph (product:price:amount).
 * Порт StructuredPriceExtractor Content Engine с правками (docs/monitoring-f0-spec.md §7.2):
 *  - товар JSON-LD опознаётся по артикулу ТОКЕНОМ (не подстрокой); одиночный товар
 *    без совпадения артикула не отбрасываем, но помечаем sku_matched=false — решает
 *    ListingIdentityVerifier;
 *  - RUR→RUB, пустая валюта → RUB;
 *  - AggregateOffer с разбросом → price_kind='from', price=low (а не null);
 *  - НДС из priceSpecification.valueAddedTaxIncluded, единица из unitCode.
 */
class StructuredPriceExtractor
{
    /** UN/CEFACT unitCode → наша единица. */
    private const UNIT_CODES = [
        'H87' => 'piece', 'C62' => 'piece', 'EA' => 'piece', 'PCE' => 'piece',
        'MTR' => 'meter', 'KGM' => 'kg', 'LTR' => 'liter', 'SET' => 'set', 'PK' => 'pack', 'PA' => 'pack',
    ];

    /**
     * @param array<int,string> $articleNorms нормализованные артикулы товара
     * @return array{price: float, currency: string, availability: ?string, price_kind: string, vat_included: ?bool,
     *               price_unit: ?string, price_unit_qty: ?float, source: string, sku_matched: bool, evidence: string}|null
     */
    public function extract(string $html, array $articleNorms = []): ?array
    {
        return $this->fromJsonLd($html, $articleNorms)
            ?? $this->fromMicrodata($html)
            ?? $this->fromOpenGraph($html);
    }

    public static function availability(?string $value): ?string
    {
        $v = mb_strtolower(trim((string) $value));
        if ($v === '') {
            return null;
        }
        $v = preg_replace('~^https?://schema\.org/~', '', $v);

        return match (true) {
            in_array($v, ['instock', 'instoreonly', 'onlineonly', 'limitedavailability'], true) => 'in_stock',
            in_array($v, ['preorder', 'presale', 'backorder', 'madetoorder'], true) => 'on_order',
            in_array($v, ['outofstock', 'soldout', 'discontinued'], true) => 'out_of_stock',
            default => null,
        };
    }

    public static function currency(?string $value): string
    {
        $c = mb_strtoupper(trim((string) $value));

        return match ($c) {
            '', 'RUR', 'РУБ', 'Р', '₽' => 'RUB',
            default => preg_match('/^[A-Z]{3}$/', $c) ? $c : 'RUB',
        };
    }

    /** @param array<int,string> $articleNorms */
    private function fromJsonLd(string $html, array $articleNorms): ?array
    {
        if (!preg_match_all('~<script[^>]+type\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $m)) {
            return null;
        }
        $products = [];
        foreach ($m[1] as $raw) {
            $data = json_decode(trim($raw), true);
            if (!is_array($data)) {
                $data = json_decode(trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)), true);
            }
            if (is_array($data)) {
                $this->collectProducts($data, $products);
            }
        }

        $priced = [];
        foreach ($products as $p) {
            $offer = $this->offerOf($p['offers'] ?? null);
            if ($offer) {
                $priced[] = ['node' => $p, 'offer' => $offer, 'matched' => $this->matchesArticle($p, $articleNorms)];
            }
        }
        if ($priced === []) {
            return null;
        }
        if (count($priced) > 1) {
            $priced = array_values(array_filter($priced, fn ($x) => $x['matched']));
            if (count($priced) !== 1) {
                return null; // раздел/«похожие товары» — не угадываем
            }
        }
        $node = $priced[0]['node'];
        $evidence = trim(implode(' | ', array_filter([
            is_string($node['name'] ?? null) ? $node['name'] : null,
            is_scalar($node['sku'] ?? null) ? 'sku ' . $node['sku'] : null,
            is_scalar($node['mpn'] ?? null) ? 'mpn ' . $node['mpn'] : null,
        ])));

        return $priced[0]['offer'] + ['source' => 'json-ld', 'sku_matched' => $priced[0]['matched'], 'evidence' => mb_substr($evidence, 0, 400)];
    }

    /** @param array<int|string,mixed> $data @param array<int,array<string,mixed>> $out */
    private function collectProducts(array $data, array &$out): void
    {
        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $this->collectProducts($item, $out);
                }
            }

            return;
        }
        $types = array_map('strval', (array) ($data['@type'] ?? []));
        if (array_intersect(array_map('mb_strtolower', $types), ['product', 'productmodel', 'individualproduct'])) {
            $out[] = $data;
        }
        foreach (['@graph', 'mainEntity', 'itemListElement', 'item'] as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                $this->collectProducts($data[$k], $out);
            }
        }
    }

    private function offerOf(mixed $offers): ?array
    {
        if (!is_array($offers)) {
            return null;
        }
        $list = array_is_list($offers) ? $offers : [$offers];
        $found = [];
        foreach ($list as $o) {
            if (!is_array($o)) {
                continue;
            }
            $type = mb_strtolower((string) (is_array($o['@type'] ?? null) ? ($o['@type'][0] ?? '') : ($o['@type'] ?? '')));
            $spec = is_array($o['priceSpecification'] ?? null)
                ? (array_is_list($o['priceSpecification']) ? ($o['priceSpecification'][0] ?? []) : $o['priceSpecification'])
                : [];
            $price = PriceNumberParser::parse($o['price'] ?? ($spec['price'] ?? null));
            $kind = 'retail';
            if ($price === null && $type === 'aggregateoffer') {
                $low = PriceNumberParser::parse($o['lowPrice'] ?? null);
                $high = PriceNumberParser::parse($o['highPrice'] ?? null);
                if ($low !== null) {
                    $price = $low;
                    $kind = ($high !== null && abs($high - $low) >= 0.01) ? 'from' : 'retail';
                }
            }
            if ($price === null) {
                continue;
            }
            $vat = $spec['valueAddedTaxIncluded'] ?? ($o['valueAddedTaxIncluded'] ?? null);
            $unitCode = mb_strtoupper((string) ($spec['unitCode'] ?? ($o['eligibleQuantity']['unitCode'] ?? '')));
            $unitQty = $spec['referenceQuantity']['value'] ?? ($o['eligibleQuantity']['value'] ?? null);
            $found[] = [
                'price' => $price,
                'currency' => self::currency((string) ($o['priceCurrency'] ?? ($spec['priceCurrency'] ?? ''))),
                'availability' => self::availability(is_string($o['availability'] ?? null) ? $o['availability'] : null),
                'price_kind' => $kind,
                'vat_included' => is_bool($vat) ? $vat : (is_string($vat) ? in_array(mb_strtolower($vat), ['true', '1'], true) : null),
                'price_unit' => self::UNIT_CODES[$unitCode] ?? null,
                'price_unit_qty' => is_numeric($unitQty) && (float) $unitQty > 1 ? (float) $unitQty : null,
            ];
        }
        $prices = array_unique(array_map(fn ($f) => round($f['price'], 2), $found));

        return count($prices) === 1 ? $found[0] : null;
    }

    /** @param array<string,mixed> $node @param array<int,string> $articleNorms */
    private function matchesArticle(array $node, array $articleNorms): bool
    {
        if ($articleNorms === []) {
            return false;
        }
        $hay = implode(' ', array_filter([
            is_string($node['name'] ?? null) ? $node['name'] : '',
            is_scalar($node['sku'] ?? null) ? (string) $node['sku'] : '',
            is_scalar($node['mpn'] ?? null) ? (string) $node['mpn'] : '',
            is_scalar($node['productID'] ?? null) ? (string) $node['productID'] : '',
            is_scalar($node['gtin13'] ?? null) ? (string) $node['gtin13'] : '',
            is_scalar($node['model'] ?? null) ? (string) $node['model'] : '',
        ]));
        $tokens = ArticleNormalizer::tokens($hay);
        foreach ($articleNorms as $code) {
            if ($code !== '' && isset($tokens[$code])) {
                return true;
            }
        }

        return false;
    }

    private function fromMicrodata(string $html): ?array
    {
        if (!preg_match_all('~<[^>]+itemprop\s*=\s*["\']price["\'][^>]*>([^<]*)~i', $html, $m, PREG_SET_ORDER)) {
            return null;
        }
        $prices = [];
        foreach ($m as $hit) {
            $content = preg_match('~\scontent\s*=\s*["\']([^"\']+)["\']~i', $hit[0], $c) ? $c[1] : HtmlText::toText($hit[1]);
            $p = PriceNumberParser::parse($content);
            if ($p !== null) {
                $prices[(string) round($p, 2)] = $p;
            }
        }
        if (count($prices) !== 1) {
            return null;
        }
        $currency = preg_match('~itemprop\s*=\s*["\']priceCurrency["\'][^>]*content\s*=\s*["\']([A-Za-z]{3})["\']~i', $html, $c)
            || preg_match('~content\s*=\s*["\']([A-Za-z]{3})["\'][^>]*itemprop\s*=\s*["\']priceCurrency["\']~i', $html, $c) ? $c[1] : '';
        $availability = preg_match('~itemprop\s*=\s*["\']availability["\'][^>]*(?:href|content)\s*=\s*["\']([^"\']+)["\']~i', $html, $a)
            || preg_match('~(?:href|content)\s*=\s*["\']([^"\']+)["\'][^>]*itemprop\s*=\s*["\']availability["\']~i', $html, $a) ? self::availability($a[1]) : null;

        return [
            'price' => reset($prices), 'currency' => self::currency($currency), 'availability' => $availability,
            'price_kind' => 'retail', 'vat_included' => null, 'price_unit' => null, 'price_unit_qty' => null,
            'source' => 'microdata', 'sku_matched' => false, 'evidence' => '',
        ];
    }

    private function fromOpenGraph(string $html): ?array
    {
        $meta = function (string $prop) use ($html): ?string {
            $p = preg_quote($prop, '~');
            if (preg_match('~<meta[^>]+property\s*=\s*["\']' . $p . '["\'][^>]+content\s*=\s*["\']([^"\']+)["\']~i', $html, $m)
                || preg_match('~<meta[^>]+content\s*=\s*["\']([^"\']+)["\'][^>]+property\s*=\s*["\']' . $p . '["\']~i', $html, $m)) {
                return $m[1];
            }

            return null;
        };
        $price = PriceNumberParser::parse($meta('product:price:amount') ?? $meta('og:price:amount'));
        if ($price === null) {
            return null;
        }

        return [
            'price' => $price,
            'currency' => self::currency($meta('product:price:currency') ?? $meta('og:price:currency')),
            'availability' => self::availability($meta('product:availability') ?? $meta('og:availability')),
            'price_kind' => 'retail', 'vat_included' => null, 'price_unit' => null, 'price_unit_qty' => null,
            'source' => 'opengraph', 'sku_matched' => false, 'evidence' => '',
        ];
    }
}
