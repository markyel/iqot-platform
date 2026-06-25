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
use Illuminate\Support\Facades\Log;

/**
 * Отправка одного письма из reports.email_queue (замена цикла n8n «Send Emails»).
 *
 * Ретраи ведём вручную через email_queue.retry_count/scheduled_at (tries=1),
 * чтобы воспроизвести логику n8n: ошибка → +1 попытка, перенос на +5 мин,
 * при ratelimit — блокировка отправителя на 30 мин (и деактивация при серии).
 */
class SendQueuedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(private readonly int $emailQueueId)
    {
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
