<?php

namespace App\Services\Monitoring\Hybrid;

use App\Models\Monitoring\MpSite;

/**
 * Уровень доверия к сайту и частота выборки (docs/monitoring-design.md §2.3, решения владельца
 * 2026-10-14): 0 — новый (первая проверка 3–5 позиций), 1 — 1 из 10, 2 — 1 из 50, 3 — 1 из 100.
 * Подъём — 3 совпадения подряд. Расхождение ЦЕНЫ на тот же товар — минус 2 уровня, второе подряд —
 * сразу 0. Ошибка сопоставления, разная база цены, наличие, неясное — уровень не трогают.
 * Доверенный сайт проверяется не реже раза в 20 дней.
 *
 * Правило 3 (решение владельца 2026-10-27): если из «большого пула» ценовых сверок почти все цены
 * мимо — мониторинг сайта ставим на паузу на месяц (переоценка позже).
 */
final class TrustPolicy
{
    public const SAMPLE_EVERY = [0 => 1, 1 => 10, 2 => 50, 3 => 100];

    public const FIRST_CHECK_ITEMS = 5;

    public const MAX_LEVEL = 3;

    public const PROMOTE_AFTER = 3;

    public const RECHECK_DAYS = 20;

    /** Пауза сайта: пул ценовых сверок (match + price_mismatch) ≥ 5 и доля расхождений ≥ 80% → пауза 30 дней. */
    public const PAUSE_MIN_POOL = 5;

    public const PAUSE_MISMATCH_SHARE = 0.8;

    public const PAUSE_DAYS = 30;

    public const PAUSE_REASON = 'verify_mismatch';

    /**
     * Применить исход сверки к сайту (без сохранения).
     *
     * @return array{level_before: int, level_after: int, paused: bool}
     */
    public static function apply(MpSite $site, string $outcome): array
    {
        $before = (int) $site->trust_level;
        $level = $before;
        switch ($outcome) {
            case 'match':
                $site->verified_ok = (int) $site->verified_ok + 1;
                $site->consecutive_ok = (int) $site->consecutive_ok + 1;
                $site->consecutive_mismatch = 0;
                if ($site->consecutive_ok >= self::PROMOTE_AFTER && $level < self::MAX_LEVEL) {
                    $level++;
                    $site->consecutive_ok = 0;
                }
                if (($site->trust_scope ?? 'none') === 'none') {
                    $site->trust_scope = 'all';
                }
                break;
            case 'price_mismatch':
                $site->verified_mismatch = (int) $site->verified_mismatch + 1;
                $site->consecutive_mismatch = (int) $site->consecutive_mismatch + 1;
                $site->consecutive_ok = 0;
                $level = $site->consecutive_mismatch >= 2 ? 0 : max(0, $level - 2);
                break;
            default:
                // match_error / basis_diff / availability / inconclusive — уровень не трогаем.
                break;
        }
        $site->trust_level = $level;
        $site->sample_every = self::SAMPLE_EVERY[$level];
        $site->last_verified_at = now();
        $site->next_verification_due_at = now()->addDays(self::RECHECK_DAYS);

        // Правило 3: большой пул ценовых сверок и почти всё мимо → пауза мониторинга сайта на месяц.
        $pool = (int) $site->verified_ok + (int) $site->verified_mismatch;
        $paused = false;
        if ($pool >= self::PAUSE_MIN_POOL && (int) $site->verified_mismatch / $pool >= self::PAUSE_MISMATCH_SHARE) {
            $until = now()->addDays(self::PAUSE_DAYS);
            if ($site->blocked_until === null || $site->blocked_until->lt($until)) {
                $site->blocked_until = $until;
                $site->blocked_reason = self::PAUSE_REASON;
            }
            $paused = true;
        }

        return ['level_before' => $before, 'level_after' => $level, 'paused' => $paused];
    }

    /**
     * Сколько позиций сайта взять в выборку из N найденных/обновлённых за цикл.
     * Уровень 0 — первая проверка на 3–5 позициях; выше — 1 из sample_every, но не меньше
     * одной, если подошёл срок обязательной проверки (раз в 20 дней).
     */
    public static function sampleSize(MpSite $site, int $candidates): int
    {
        if ($candidates <= 0) {
            return 0;
        }
        $level = (int) $site->trust_level;
        if ($level === 0) {
            return min($candidates, self::FIRST_CHECK_ITEMS);
        }
        $n = intdiv($candidates, self::SAMPLE_EVERY[$level] ?? 100);
        $due = $site->next_verification_due_at === null || $site->next_verification_due_at->isPast();

        return min($candidates, max($n, $due ? 1 : 0));
    }
}
