<?php

namespace App\Services\Monitoring\Web;

use Illuminate\Support\Facades\DB;

/**
 * Атомарный лимит частоты запросов к домену (вежливый обход). Слот занимается
 * CAS-UPDATE по mp_domain_slots.next_allowed_at — как reserveSlot() у ящиков. Если
 * слот занят, вызывающий не ждёт (без usleep), а откладывает задачу на retryAfterMs.
 */
class DomainSlots
{
    /**
     * @return int 0 — слот наш, можно идти; >0 — через сколько мс повторить
     */
    public function reserve(string $domain, int $intervalMs): int
    {
        $domain = mb_substr(mb_strtolower($domain), 0, 191);
        DB::statement(
            'INSERT IGNORE INTO mp_domain_slots (domain, next_allowed_at) VALUES (?, NOW(3) - INTERVAL 1 SECOND)',
            [$domain]
        );

        $affected = DB::update(
            'UPDATE mp_domain_slots SET next_allowed_at = NOW(3) + INTERVAL ? MICROSECOND
             WHERE domain = ? AND next_allowed_at <= NOW(3)',
            [max(0, $intervalMs) * 1000, $domain]
        );
        if ($affected > 0) {
            return 0;
        }

        $wait = DB::selectOne(
            'SELECT GREATEST(0, TIMESTAMPDIFF(MICROSECOND, NOW(3), next_allowed_at)) / 1000 AS ms FROM mp_domain_slots WHERE domain = ?',
            [$domain]
        );

        return max(1, (int) ceil((float) ($wait->ms ?? $intervalMs)));
    }
}
