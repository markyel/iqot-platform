<?php

namespace App\Services\Senders;

use App\Support\Senders\SenderPool;
use Illuminate\Support\Facades\DB;

/**
 * Здоровье доменов основного пула ящиков по данным спам-гварда (решение владельца 27.10.2026): отправки и отказы
 * «спам» / «мёртвый адрес» за окно по ящикам домена (bounce_reason из приёма, как SpamRejectGuard). Горит именно
 * домен (урок июля 2026: .ru-домены горят, их .store-близнецы чистые), поэтому пополнение пула и порядок возврата
 * ящиков смотрят на домен, а не на отдельный ящик.
 */
final class DomainHealth
{
    private const CONN = 'reports';

    /**
     * @return array<string,array{sent:int, spam:int, dead:int, spam_pct:float, working:int, disabled:int, off:int}>
     *         домен => отправлено и отказы за окно; ящики: рабочие, отключённые гвардом (ждут возврата), снятые
     */
    public static function byDomain(int $days): array
    {
        $since = now()->subDays($days);
        $senders = SenderPool::onlyMain(DB::connection(self::CONN)->table('senders'))
            ->where(fn ($q) => $q->whereNull('is_personal')->orWhere('is_personal', 0))
            ->get(['id', 'email', 'is_active', 'sending_disabled']);
        $sent = DB::connection(self::CONN)->table('email_queue')->whereNotNull('sent_at')->where('sent_at', '>=', $since)
            ->selectRaw('sender_id, COUNT(*) n')->groupBy('sender_id')->pluck('n', 'sender_id');
        $bounces = [];
        foreach (DB::connection(self::CONN)->table('unidentified_emails')->where('reason', 'bounce')
            ->whereIn('bounce_reason', ['spam', 'permanent'])->where('created_at', '>=', $since)
            ->selectRaw('sender_id, bounce_reason, COUNT(*) n')->groupBy('sender_id', 'bounce_reason')->get() as $b) {
            $bounces[(int) $b->sender_id][$b->bounce_reason] = (int) $b->n;
        }

        $out = [];
        foreach ($senders as $s) {
            $d = self::domainOf((string) $s->email);
            if ($d === '') {
                continue;
            }
            $x = $out[$d] ?? ['sent' => 0, 'spam' => 0, 'dead' => 0, 'spam_pct' => 0.0, 'working' => 0, 'disabled' => 0, 'off' => 0];
            if ((int) $s->is_active !== 1) {
                $x['off']++;
            } elseif ((int) $s->sending_disabled === 1) {
                $x['disabled']++;
            } else {
                $x['working']++;
            }
            $x['sent'] += (int) ($sent[$s->id] ?? 0);
            $x['spam'] += $bounces[$s->id]['spam'] ?? 0;
            $x['dead'] += $bounces[$s->id]['permanent'] ?? 0;
            $out[$d] = $x;
        }
        foreach ($out as $d => $x) {
            $out[$d]['spam_pct'] = $x['sent'] > 0 ? round(100 * $x['spam'] / $x['sent'], 2) : 0.0;
        }

        return $out;
    }

    /**
     * Живой домен: за окно достаточно отправок, чтобы судить, и доля спам-отказов не выше порога.
     *
     * @param array{sent:int, spam_pct:float} $h
     */
    public static function isLive(array $h, float $maxSpamPct, int $minSent): bool
    {
        return $h['sent'] >= $minSent && $h['spam_pct'] <= $maxSpamPct;
    }

    public static function domainOf(string $email): string
    {
        return mb_strtolower(trim(substr((string) strrchr($email, '@'), 1)));
    }
}
