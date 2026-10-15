<?php

namespace App\Services\Monitoring\Hybrid;

/**
 * Сверка цены КП с ценой сайта (docs/monitoring-design.md §2.2). Сначала — чья ошибка:
 * доверие к сайту снижает ТОЛЬКО расхождение цены на тот же товар (решение владельца
 * 2026-10-14), а «не тот товар» и «разная база цены» — нет.
 *
 *  match          |КП − сайт| / сайт ≤ 5% (после приведения НДС, если обе стороны известны);
 *  basis_diff     разница ≈ НДС 20% при неизвестной базе или кратна (×2…×100 — упаковка/метраж);
 *  match_error    КП предложено как аналог/замена/другой артикул (наша ошибка сопоставления);
 *  inconclusive   разные валюты, нет одной из цен;
 *  price_mismatch остальное — цена сайта на тот же товар устарела.
 */
final class PriceComparator
{
    public const MATCH_TOLERANCE = 0.05;

    private const VAT = 1.2;

    private const ANALOG_RE = '~аналог|замен|взамен|альтернатив|другой\s+артикул|другая\s+модель|не\s+тот|отличается|analog|replacement|substitute|equivalent~iu';

    /**
     * @param array{notes?: ?string, offer_type?: ?string} $hints из AI-разбора КП
     * @return array{outcome: string, diff_pct: ?float, note: ?string}
     */
    public static function compare(
        ?float $sitePrice, ?bool $siteVat, ?string $siteCurrency,
        ?float $kpPrice, ?bool $kpVat, ?string $kpCurrency,
        array $hints = [],
    ): array {
        if ($sitePrice === null || $sitePrice <= 0 || $kpPrice === null || $kpPrice <= 0) {
            return ['outcome' => 'inconclusive', 'diff_pct' => null, 'note' => 'нет одной из цен'];
        }
        $sc = strtoupper((string) ($siteCurrency ?: 'RUB'));
        $kc = strtoupper((string) ($kpCurrency ?: 'RUB'));
        if (in_array($sc, ['RUR'], true)) {
            $sc = 'RUB';
        }
        if (in_array($kc, ['RUR'], true)) {
            $kc = 'RUB';
        }
        if ($sc !== $kc) {
            return ['outcome' => 'inconclusive', 'diff_pct' => null, 'note' => "валюты {$sc}/{$kc}"];
        }

        if (self::isAnalog($hints)) {
            return ['outcome' => 'match_error', 'diff_pct' => self::diff($sitePrice, $kpPrice), 'note' => 'КП на аналог/замену'];
        }

        // База НДС: обе стороны известны и различаются — приводим КП к базе сайта.
        $kp = $kpPrice;
        $note = null;
        if ($siteVat !== null && $kpVat !== null && $siteVat !== $kpVat) {
            $kp = $siteVat ? $kpPrice * self::VAT : $kpPrice / self::VAT;
            $note = 'КП приведено к базе НДС сайта';
        }
        $diff = self::diff($sitePrice, $kp);
        if ($diff <= self::MATCH_TOLERANCE * 100) {
            return ['outcome' => 'match', 'diff_pct' => $diff, 'note' => $note];
        }

        $ratio = max($kp, $sitePrice) / min($kp, $sitePrice);
        if (($siteVat === null || $kpVat === null) && abs($ratio - self::VAT) <= 0.02) {
            return ['outcome' => 'basis_diff', 'diff_pct' => $diff, 'note' => 'разница ≈ НДС 20%, база одной из цен неизвестна'];
        }
        $n = round($ratio);
        if ($n >= 2 && $n <= 100 && abs($ratio - $n) / $n <= 0.02) {
            return ['outcome' => 'basis_diff', 'diff_pct' => $diff, 'note' => "цены кратны (×{$n}) — упаковка/метраж/количество"];
        }

        return ['outcome' => 'price_mismatch', 'diff_pct' => $diff, 'note' => $note];
    }

    /** КП предложено как аналог/замена (по разметке AI или тексту примечания) — не цена нашего товара. */
    public static function isAnalog(array $hints): bool
    {
        return in_array(mb_strtolower((string) ($hints['offer_type'] ?? '')), ['analog', 'alternative', 'replacement'], true)
            || (bool) preg_match(self::ANALOG_RE, (string) ($hints['notes'] ?? ''));
    }

    private static function diff(float $site, float $kp): float
    {
        return round(abs($kp - $site) / $site * 100, 3);
    }
}
