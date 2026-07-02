<?php

namespace App\Services\Generate;

use Illuminate\Support\Facades\DB;

/**
 * Остатки дневных лимитов прогрева (Phase 3b, config services.email_warmup).
 *
 * «Израсходовано сегодня» ящиком = письма, отправленные в текущие МСК-сутки
 * (sent_at), ПЛЮС уже сгенерированные и уходящие сегодня (pending/sending, а также
 * error с ретраем, scheduled_at в пределах суток). Придержанная волна 2
 * (scheduled_at=2037, CampaignPersister::HELD_UNTIL) сегодня не уходит — не считается.
 * Сутки — МСК, как в emails:warmup-ramp.
 */
class SenderDailyCapacity
{
    private const CONN = 'reports';

    public function enabled(): bool
    {
        return (bool) config('services.email_warmup.enabled', false);
    }

    /**
     * Остаток дневного лимита по каждому ящику: max(0, daily_limit − израсходовано).
     *
     * @param array<int,int> $senderIds
     * @return array<int,int> sender_id => остаток
     */
    public function remainingMap(array $senderIds): array
    {
        $ids = $this->intList($senderIds);
        if ($ids === []) {
            return [];
        }

        $start = (int) config('services.email_warmup.start', 30);
        $limits = DB::connection(self::CONN)->table('senders')
            ->whereIn('id', $ids)
            ->pluck('daily_limit', 'id');

        $used = $this->usedTodayBySender($ids);

        $map = [];
        foreach ($ids as $id) {
            $limit = max(1, (int) ($limits[$id] ?? $start));
            $map[$id] = max(0, $limit - (int) ($used[$id] ?? 0));
        }
        return $map;
    }

    /**
     * Остаток глобального потолка платформы (global_daily_cap) на текущие МСК-сутки.
     * Потолок <= 0 в конфиге = без ограничения.
     */
    public function globalRemaining(): int
    {
        $cap = (int) config('services.email_warmup.global_daily_cap', 10000);
        if ($cap <= 0) {
            return PHP_INT_MAX;
        }

        $used = (int) $this->usedTodayQuery()->count();
        return max(0, $cap - $used);
    }

    /**
     * Суммарный остаток лимитов всех ящиков пула генерации (is_active=1,
     * sending_disabled=0) — «есть ли вообще кому слать» для выпуска отсрочек.
     */
    public function poolRemainingTotal(): int
    {
        $ids = DB::connection(self::CONN)->table('senders')
            ->where('is_active', 1)->where('sending_disabled', 0)
            ->pluck('id')->map(fn ($v) => (int) $v)->all();

        return array_sum($this->remainingMap($ids));
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,int> sender_id => израсходовано сегодня
     */
    private function usedTodayBySender(array $ids): array
    {
        $rows = $this->usedTodayQuery()
            ->whereIn('sender_id', $ids)
            ->groupBy('sender_id')
            ->selectRaw('sender_id, COUNT(*) as c')
            ->get();

        $used = [];
        foreach ($rows as $r) {
            $used[(int) $r->sender_id] = (int) $r->c;
        }
        return $used;
    }

    /**
     * База «израсходовано сегодня»: отправленные в МСК-сутки + уходящие сегодня
     * (pending/sending + error с ретраем, scheduled_at <= конца суток).
     */
    private function usedTodayQuery(): \Illuminate\Database\Query\Builder
    {
        $tz = 'Europe/Moscow';
        $dayStart = now($tz)->startOfDay()->utc();
        $dayEnd = now($tz)->endOfDay()->utc();

        return DB::connection(self::CONN)->table('email_queue')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('sent_at', [$dayStart, $dayEnd])
                    ->orWhere(function ($q2) use ($dayEnd) {
                        $q2->whereIn('status', ['pending', 'sending'])
                            ->where('scheduled_at', '<=', $dayEnd);
                    })
                    ->orWhere(function ($q2) use ($dayEnd) {
                        $q2->where('status', 'error')
                            ->whereColumn('retry_count', '<', 'max_retries')
                            ->where('scheduled_at', '<=', $dayEnd);
                    });
            });
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,int>
     */
    private function intList(array $values): array
    {
        $out = [];
        foreach ($values as $v) {
            $n = (int) $v;
            if ($n > 0 && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        return $out;
    }
}
