<?php

namespace App\Services\Monitoring\Extract;

use App\Models\Monitoring\MpProduct;
use App\Support\Monitoring\ArticleNormalizer;
use App\Support\Monitoring\KeyAttributes;

/**
 * «Это наш товар?» — главный источник ошибок мониторинга (docs/monitoring-f0-spec.md §7.3).
 * Правила (первое сработавшее решает):
 *  1. LLM: аналог / замена → отказ (аналог ≠ наш товар).
 *  2. Ключевой признак наименования расходится (модель или явно по адресу/заголовку/цитатам) → отказ,
 *     даже при артикуле на странице. Затем LLM явно: другой товар (is_same_product=false) → отказ.
 *  3. Артикул товара есть на странице ТОКЕНОМ (или совпал sku разметки) → oem_exact/structured_sku.
 *  4. Серая зона: числовые признаки наименования (D=240, 6 мм) расходятся со страницей → отказ.
 *  5. Серая зона: только LLM is_same_product=true и её цитата есть на странице → llm_verified; иначе отказ
 *     (урок LazyLift: в серой зоне 95% привязок оказывались чужими).
 */
class ListingIdentityVerifier
{
    /** Признаки аналога/замены в адресе или заголовке карточки (рус. и транслит). */
    public const ANALOG_RE = '~(?<![\p{L}])(аналог\p{L}*|analog\p{L}*|заменител\p{L}*|zamenitel\p{L}*|взамен|vzamen|совместим\p{L}*\s+с|sovmestim\p{L}*)(?![\p{L}])~iu';

