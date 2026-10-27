<?php

namespace App\Support\Senders;

use Illuminate\Support\Facades\DB;

/**
 * Суточный потолок писем на SMTP-сервер (аккаунт провайдера), а не на ящик. У хостинга REG.RU лимит 3000 писем за
 * 24 часа на весь аккаунт (VIP — 9000), сверх — «Sender rate overlimit»; аккаунт = свой сервер (server269.hosting.reg.ru),
 * поэтому ключ — smtp_server ящика. Окно — скользящие 24 часа, как у провайдера. Конфиг:
 * services.email_dispatch.smtp_server_daily_caps (EMAILS_SMTP_SERVER_DAILY_CAPS="server269.hosting.reg.ru:2700").
 * Серверы без потолка (Beget и др.) не ограничиваются.
 */
final class SmtpServerCaps
{
    private const CONN = 'reports';

    /** @return array<string,int> smtp_server => потолок писем за 24 ч */
    public static function caps(): array
    {
        $out = [];
        foreach ((array) config('services.email_dispatch.smtp_server_daily_caps', []) as $host => $cap) {
            if ((int) $cap > 0) {
                $out[mb_strtolower((string) $host)] = (int) $cap;
            }
        }

        return $out;
    }

    /** Разбор env «host:cap,host:cap». @return array<string,int> */
    public static function parse(string $raw): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $pair) {
            $pos = strrpos($pair, ':');
            if ($pos !== false && (int) substr($pair, $pos + 1) > 0) {
                $out[mb_strtolower(trim(substr($pair, 0, $pos)))] = (int) substr($pair, $pos + 1);
            }
        }

        return $out;
    }

    /**
     * Остаток потолка по серверам: потолок − отправлено за 24 ч − в полёте (sending)
     * [− уже запланированные на сегодня, если $withPending: план дня и генерация не должны набирать сверх].
     *
     * @return array<string,int> только серверы с потолком
     */
    public static function remaining(bool $withPending = false): array
    {
        $caps = self::caps();
        if ($caps === []) {
            return [];
        }
        $dayEnd = now('Europe/Moscow')->endOfDay()->utc();
        $used = DB::connection(self::CONN)->table('email_queue as eq')
            ->join('senders as s', 's.id', '=', 'eq.sender_id')
            ->whereIn(DB::raw('LOWER(s.smtp_server)'), array_keys($caps))
            ->where(function ($q) use ($withPending, $dayEnd) {
                $q->where('eq.sent_at', '>=', now()->subDay())->orWhere('eq.status', 'sending');
                if ($withPending) {
                    $q->orWhere(fn ($q) => $q->where('eq.status', 'pending')->where('eq.scheduled_at', '<=', $dayEnd))
                        ->orWhere(fn ($q) => $q->where('eq.status', 'error')->whereColumn('eq.retry_count', '<', 'eq.max_retries')
                            ->where('eq.scheduled_at', '<=', $dayEnd));
                }
            })
            ->selectRaw('LOWER(s.smtp_server) h, COUNT(*) n')->groupBy(DB::raw('LOWER(s.smtp_server)'))->pluck('n', 'h');
        $out = [];
        foreach ($caps as $host => $cap) {
            $out[$host] = max(0, $cap - (int) ($used[$host] ?? 0));
        }

        return $out;
    }

    /**
     * Урезать остатки ящиков так, чтобы на каждом сервере с потолком их сумма не превышала остаток сервера:
     * пропорционально, с раздачей остатка от округления ящикам с наибольшим остатком.
     *
     * @param array<int,int> $senderLeft sender_id => остаток дневного лимита ящика
     * @param array<int,string> $senderServer sender_id => smtp_server
     * @param array<string,int> $serverLeft smtp_server => остаток потолка сервера
     * @return array<int,int>
     */
    public static function clip(array $senderLeft, array $senderServer, array $serverLeft): array
    {
        $groups = [];
        foreach ($senderLeft as $id => $left) {
            $host = mb_strtolower((string) ($senderServer[$id] ?? ''));
            if (array_key_exists($host, $serverLeft)) {
                $groups[$host][$id] = $left;
            }
        }
        foreach ($groups as $host => $members) {
            $sum = array_sum($members);
            $budget = max(0, (int) $serverLeft[$host]);
            if ($sum <= $budget) {
                continue;
            }
            $given = 0;
            foreach ($members as $id => $left) {
                $senderLeft[$id] = intdiv($left * $budget, max(1, $sum));
                $given += $senderLeft[$id];
            }
            arsort($members);
            foreach (array_keys($members) as $id) {
                if ($given >= $budget) {
                    break;
                }
                if ($senderLeft[$id] < $members[$id]) {
                    $senderLeft[$id]++;
                    $given++;
                }
            }
        }

        return $senderLeft;
    }

    /**
     * Остатки ящиков с учётом потолков их серверов (smtp_server читается из БД).
     *
     * @param array<int,int> $senderLeft
     * @return array<int,int>
     */
    public static function clipByDb(array $senderLeft, bool $withPending): array
    {
        if ($senderLeft === [] || self::caps() === []) {
            return $senderLeft;
        }
        $servers = DB::connection(self::CONN)->table('senders')->whereIn('id', array_keys($senderLeft))->pluck('smtp_server', 'id')->all();

        return self::clip($senderLeft, $servers, self::remaining($withPending));
    }
}
