<?php

namespace App\Services\Senders;

use Illuminate\Support\Facades\DB;

/**
 * Паузы по отписке на пару «поставщик × наш ящик / наш почтовый домен» (решение владельца 28.10.2026):
 *  - сигнал «не пишите» — пауза с ящика, на который ответил поставщик;
 *  - повторный сигнал на тот же наш почтовый домен (с любого его ящика) — пауза на весь домен;
 *  - после паузы — пишем снова; каждое повторение — пауза на неделю длиннее (7, 14, 21… дней).
 * Общего стопа на поставщика нет. Планировщики не назначают ящик на паузе этому получателю, запись писем
 * (CampaignPersister) его отсеивает, диспетчер придерживает уже стоящие в очереди письма до конца паузы.
 */
class SenderPauses
{
    private const CONN = 'reports';

    /** Длительность паузы в днях для n-го сигнала (первая — base, каждое повторение — +step). */
    public static function durationDays(int $signals, int $base, int $step): int
    {
        return max(1, $base) + max(0, $step) * max(0, $signals - 1);
    }

    public static function domainOf(string $email): string
    {
        $email = mb_strtolower(trim($email));

        return (string) substr((string) strrchr($email, '@'), 1);
    }

    /**
     * Сигнал отписки: пауза ящика и — на повторном сигнале по домену — пауза домена.
     *
     * @return array{mailbox_until:string, mailbox_signals:int, domain_until:?string, domain_signals:int, domain:string}
     */
    public function record(int $supplierId, string $recipientEmail, int $senderId, string $senderEmail, ?string $reason): array
    {
        $base = (int) config('services.email_unsubscribe.pause_days', 7);
        $step = (int) config('services.email_unsubscribe.pause_step_days', 7);
        $recipient = mb_strtolower(trim($recipientEmail));
        $domain = self::domainOf($senderEmail);
        $reason = $reason !== null ? mb_substr($reason, 0, 255) : null;

        $mailbox = $this->bump($supplierId, $recipient, 'mailbox', 'm:' . $senderId, $senderId, $domain, $reason, $base, $step);
        $out = ['mailbox_until' => $mailbox['until'], 'mailbox_signals' => $mailbox['signals'], 'domain_until' => null,
            'domain_signals' => 0, 'domain' => $domain];

        // Сигналов этому поставщику с ящиков домена всего (с текущим): со второго — пауза на домен.
        $onDomain = (int) DB::connection(self::CONN)->table('supplier_sender_pauses')
            ->where('supplier_id', $supplierId)->where('scope', 'mailbox')->where('sender_domain', $domain)->sum('signals');
        if ($domain !== '' && $onDomain >= 2) {
            $d = $this->bump($supplierId, $recipient, 'domain', 'd:' . $domain, null, $domain, $reason, $base, $step);
            $out['domain_until'] = $d['until'];
            $out['domain_signals'] = $d['signals'];
        }

        return $out;
    }

    /** @return array{until:string, signals:int} */
    private function bump(int $supplierId, string $recipient, string $scope, string $key, ?int $senderId, string $domain,
        ?string $reason, int $base, int $step): array
    {
        $db = DB::connection(self::CONN);
        $now = now();
        $row = $db->table('supplier_sender_pauses')->where('supplier_id', $supplierId)->where('pause_key', $key)->first(['id', 'signals']);
        $signals = (int) ($row->signals ?? 0) + 1;
        $until = $now->copy()->addDays(self::durationDays($signals, $base, $step))->toDateTimeString();
        $data = ['recipient_email' => $recipient, 'scope' => $scope, 'sender_id' => $senderId, 'sender_domain' => $domain,
            'signals' => $signals, 'paused_until' => $until, 'last_signal_at' => $now, 'last_reason' => $reason, 'updated_at' => $now];
        if ($row) {
            $db->table('supplier_sender_pauses')->where('id', $row->id)->update($data);
        } else {
            $db->table('supplier_sender_pauses')->insert($data + ['supplier_id' => $supplierId, 'pause_key' => $key, 'created_at' => $now]);
        }

        return ['until' => $until, 'signals' => $signals];
    }

    /**
     * Действующие паузы по адресам получателей.
     *
     * @param string[]|null $emails нормализованные адреса; null — все
     * @return array<string,array{m:array<int,true>, d:array<string,true>}>
     */
    public function active(?array $emails = null): array
    {
        if ($emails === []) {
            return [];
        }
        $q = DB::connection(self::CONN)->table('supplier_sender_pauses')->where('paused_until', '>', now());
        $out = [];
        $chunks = $emails === null ? [null] : array_chunk(array_values(array_unique($emails)), 1000);
        foreach ($chunks as $chunk) {
            $cq = clone $q;
            if ($chunk !== null) {
                $cq->whereIn('recipient_email', $chunk);
            }
            foreach ($cq->get(['recipient_email', 'scope', 'sender_id', 'sender_domain']) as $r) {
                $e = (string) $r->recipient_email;
                $out[$e] ??= ['m' => [], 'd' => []];
                if ($r->scope === 'mailbox' && $r->sender_id !== null) {
                    $out[$e]['m'][(int) $r->sender_id] = true;
                } elseif ($r->scope === 'domain') {
                    $out[$e]['d'][(string) $r->sender_domain] = true;
                }
            }
        }

        return $out;
    }

    /** Ящик на паузе для получателя (по самому ящику или его домену). @param array<string,array{m:array,d:array}> $active */
    public static function blocks(array $active, string $recipientEmail, int $senderId, string $senderEmail): bool
    {
        $p = $active[mb_strtolower(trim($recipientEmail))] ?? null;

        return $p !== null && (isset($p['m'][$senderId]) || isset($p['d'][self::domainOf($senderEmail)]));
    }

    /**
     * Пары «ящик|получатель» на паузе — в формате ротации планировщиков (DayPlanAssigner $senderRecent,
     * VerificationMailer $pairs).
     *
     * @param array<string,array{m:array,d:array}> $active
     * @param array<int,string> $senderEmails sender_id => адрес ящика
     * @return array<string,true>
     */
    public static function pairs(array $active, array $senderEmails): array
    {
        $out = [];
        foreach ($active as $recipient => $p) {
            foreach ($senderEmails as $sid => $email) {
                if (isset($p['m'][$sid]) || isset($p['d'][self::domainOf((string) $email)])) {
                    $out[$sid . '|' . $recipient] = true;
                }
            }
        }

        return $out;
    }

    /** @param int[] $ids @return array<int,string> sender_id => адрес */
    public static function senderEmails(array $ids): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table('senders')->whereIn('id', $chunk)->pluck('email', 'id') as $id => $e) {
                $out[(int) $id] = mb_strtolower(trim((string) $e));
            }
        }

        return $out;
    }
}
