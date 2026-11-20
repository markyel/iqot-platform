<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;

/**
 * Перепроверка выбросов цен (решение владельца 20.11.2026).
 *
 * ListingChecker судит о выбросе в момент проверки страницы — при первом поиске цены товара приходят по одной,
 * и ранние ошибочные цены (M16759: 16 ₽ за контактор при 2 300–7 400 ₽; M00183: 6 804 ₽ за тормозные колодки
 * armtek при 119–201 ₽) проходили без сравнения и становились лучшей ценой у клиента; пересмотр — только при
 * переобходе через 1–2 недели.
 *
 * Здесь (шаг monitoring:refresh-coverage, каждые 15 мин) по ВСЕМ ценам товара: страница с отрывом от медианы больше
 * чем в outlier_ratio раз (при ≥ outlier_min_prices ценах) не выбрасывается, а уходит на перепроверку: «подозрительно»
 * (вне сводки), recheck_reason='outlier', проверка сейчас без кеша. ListingChecker в этом режиме загружает страницу
 * заново и обязательно спрашивает ИИ: другой товар или цена ИИ не сходится с ценой разметки → «чужой товар»;
 * подтверждено → обычная оценка (выброс среди того же варианта), цена запоминается и повторно не перепроверяется.
 */
class OutlierRecheck
{
    /** @return array{products: int, flagged: int} */
    public function sweep(): array
    {
        $min = (int) config('monitoring.sanity.outlier_min_prices', 4);
        $ratio = (float) config('monitoring.sanity.outlier_ratio', 4.0);

        $rows = DB::table('mp_listings')
            ->where('bench', false)->where('status', 'active')->whereNull('recheck_reason')
            ->where('price', '>', 0)
            ->where(fn ($q) => $q->whereNull('currency')->orWhere('currency', 'RUB'))
            ->where(fn ($q) => $q->whereNull('price_kind')->orWhere('price_kind', '<>', 'wholesale_tier'))
            ->get(['id', 'product_id', 'price', 'outlier_checked_price']);

        $flagged = 0;
        $products = [];
        foreach ($rows->groupBy('product_id') as $productId => $group) {
            $prices = $group->mapWithKeys(fn ($r) => [(int) $r->id => (float) $r->price])->all();
            $confirmed = $group->mapWithKeys(fn ($r) => [(int) $r->id => $r->outlier_checked_price !== null ? (float) $r->outlier_checked_price : null])->all();
            $ids = array_values(array_filter(self::outliers($prices, $ratio, $min),
                fn ($id) => !self::alreadyConfirmed($prices[$id], $confirmed[$id] ?? null)));
            if ($ids === []) {
                continue;
            }
            DB::table('mp_listings')->whereIn('id', $ids)->update([
                'status' => 'suspicious',
                'recheck_reason' => 'outlier',
                'next_check_at' => now(),
                'etag' => null,
                'last_modified' => null,
                'content_hash' => null,
                'updated_at' => now(),
            ]);
            // Цена ушла из сводки — курсор выдачи (prices?updated_since) должен это увидеть.
            DB::table('program_items')->where('product_id', $productId)->update(['prices_changed_at' => now()]);
            $flagged += count($ids);
            $products[$productId] = true;
        }

        return ['products' => count($products), 'flagged' => $flagged];
    }

    /**
     * Выбросы по всем ценам товара: отрыв от медианы больше чем в $ratio раз при ≥ $min ценах.
     *
     * @param array<int, float> $prices id листинга => цена
     * @return array<int, int> id листингов-выбросов
     */
    public static function outliers(array $prices, float $ratio, int $min): array
    {
        $values = array_values(array_filter($prices, fn ($p) => $p > 0));
        $n = count($values);
        if ($n < $min) {
            return [];
        }
        sort($values);
        $median = $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
        if ($median <= 0) {
            return [];
        }
        $out = [];
        foreach ($prices as $id => $p) {
            if ($p > 0 && ($p / $median > $ratio || $median / $p > $ratio)) {
                $out[] = (int) $id;
            }
        }

        return $out;
    }

    /** Эта цена уже подтверждена перепроверкой (с точностью до 1%) — не гоняем страницу по кругу. */
    public static function alreadyConfirmed(float $price, ?float $confirmedPrice): bool
    {
        return $confirmedPrice !== null && $confirmedPrice > 0 && abs($price - $confirmedPrice) / $confirmedPrice < 0.01;
    }
}
