<?php

namespace App\Services\Dispatch;

use App\Models\Reports\RecipientMailbox;
use App\Support\Senders\SenderPool;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Изолированные дневные лимиты писем на получателя по каналам (решение владельца 19.10.2026):
 *  - main       — запросы КП по заявкам: адаптивный кап адресата (recipient_mailboxes.daily_cap, база 10);
 *  - alias      — эксперимент «ящик на батч» (ящики senders.pool='alias'): доля от капа заявок, не меньше 1;
 *  - monitoring — письма мониторинга (батчи mp_asks: проверки цен, «по запросу», пул — с обычных ящиков и с ящиков
 *                 ветки «ящик на батч»): доля капа адресата (решение владельца 27.10.2026) — есть письма заявок этому
 *                 адресату сегодня (конкуренция) — до 50%, нет — весь кап; не меньше прежнего фиксированного.
 * Каналы не съедают лимит друг друга; общим остаётся только минимальный интервал между любыми письмами
 * одному адресату (recipient_mailboxes.last_dispatched_at, пол recipient_interval_min_seconds).
 * Расход канала за сутки (МСК) — по email_queue: отправленные сегодня + взятые в работу (sending).
 */
class RecipientChannels
{
    public const MAIN = 'main';
    public const ALIAS = 'alias';
    public const MONITORING = 'monitoring';

    private const CONN = 'reports';
    private const DONE = ['sending', 'sent', 'opened', 'replied', 'reply_processed', 'in_conversation', 'completed'];

    /** @var array<int,true> */
    private array $aliasIds;

    /** @var array<int,true>|null */
    private ?array $monitoringBatches = null;

    public function __construct()
    {
        $this->aliasIds = SenderPool::aliasIds();
    }

    /** @return array<int,true> id ящиков эксперимента */
    public function aliasIds(): array
    {
        return $this->aliasIds;
    }

    /** @return array<int,true> батчи писем мониторинга (БД iqot, mp_asks — за 60 дней) */
    public function monitoringBatchIds(): array
    {
        if ($this->monitoringBatches === null) {
            try {
                $this->monitoringBatches = array_fill_keys(DB::table('mp_asks')->whereNotNull('batch_id')
                    ->where('created_at', '>=', now()->subDays(60))->distinct()->pluck('batch_id')
                    ->map(fn ($v) => (int) $v)->all(), true);
            } catch (\Throwable) {
                $this->monitoringBatches = [];
            }
        }

        return $this->monitoringBatches;
    }

    public function channel(int $senderId, ?int $batchId): string
    {
        // Батч мониторинга — канал мониторинга, с какого бы ящика он ни шёл (в т.ч. с ящика ветки «ящик на батч»).
        if ($batchId !== null && isset($this->monitoringBatchIds()[$batchId])) {
            return self::MONITORING;
        }

        return isset($this->aliasIds[$senderId]) ? self::ALIAS : self::MAIN;
    }

    /**
     * Дневной кап канала для адресата (кап заявок — адаптивный кап адресата). $contended — у адресата сегодня есть
     * письма заявок (отправленные или ожидающие): мониторингу тогда до половины капа, иначе весь (решение 27.10.2026).
     */
    public static function cap(string $channel, int $mainCap, bool $contended = true): int
    {
        $cfg = (array) config('services.email_dispatch.channels', []);

        return match ($channel) {
            self::ALIAS => max(1, (int) ceil($mainCap * (float) ($cfg['alias_share'] ?? 0.2))),
            self::MONITORING => max(max(0, (int) ($cfg['monitoring_cap'] ?? 2)), (int) ceil($mainCap * (float) ($contended
                ? ($cfg['monitoring_share_contended'] ?? 0.5) : ($cfg['monitoring_share_free'] ?? 1.0)))),
            default => $mainCap,
        };
    }

    /**
     * Конкуренция за адресата: сегодня ему уже ушли или ждут отправки письма заявок (канал main).
     *
     * @param array<string,array<string,array{n:int,last:?string}>> $usage usageToday()
     * @param array<string,array<string,int>> $pending pending()
     */
    public static function contended(array $usage, array $pending, string $email): bool
    {
        return (int) ($usage[$email][self::MAIN]['n'] ?? 0) + (int) ($pending[$email][self::MAIN] ?? 0) > 0;
    }

    /**
     * Кап заявок по адресатам + блокировка. @param string[] $emails нормализованные
     * @return array<string,array{cap:int, blocked:bool, last:?string}>
     */
    public function recipientMeta(array $emails): array
    {
        $base = (int) config('services.email_dispatch.recipient_daily_cap', 10);
        $out = array_fill_keys($emails, ['cap' => $base, 'blocked' => false, 'last' => null]);
        foreach (array_chunk($emails, 1000) as $chunk) {
            foreach (DB::connection(self::CONN)->table('recipient_mailboxes')->whereIn('email', $chunk)
                ->get(['email', 'daily_cap', 'is_blocked', 'last_dispatched_at']) as $r) {
                $out[$r->email] = ['cap' => $r->daily_cap !== null ? (int) $r->daily_cap : $base,
                    'blocked' => (int) $r->is_blocked === 1, 'last' => $r->last_dispatched_at];
            }
        }

        return $out;
    }