    /**
     * @param array{sku_matched?: bool}|null $structured результат StructuredPriceExtractor
     * @param array{is_same_product?: ?bool, is_analog?: bool, evidence_identity?: string}|null $llm результат LlmPriceExtractor
     * @return array{accepted: bool, method: ?string, confidence: float, reason: string, evidence: string}
     */
    public function verify(MpProduct $product, string $pageText, ?array $structured, ?array $llm, string $context = ''): array
    {
        $codes = $product->articleCodes();
        $llmEvidence = (string) ($llm['evidence_identity'] ?? '');

        // Аналог по адресу/заголовку карточки («…-analog-gda25005b1…», «Плата … (аналог …)»): наш артикул
        // на такой странице стоит как «аналог чего», а SKU в разметке совпадает — без этой проверки
        // аналог проходил как oem_exact/structured_sku и сбивал лучшую цену (API 15.10.2026: 53 листинга).
        if ($context !== '' && preg_match(self::ANALOG_RE, $context, $am) && !preg_match(self::ANALOG_RE, (string) $product->name)) {
            return $this->reject('analog_marker', $am[0]);
        }
        if ($llm !== null && !empty($llm['is_analog'])) {
            return $this->reject('analog', $llmEvidence);
        }
        // Ключевые признаки наименования (сторона, исполнение, этаж, типоразмер, модель, бренд) расходятся со
        // страницей → другой вариант (M02384: цены китайских отводок BLT/Fermator за отводку OTIS Techna).
        // Признак, о котором страница молчит (часто бренд), — не противоречие.
        // Если на странице НАШ артикул — пока не отказ, а пометка (решение владельца 22.10.2026): у парных гребёнок
        // (GAA453BV5/BV6, BM5/BM6, KONE ECO A/B) сторона в наименовании клиента спорит с артикулом, а зубья и длина
        // совпадают с артикулом на 9 сайтах из 9 — скорее перепутано наименование, цена верная.
        $attrs = (array) ($product->key_attrs ?? []);
        $attrHit = null;
        foreach ((array) ($llm['attrs'] ?? []) as $v) {
            // Модель путается на страницах-списках: «conflict» с цитатой «правый» при нашем «правая» — не противоречие.
            if (($v['verdict'] ?? null) === 'conflict' && !KeyAttributes::sameValue($attrs, (string) ($v['key'] ?? ''), (string) ($v['page'] ?? ''))) {
                $attrHit = trim(($v['key'] ?? '') . ': ' . ($v['page'] ?? ''));
                break;
            }
        }
        $attrHit ??= KeyAttributes::conflictIn($attrs,
            $context . ' ' . (string) ($structured['evidence'] ?? '') . ' ' . $llmEvidence . ' ' . (string) ($llm['evidence_price'] ?? ''));

        $codeHit = null;
        if ($codes !== []) {
            $tokens = ArticleNormalizer::tokens($pageText);
            foreach ($codes as $code) {
                if (isset($tokens[$code])) {
                    $codeHit = $code;
                    break;
                }
            }
        }
        if ($attrHit !== null && $codeHit === null && empty($structured['sku_matched'])) {
            return $this->reject('attr_conflict', $attrHit);
        }
        $codeOnPage = $codeHit !== null || !empty($structured['sku_matched']);
        // Артикул сильнее признака — только для стороны/исполнения (парные гребёнки, решение владельца 22.10.2026).
        // Бренд, модель, размер при совпавшем артикуле — другой товар (23.11.2026: «Плата LEX-C ELEX» → робот-игрушка
        // emotoBOT LEX-C, бренд Just Play; «Направляющие SALSIS L=296» → датчик и башмак SALSIS другой длины).
        if ($attrHit !== null && $codeOnPage && !self::softAttr($attrHit)) {
            return $this->reject('attr_conflict', $attrHit);
        }
        $extraNote = null;
        if ($llm !== null && ($llm['is_same_product'] ?? null) === false && !($codeOnPage && $attrHit !== null)) {
            // Наш код — в заголовке или адресе карточки (главный товар страницы), а модель расхождений не назвала —
            // верим коду, с пометкой (23.11.2026: «Амортизатор купе кабины TAA316BJN3 Otis», «Паллета 606 NCT 1011 мм»
            // снимались как «другой товар»). Короткий цифровой код так не доказывается (armtek «FO 468881 PRO»).
            if (!$codeOnPage || !self::strongCodeIn($codes, $context)) {
                return $this->reject('llm_other_product', $llmEvidence);
            }
            $extraNote = 'llm≠code';
        }
        if (!empty($structured['sku_matched'])) {
            return $this->accept('structured_sku', 0.95, 'sku in markup', $attrHit, $extraNote);
        }
        if ($codeHit !== null) {
            return $this->accept('oem_exact', 0.95, $codeHit, $attrHit, $extraNote);
        }

        // Серая зона (артикула на странице нет): типоразмер расходится — точно не он.
        if ($this->numericSignalsConflict((string) $product->name, $pageText)) {
            return $this->reject('numeric_signals_conflict', '');
        }

        if ($llm !== null && ($llm['is_same_product'] ?? null) === true) {
            // Цитата, по которой модель «опознала» товар, обязана быть на странице: на странице бренда snab-lift
            // (M10697, 19.10.2026) модель вернула дословно НАШЕ название, которого там нет, и цену соседнего товара.
            if (!self::quoteOnPage($llmEvidence, $pageText)) {
                return $this->reject('llm_evidence_not_on_page', $llmEvidence);
            }
            // Артикула нет — модель из наименования должна быть в опознанном названии (цитата, адрес, заголовок).
            if (($miss = KeyAttributes::missingModel($attrs, $llmEvidence . ' ' . $context)) !== null) {
                return $this->reject('attr_missing', $miss);
            }

            return $this->accept('llm_verified', 0.80, $llmEvidence);
        }

        return $this->reject($llm === null ? 'no_article_on_page' : 'llm_unsure', $llmEvidence);
    }

