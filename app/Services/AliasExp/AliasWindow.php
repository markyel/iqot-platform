<?php

namespace App\Services\AliasExp;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Рабочие часы ветки «ящик на батч»: будни 9:00–18:00 МСК (решение владельца 16.10.2026, уже окна основной рассылки)
 * и расписание писем ящика — «живой» темп: шаг = пауза ящика (300 с) + случайная добавка, только внутри окна.
 */
class AliasWindow
{
    public static function isOpen(?CarbonInterface $at = null): bool
    {
        $cfg = config('services.alias_exp');
        $t = CarbonImmutable::instance($at ?? now())->setTimezone((string) $cfg['window_timezone']);

        return $t->isWeekday() && $t->hour >= (int) $cfg['window_start_hour'] && $t->hour < (int) $cfg['window_end_hour'];
    }

    /**
     * Слоты отправки (UTC) для $n писем, начиная с $from: по одному на шаг, вне окна — перенос на начало
     * следующего рабочего окна. Диспетчер всё равно держит паузу reserveSlot'ом и пейсинг получателя,
     * слоты лишь размазывают письма по дням и не дают ящику «отстреляться» пачкой.
     *
     * @return CarbonImmutable[]
     */
    public static function slots(int $n, ?CarbonInterface $from = null, int $jitterMax = 90): array
    {
        $cfg = config('services.alias_exp');
        $tz = (string) $cfg['window_timezone'];
        $step = max(60, (int) $cfg['send_delay_seconds']);
        $t = self::align(CarbonImmutable::instance($from ?? now())->setTimezone($tz));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $t->utc();
            $t = self::align($t->addSeconds($step + random_int(0, max(0, $jitterMax))));
        }

        return $out;
    }

    private static function align(CarbonImmutable $t): CarbonImmutable
    {
        $cfg = config('services.alias_exp');
        $start = (int) $cfg['window_start_hour'];
        $end = (int) $cfg['window_end_hour'];
        for ($guard = 0; $guard < 14; $guard++) {
            if ($t->isWeekday() && $t->hour >= $start && $t->hour < $end) {
                return $t;
            }
            $t = ($t->isWeekday() && $t->hour < $start)
                ? $t->setTime($start, random_int(0, 9), random_int(0, 59))
                : $t->addDay()->setTime($start, random_int(0, 9), random_int(0, 59));
        }

        return $t;
    }
}
