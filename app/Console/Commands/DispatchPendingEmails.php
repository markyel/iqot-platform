<?php

namespace App\Console\Commands;

use App\Jobs\SendQueuedEmailJob;
use App\Models\Reports\EmailQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер рассылки — замена крон-триггера n8n «Send Emails v2».
 *
 * Многопоточность: раздаёт письма ЧЕСТНО по всем готовым ящикам (round-robin),
 * чтобы они слались параллельно (своя очередь `emails`, пул воркеров). На один
 * ящик за тик берётся не больше, чем он успеет отправить до следующего тика
 * (потолок = окно_тика / send_delay_seconds), с лёгким пред-разносом delay —
 * чтобы жёсткий «замок интервала» в SendQueuedEmailJob почти не срабатывал.
 *
 * «Claim» через status='sending': взятое в работу письмо не подхватывается
 * повторно; зависшие (упавший воркер) реклеймятся обратно в pending через 30 мин.
 */
class DispatchPendingEmails extends Command
{
    protected $signature = 'emails:dispatch-pending
        {--limit=3000 : Общий потолок писем за тик (предохранитель)}
        {--tick=60 : Окно тика в секундах — под него считается потолок на ящик}
        {--force : Запустить даже при выключенном флаге EMAILS_DISPATCH_ENABLED}';

    protected $description = 'Поставить pending/error письма из reports.email_queue в очередь на отправку (многопоточно, с паузой на ящик)';

    public function handle(): int
    {
        // Предохранитель для фазового перехода с n8n: по расписанию команда
        // молчит, пока не включён флаг. Ручной прогон — через --force.
        if (!$this->option('force') && !config('services.email_dispatch.enabled')) {
            $this->warn('emails:dispatch-pending выключен (EMAILS_DISPATCH_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        // 1) Реклейм застрявших в 'sending' (упавший воркер) — старше 30 минут.
        $reclaimed = EmailQueue::where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->update(['status' => 'pending']);
        if ($reclaimed) {
            $this->info("Reclaimed stale 'sending': {$reclaimed}");
        }

        $limit = max(1, (int) $this->option('limit'));
        $tick = max(5, (int) $this->option('tick'));

        // 2) Готовые ящики: активные, не заблокированные, у которых есть письма к отправке.
        $senders = DB::connection('reports')->table('senders as s')
            ->join('email_queue as eq', 'eq.sender_id', '=', 's.id')
            ->where('s.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('s.blocked_until')->orWhereRaw('s.blocked_until <= NOW()');
            })
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('eq.status', 'pending')->whereRaw('eq.scheduled_at <= NOW()');
                })->orWhere(function ($q) {
                    $q->where('eq.status', 'error')
                        ->whereColumn('eq.retry_count', '<', 'eq.max_retries')
                        ->whereRaw('eq.scheduled_at <= NOW()');
                });
            })
            // Заблокированные получатели (N ошибок подряд) — сразу пропускаем, не клеймим.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('recipient_mailboxes as rm')
                    ->whereColumn('rm.email', DB::raw('LOWER(eq.to_email)'))
                    ->where('rm.is_blocked', 1);
            })
            ->groupBy('s.id', 's.send_delay_seconds')
            ->get(['s.id', 's.send_delay_seconds']);

        if ($senders->isEmpty()) {
            $this->info('No pending emails.');
            return self::SUCCESS;
        }

        // 3) Round-robin по ящикам: на каждый берём perSenderCap писем, ставим
        //    с накопительной задержкой по этому ящику (0, delay, 2·delay, …).
        $dispatched = 0;
        $totalCapHit = 0;

        foreach ($senders as $sender) {
            if ($dispatched >= $limit) {
                break;
            }

            $delaySec = max(1, (int) ($sender->send_delay_seconds ?: 2));
            // Сколько ящик успеет отправить до следующего тика (+1 в запас от простоя).
            $perSenderCap = (int) ceil($tick / $delaySec) + 1;

            $rows = DB::connection('reports')->table('email_queue')
                ->where('sender_id', $sender->id)
                ->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('status', 'pending')->whereRaw('scheduled_at <= NOW()');
                    })->orWhere(function ($q) {
                        $q->where('status', 'error')
                            ->whereColumn('retry_count', '<', 'max_retries')
                            ->whereRaw('scheduled_at <= NOW()');
                    });
                })
                // Не выбирать письма заблокированным получателям (см. выше).
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('recipient_mailboxes as rm')
                        ->whereColumn('rm.email', DB::raw('LOWER(email_queue.to_email)'))
                        ->where('rm.is_blocked', 1);
                })
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('priority')
                ->orderBy('scheduled_at')
                ->limit($perSenderCap)
                ->get(['id']);

            if ($rows->count() >= $perSenderCap) {
                $totalCapHit++;
            }

            $accum = 0;
            foreach ($rows as $row) {
                if ($dispatched >= $limit) {
                    break;
                }

                $claimed = EmailQueue::where('id', $row->id)
                    ->whereIn('status', ['pending', 'error'])
                    ->update(['status' => 'sending', 'updated_at' => now()]);

                if (!$claimed) {
                    continue; // письмо уже забрал другой процесс
                }

                SendQueuedEmailJob::dispatch((int) $row->id)->delay(now()->addSeconds($accum));

                $accum += $delaySec;
                $dispatched++;
            }
        }

        $this->info("Dispatched: {$dispatched} (senders: {$senders->count()}, на лимите ящика: {$totalCapHit})");
        return self::SUCCESS;
    }
}
