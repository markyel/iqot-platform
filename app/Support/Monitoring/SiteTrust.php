<?php

namespace App\Support\Monitoring;

use App\Services\Monitoring\Hybrid\TrustPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Надёжность цены с сайта по итогам сверок КП↔сайт (решения владельца 2026-10-27). Одно определение для выдачи
 * клиенту (API: сводка/best_price, флаг doubtful) и для покрытия позиции в Гибриде (RfqPlanner: сколько
 * достоверных цен уже есть), чтобы «сомнительно для клиента» и «не закрывает позицию» совпадали:
 *  - доверяем сайту — цена надёжна;
 *  - иногда ошибается (непогашенное расхождение, consecutive_mismatch > 0) — цена сомнительная, кроме
 *    индивидуально подтверждённой (match); такие сайты чаще проверяются (TrustPolicy);
 *  - в основном неправда (пауза TrustPolicy, blocked_reason=verify_mismatch) — данным сайта не верим, всё спрашиваем;
 *  - цена, опровергнутая КП (price_mismatch), сама не надёжна — вместо неё идёт цена КП.
 */
final class SiteTrust
{
    /** Сайт на паузе из-за расхождений цен (TrustPolicy, правило 3). */
    public static function paused(?string $blockedReason, mixed $blockedUntil): bool
    {
        if ($blockedReason !== TrustPolicy::PAUSE_REASON || $blockedUntil === null || $blockedUntil === '') {
            return false;
        }

        return ($blockedUntil instanceof \DateTimeInterface ? Carbon::instance($blockedUntil) : Carbon::parse((string) $blockedUntil))->isFuture();
    }

    /** Доверие к сайту понижено: непогашенное расхождение цены или пауза. */
    public static function doubtful(int $consecutiveMismatch, bool $paused): bool
    {
        return $consecutiveMismatch > 0 || $paused;
    }

    /** Цена листинга надёжна как цена сайта (идёт в сводку клиенту и закрывает позицию). */
    public static function reliable(?string $lastOutcome, bool $siteDoubtful, bool $sitePaused): bool
    {
        if ($sitePaused || $lastOutcome === 'price_mismatch') {
            return false;
        }

        return !$siteDoubtful || $lastOutcome === 'match';
    }

    /**
     * Исход последней сверки по листингам.
     *
     * @param int[] $listingIds
     * @return array<int,string> listing_id => outcome
     */
    public static function lastOutcomes(array $listingIds): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_filter(array_map('intval', $listingIds)))), 1000) as $chunk) {
            foreach (DB::table('mp_verifications')->whereIn('listing_id', $chunk)->orderBy('verified_at')->orderBy('id')
                ->get(['listing_id', 'outcome']) as $v) {
                $out[(int) $v->listing_id] = (string) $v->outcome; // последняя по времени перекрывает
            }
        }

        return $out;
    }
}