    /**
     * Дословная (с точностью до регистра, ё/е, пунктуации и пробелов) цитата есть в тексте страницы. Допуск — если
     * модель чуть сократила цитату: ≥ 90% слов цитаты есть на странице и все слова с цифрами/латиницей (модели, коды).
     */
    public static function quoteOnPage(string $quote, string $page): bool
    {
        $norm = fn (string $t) => trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('ё', 'е', mb_strtolower($t))));
        $q = $norm($quote);
        if (mb_strlen(str_replace(' ', '', $q)) < 4) {
            return false;
        }
        $p = ' ' . $norm($page) . ' ';
        if (str_contains($p, ' ' . $q . ' ') || str_contains($p, $q)) {
            return true;
        }
        $words = array_values(array_filter(explode(' ', $q), fn ($w) => mb_strlen($w) >= 2));
        if (count($words) < 3) {
            return false;
        }
        $have = array_flip(explode(' ', trim($p)));
        $hit = 0;
        foreach ($words as $w) {
            if (isset($have[$w])) {
                $hit++;
            } elseif (preg_match('/[\p{N}a-z]/u', $w)) {
                return false; // модель/код/число из цитаты не встречается на странице
            }
        }

        return $hit / count($words) >= 0.9;
    }

    /**
     * Числовые признаки наименования (диаметр D=…, Ø…, значения в мм) есть и на странице,
     * но ни одно не совпадает с нашим → другой типоразмер.
     */
    public function numericSignalsConflict(string $name, string $pageText): bool
    {
        foreach ($this->signalPatterns() as $re) {
            $ours = $this->numbers($re, $name);
            if ($ours === []) {
                continue;
            }
            $theirs = $this->numbers($re, $pageText);
            if ($theirs !== [] && array_intersect($ours, $theirs) === []) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function signalPatterns(): array
    {
        return [
            '/(?:\bD|Ø|\bД)\s*=?\s*(\d{2,4})\b/u',          // диаметр шкива/ролика
            '/\b(\d{1,4}(?:[.,]\d+)?)\s*мм\b/u',            // размер в мм
        ];
    }

    /** @return array<int,string> */
    private function numbers(string $re, string $text): array
    {
        if (!preg_match_all($re, $text, $m)) {
            return [];
        }

        return array_values(array_unique(array_map(fn ($v) => str_replace(',', '.', $v), $m[1])));
    }

    /** Признаки, расхождение которых при совпавшем артикуле не отказ, а пометка (сторона/исполнение парных деталей). */
    public const SOFT_ATTR_KEYS = ['сторона', 'исполнение'];

    /** «ключ: значение» расходящегося признака — из мягких? */
    public static function softAttr(string $attrHit): bool
    {
        $key = mb_strtolower(trim(explode(':', $attrHit, 2)[0]));

        return in_array($key, self::SOFT_ATTR_KEYS, true);
    }

    /**
     * Наш код в заголовке/адресе карточки и сам по себе доказателен: ≥ 6 знаков и не короткое число
     * (цифровые OEM вроде 468881 встречаются в артикулах чужих товаров).
     *
     * @param array<int,string> $codes нормализованные коды товара
     */
    public static function strongCodeIn(array $codes, string $context): bool
    {
        if ($codes === [] || trim($context) === '') {
            return false;
        }
        $tokens = ArticleNormalizer::tokens($context);
        foreach ($codes as $code) {
            $code = (string) $code;
            if (!isset($tokens[$code]) || mb_strlen($code) < 6 || (ctype_digit($code) && strlen($code) < 8)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param string|null $attrNote признак, расходящийся при совпавшем артикуле (цену берём, расхождение отмечаем)
     * @param string|null $extraNote прочая пометка (llm≠code — модель сочла другим товаром, принято по коду в заголовке)
     */
    private function accept(string $method, float $confidence, string $evidence, ?string $attrNote = null, ?string $extraNote = null): array
    {
        return ['accepted' => true, 'method' => $method, 'confidence' => $confidence, 'reason' => 'ok', 'evidence' => mb_substr($evidence, 0, 400),
            'attr_note' => $attrNote, 'extra_note' => $extraNote];
    }

    private function reject(string $reason, string $evidence): array
    {
        return ['accepted' => false, 'method' => null, 'confidence' => 0.0, 'reason' => $reason, 'evidence' => mb_substr($evidence, 0, 400), 'attr_note' => null];
    }
}
