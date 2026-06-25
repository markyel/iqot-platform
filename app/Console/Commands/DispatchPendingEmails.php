<?php

namespace App\Console\Commands;

use App\Jobs\SendQueuedEmailJob;
use App\Models\Reports\EmailQueue;
use Illuminate\Console\Command;

/**
 * Диспетчер рассылки — замена крон-триггера n8n «Send Emails v2».
 *
 * Выбирает pending/error письма от незаблокированных отправителей и ставит
 * каждое отдельной джобой SendQueuedEmailJob с накопительной задержкой
 * по каждому отправителю (соблюдение send_delay_seconds вместо n8n Wait).
 *
 * «Claim» через status='sending': взятое в работу письмо не подхватывается
 * повторно; зависшие (упавший воркер) реклеймятся обратно в pending через 30 мин.
 */
class DispatchPendingEmails extends Command
{
    protected $signature = 'emails:dispatch-pending {--limit=150} {--force : Запустить даже при выключенном флаге EMAILS_DISPATCH_ENABLED}';

    protected $description = 'Поставить pending/error письма из reports.email_queue в очередь на отправку';

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

        // 2) Выборка кандидатов (логика n8n Get Pending Emails).
        $candidates = EmailQueue::query()
            ->from('email_queue as eq')
            ->join('senders as s', 'eq.sender_id', '=', 's.id')
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('eq.status', 'pending')
                        ->where('eq.scheduled_at', '<=', now());
                })->orWhere(function ($q) {
                    $q->where('eq.status', 'error')
                        ->whereColumn('eq.retry_count', '<', 'eq.max_retries')
                        ->where('eq.scheduled_at', '<=', now());
                });
            })
            ->where(function ($q) {
                $q->whereNull('s.blocked_until')->orWhere('s.blocked_until', '<=', now());
            })
            ->where('s.is_active', 1)
            ->orderByRaw("CASE WHEN eq.status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('eq.priority')
            ->orderBy('eq.scheduled_at')
            ->limit($limit)
            ->get(['eq.id', 'eq.sender_id', 's.send_delay_seconds']);

        if ($candidates->isEmpty()) {
            $this->info('No pending emails.');
            return self::SUCCESS;
        }

        // 3) Claim + dispatch с задержкой, накапливаемой отдельно по отправителю.
        $delayBySender = [];
        $dispatched = 0;

        foreach ($candidates as $row) {
            $claimed = EmailQueue::where('id', $row->id)
                ->whereIn('status', ['pending', 'error'])
                ->update(['status' => 'sending', 'updated_at' => now()]);

            if (!$claimed) {
                continue; // письмо уже забрал другой процесс
            }

            $delaySec = max(0, (int) ($row->send_delay_seconds ?? 5));
            $accum = $delayBySender[$row->sender_id] ?? 0;

            SendQueuedEmailJob::dispatch((int) $row->id)->delay(now()->addSeconds($accum));

            $delayBySender[$row->sender_id] = $accum + $delaySec;
            $dispatched++;
        }

        $this->info("Dispatched: {$dispatched}");
        return self::SUCCESS;
    }
}