    /**
     * Расход за сегодняшние МСК-сутки по каналам: [email][channel] => ['n' => писем, 'last' => время последнего (UTC)].
     *
     * @param string[] $emails нормализованные
     * @return array<string,array<string,array{n:int,last:?string}>>
     */
    public function usageToday(array $emails): array
    {
        $dayStart = Carbon::parse(RecipientMailbox::recipientDay(), 'Europe/Moscow')->startOfDay()->utc()->toDateTimeString();
        $out = [];
        foreach (array_chunk(array_values(array_unique($emails)), 1000) as $chunk) {
            $rows = DB::connection(self::CONN)->table('email_queue')
                ->whereIn(DB::raw('LOWER(to_email)'), $chunk)
                ->whereIn('status', self::DONE)
                ->where(fn ($q) => $q->where('sent_at', '>=', $dayStart)->orWhere(fn ($q) => $q->where('status', 'sending')->where('updated_at', '>=', $dayStart)))
                ->selectRaw('LOWER(to_email) r, sender_id, batch_id, COUNT(*) n, MAX(COALESCE(sent_at, updated_at)) last')
                ->groupBy(DB::raw('LOWER(to_email)'), 'sender_id', 'batch_id')->get();
            foreach ($rows as $row) {
                $c = $this->channel((int) $row->sender_id, $row->batch_id !== null ? (int) $row->batch_id : null);
                $cur = $out[$row->r][$c] ?? ['n' => 0, 'last' => null];
                $out[$row->r][$c] = ['n' => $cur['n'] + (int) $row->n, 'last' => max($cur['last'], $row->last)];
            }
        }

        return $out;
    }

    /**
     * Ожидающие письма по каналам: [email][channel] => число. $until — горизонт (UTC) для писем эксперимента
     * (у них расписание по дням), для остальных — уже наступившие (или до $mainUntil, если задан).
     *
     * @return array<string,array<string,int>>
     */
    public function pending(?string $aliasUntil = null, ?string $mainUntil = null, ?array $onlyEmails = null): array
    {
        $aliasList = array_keys($this->aliasIds) ?: [0];
        $q = DB::connection(self::CONN)->table('email_queue')
            ->where(function ($q) use ($aliasList, $aliasUntil, $mainUntil) {
                $q->where(function ($q) use ($mainUntil) {
                    $q->where('status', 'pending');
                    $mainUntil !== null ? $q->where('scheduled_at', '<=', $mainUntil) : $q->whereRaw('scheduled_at <= NOW()');
                })->orWhere(fn ($q) => $q->where('status', 'error')->whereColumn('retry_count', '<', 'max_retries')->whereRaw('scheduled_at <= NOW()'));
                if ($aliasUntil !== null) {
                    $q->orWhere(fn ($q) => $q->where('status', 'pending')->whereIn('sender_id', $aliasList)->where('scheduled_at', '<=', $aliasUntil));
                }
            })
            ->whereRaw("TRIM(to_email) <> ''");
        if ($onlyEmails !== null) {
            if ($onlyEmails === []) {
                return [];
            }
            $q->whereIn(DB::raw('LOWER(to_email)'), $onlyEmails);
        }
        $out = [];
        foreach ($q->selectRaw('LOWER(to_email) r, sender_id, batch_id, COUNT(*) n')
            ->groupBy(DB::raw('LOWER(to_email)'), 'sender_id', 'batch_id')->get() as $row) {
            $c = $this->channel((int) $row->sender_id, $row->batch_id !== null ? (int) $row->batch_id : null);
            $out[$row->r][$c] = ($out[$row->r][$c] ?? 0) + (int) $row->n;
        }

        return $out;
    }

    /**
     * Остаток дневного капа канала по адресатам (для планировщиков: план дня — main, мониторинг — monitoring):
     * кап канала − отправлено сегодня этим каналом − ожидающие сегодня этим каналом. Заблокированный адресат — 0.
     *
     * @param string[] $emails нормализованные
     * @return array<string,int>
     */
    public function remaining(array $emails, string $channel): array
    {
        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            return [];
        }
        $endOfDay = Carbon::now('Europe/Moscow')->endOfDay()->utc()->toDateTimeString();
        $meta = $this->recipientMeta($emails);
        $usage = $this->usageToday($emails);
        $pending = $this->pending($endOfDay, $endOfDay, $emails);
        $out = [];
        foreach ($emails as $e) {
            $m = $meta[$e];
            $out[$e] = $m['blocked'] ? 0 : max(0, self::cap($channel, $m['cap'], self::contended($usage, $pending, $e))
                - (int) ($usage[$e][$channel]['n'] ?? 0) - (int) ($pending[$e][$channel] ?? 0));
        }

        return $out;
    }
}
