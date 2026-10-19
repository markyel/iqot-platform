<?php

namespace App\Services\Monitoring\Extract;

use App\Models\Monitoring\MpProduct;
use App\Support\Monitoring\ArticleNormalizer;

/**
 * «Это наш товар?» — главный источник ошибок мониторинга (docs/monitoring-f0-spec.md §7.3).
 * Правила (первое сработавшее решает):
 *  1. LLM: аналог / замена → отказ (аналог ≠ наш товар).
 *  2. LLM явно: другой товар (is_same_product=false) → отказ (бренд/тип/параметры).
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
        if ($llm !== null && ($llm['is_same_product'] ?? null) === false) {
            return $this->reject('llm_other_product', $llmEvidence);
        }
        if (!empty($structured['sku_matched'])) {
            return $this->accept('structured_sku', 0.95, 'sku in markup');
        }
        if ($codes !== []) {
            $tokens = ArticleNormalizer::tokens($pageText);
            foreach ($codes as $code) {
                if (isset($tokens[$code])) {
                    return $this->accept('oem_exact', 0.95, $code);
                }
            }
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

    private function accept(string $method, float $confidence, string $evidence): array
    {
        return ['accepted' => true, 'method' => $method, 'confidence' => $confidence, 'reason' => 'ok', 'evidence' => mb_substr($evidence, 0, 400)];
    }

    private function reject(string $reason, string $evidence): array
    {
        return ['accepted' => false, 'method' => null, 'confidence' => 0.0, 'reason' => $reason, 'evidence' => mb_substr($evidence, 0, 400)];
    }
}
