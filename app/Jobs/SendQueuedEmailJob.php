<?php

namespace App\Jobs;

use App\Models\Reports\EmailBatch;
use App\Models\Reports\EmailQueue;
use App\Models\Reports\Sender;
use App\Services\Senders\QueuedEmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Отправка одного письма из reports.email_queue (замена цикла n8n «Send Emails»).
 *
 * Многопоточная рассылка: разные ящики шлются параллельно (своя очередь `emails`,
 * пул воркеров), а внутри одного ящика паузу гарантирует атомарный «замок интервала»
 * в БД (см. reserveSlot()) — два письма одного отправителя не уйдут чаще
 * send_delay_seconds и не уйдут одновременно при любом числе воркеров.
 *
 * Ретраи ведём вручную через email_queue.retry_count/scheduled_at; переносы
 * по паузе — через release(), поэтому ограничение времени задаём retryUntil()
 * (30 мин), а не tries.
 */
class SendQueuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    // tries=0 → лимит попыток отключён, время жизни ограничивает retryUntil().
    // Нужно для release() по паузе: переносы не должны исчерпывать попытки.
    public int $tries = 0;

    public function __construct(private readonly int $emailQueueId)
    {
        $this->onQueue('emails');
    }

    /**
     * Письмо живёт в очереди не дольше 30 мин (совпадает с реклеймом «застрявших»
     * в диспетчере). За это время пауза-переносы успевают отработать.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(QueuedEmailSender $sender): void
    {
        $email = EmailQueue::find($this->emailQueueId);
        if (!$email) {
            return;
        }

        // Письмо уже в финальном статусе (sent/cancelled/replied и т.п.) — пропускаем.
        if (!in_array($email->status, ['sending', 'pending', 'error'], true)) {
            return;
        }

        $senderModel = $email->sender;

        // Отправитель недоступен — возвращаем письмо в очередь, заберёт следующий тик.
        if (!$senderModel || !$senderModel->is_active) {
            $email->update(['status' => 'pending']);
            return;
        }
        if ($senderModel->blocked_until && Carbon::parse($senderModel->blocked_until)->isFuture()) {
            $email->update(['status' => 'pending']);
            return;
        }

        // Жёсткая пауза на ящик: атомарно «занимаем слот». Если рано — переносим
        // письмо на send_delay_seconds, статус остаётся 'sending' (claim не теряем).
        $delay = max(1, (int) ($senderModel->send_delay_seconds ?: 2));
        if (!$this->reserveSlot($senderModel->id, $delay)) {
            $this->release($delay);
            return;
        }

        try {
            $sender->send($email);

            $email->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $this->bumpSenderCounter($senderModel);
            $this->refreshBatch($email->batch_id);
        } catch (\Throwable $e) {
            $this->handleFailure($email, $senderModel, $e->getMessage());
        }
    }

    /**
     * Атомарный замок интервала отправки для ящика.
     *
     * Один UPDATE: занимаем слот только если прошло >= $delay секунд с прошлой
     * отправки (или её ещё не было). affected=1 → слот наш, можно слать;
     * affected=0 → другой воркер уже занял слот / пауза не вышла → ждём.
     *
     * Время пишем и сравниваем через NOW() самой БД (reports), чтобы не зависеть
     * от рассинхрона таймзоны приложения (UTC) и БД (МСК).
     */
    private function reserveSlot(int $senderId, int $delay): bool
    {
        $affected = DB::connection('reports')->table('senders')
            ->where('id', $senderId)
            ->where(function ($q) use ($delay) {
                $q->whereNull('last_send_at')
                    ->orWhereRaw('last_send_at <= (NOW() - INTERVAL ? SECOND)', [$delay]);
            })
            ->update(['last_send_at' => DB::connection('reports')->raw('NOW()')]);

        return $affected > 0;
    }

    private function bumpSenderCounter(Sender $sender): void
    {
        $today = now()->toDateString();

        if ($sender->last_send_date instanceof \DateTimeInterface
            && $sender->last_send_date->format('Y-m-d') === $today) {
            $sender->increment('emails_sent_today');
        } else {
            $sender->forceFill([
                'emails_sent_today' => 1,
                'last_send_date' => $today,
            ])->save();
        }
    }

    private function refreshBatch($batchId): void
    {
        if (!$batchId) {
            return;
        }

        $total = EmailQueue::where('batch_id', $batchId)->count();
        $sent = EmailQueue::where('batch_id', $batchId)->where('status', 'sent')->count();

        if ($total > 0 && $sent === $total) {
            EmailBatch::where('id', $batchId)->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    private function handleFailure(EmailQueue $email, Sender $sender, string $message): void
    {
        $message = mb_substr($message, 0, 500);
        $isRateLimit = (bool) preg_match('/ratelimit|rate limit|try again later|too many/i', $message);

        if ($isRateLimit) {
            $this->blockSender($sender, $message);
        }

        $retry = (int) $email->retry_count + 1;
        $data = [
            'status' => 'error',
            'error_message' => $message,
            'retry_count' => $retry,
        ];
        if ($retry < (int) $email->max_retries) {
            $data['scheduled_at'] = now()->addMinutes(5);
        }
        $email->update($data);

        Log::warning('SendQueuedEmailJob: send failed', [
            'email_queue_id' => $email->id,
            'sender_id' => $sender->id,
            'ratelimit' => $isRateLimit,
            'error' => $message,
        ]);
    }

    /**
     * Блокировка отправителя при ratelimit (логика n8n Update Error):
     * счётчик блокировок за сутки, блок на 30 мин, деактивация при 3-й блокировке.
     */
    private function blockSender(Sender $sender, string $reason): void
    {
        $recent = $sender->last_block_at
            && Carbon::parse($sender->last_block_at)->gt(now()->subDay());

        $oldCount = (int) $sender->block_count;
        $newCount = $recent ? $oldCount + 1 : 1;

        $data = [
            'block_count' => $newCount,
            'last_block_at' => now(),
            'blocked_until' => now()->addMinutes(30),
            'block_reason' => mb_substr($reason, 0, 255),
        ];

        // n8n деактивирует, если ДО этой блокировки уже было >=2 блока за сутки.
        if ($recent && $oldCount >= 2) {
            $data['is_active'] = false;
        }

        $sender->forceFill($data)->save();
    }

    public function failed(\Throwable $exception): void
    {
        // Джоба упала жёстко (вне нашего try/catch) — снимаем claim, чтобы письмо переехало в очередь.
        $email = EmailQueue::find($this->emailQueueId);
        if ($email && $email->status === 'sending') {
            $email->update(['status' => 'pending']);
        }

        Log::error('SendQueuedEmailJob: hard failure', [
            'email_queue_id' => $this->emailQueueId,
            'error' => $exception->getMessage(),
        ]);
    }
}
