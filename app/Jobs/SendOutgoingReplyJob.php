<?php

namespace App\Jobs;

use App\Models\Reports\OutgoingReply;
use App\Models\Reports\Sender;
use App\Services\Senders\OutgoingReplySender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Отправка одного готового ответа из reports.outgoing_replies (замена цикла n8n
 * «Send Outgoing Replies»).
 *
 * Отправители общие с массовой рассылкой, поэтому паузу на ящик держит тот же
 * атомарный «замок интервала» (senders.last_send_at, см. reserveSlot()) — ответы
 * и массовые письма с одного ящика не уйдут чаще send_delay_seconds и не
 * столкнутся при любом числе воркеров.
 *
 * На успехе пишет email_messages (direction='outgoing') и переводит ответ в 'sent'.
 * Ошибки терминальны (status='failed') — в outgoing_replies нет счётчика ретраев,
 * как и в n8n. Исключение: ratelimit отправителя → блокируем ящик и возвращаем
 * ответ в 'pending' (диспетчер пропускает заблокированные ящики, заберёт после).
 */
class SendOutgoingReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    // tries=0 → лимит попыток отключён, время жизни ограничивает retryUntil();
    // переносы по паузе через release() не должны исчерпывать попытки.
    public int $tries = 0;

    // Потолок переносов по занятому слоту: дальше ответ возвращается в pending
    // (un-claim), а не множит attempts до переполнения (защита от «ядовитых» job'ов).
    private const MAX_SLOT_DEFERRALS = 25;

    public function __construct(private readonly int $replyId)
    {
        $this->onQueue('replies');
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function handle(OutgoingReplySender $sender): void
    {
        $reply = OutgoingReply::find($this->replyId);
        if (!$reply) {
            return;
        }

        // Уже в финальном статусе (sent/failed) — пропускаем.
        if (!in_array($reply->status, ['sending', 'pending'], true)) {
            return;
        }

        $senderModel = $reply->sender;

        // Отправитель недоступен — возвращаем ответ в очередь, заберёт следующий тик.
        if (!$senderModel || !$senderModel->is_active) {
            $reply->update(['status' => 'pending']);
            return;
        }
        if ($senderModel->blocked_until && Carbon::parse($senderModel->blocked_until)->isFuture()) {
            $reply->update(['status' => 'pending']);
            return;
        }

        // Жёсткая пауза на ящик (общая с массовой рассылкой): атомарно «занимаем слот».
        $delay = max(1, (int) ($senderModel->send_delay_seconds ?: 2));
        if (!$this->reserveSlot($senderModel->id, $delay)) {
            if ($this->attempts() >= self::MAX_SLOT_DEFERRALS) {
                $reply->update(['status' => 'pending']);
                return;
            }
            $this->release($delay);
            return;
        }

        try {
            $messageId = $sender->send($reply);

            $this->saveToEmailMessages($reply, $messageId);

            $reply->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $this->bumpSenderCounter($senderModel);
        } catch (\Throwable $e) {
            $this->handleFailure($reply, $senderModel, $e->getMessage());
        }
    }

    /**
     * Атомарный замок интервала отправки для ящика (общий с SendQueuedEmailJob):
     * один UPDATE — занимаем слот только если прошло >= $delay секунд с прошлой
     * отправки. affected=1 → слот наш; affected=0 → рано, ждём.
     */
    private function reserveSlot(int $senderId, int $delay): bool
    {
        $affected = DB::connection('reports')->table('senders')
            ->where('id', $senderId)
            ->where(function ($q) use ($delay) {
                $q->whereNull('last_send_at')
                    ->orWhereRaw('last_send_at <= (NOW(3) - INTERVAL ? SECOND)', [$delay]);
            })
            ->update(['last_send_at' => DB::connection('reports')->raw('NOW(3)')]);

        return $affected > 0;
    }

    /**
     * Порт «Save to Email Messages»: фиксируем отправленный ответ в истории беседы
     * (direction='outgoing'), чтобы он попал в цепочку и приём не счёл его новым.
     */
    private function saveToEmailMessages(OutgoingReply $reply, string $messageId): void
    {
        DB::connection('reports')->table('email_messages')->insert([
            'conversation_id' => (int) $reply->conversation_id,
            'direction' => 'outgoing',
            'from_email' => (string) $reply->from_email,
            'to_email' => (string) $reply->to_email,
            'subject' => (string) $reply->subject,
            'body_text' => (string) ($reply->body_text ?? ''),
            'body_html' => (string) ($reply->body_html ?? ''),
            'message_id' => $messageId,
            'in_reply_to' => (string) ($reply->in_reply_to ?? ''),
            'references_header' => (string) ($reply->references_header ?? ''),
            'received_at' => now(),
        ]);
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

    private function handleFailure(OutgoingReply $reply, Sender $sender, string $message): void
    {
        $message = mb_substr($message, 0, 500);
        $isRateLimit = (bool) preg_match('/ratelimit|rate limit|try again later|too many/i', $message);

        if ($isRateLimit) {
            // Ratelimit — проблема отправителя: блокируем ящик, ответ возвращаем в
            // pending (диспетчер пропускает заблокированные ящики, заберёт после снятия).
            $this->blockSender($sender, $message);
            $reply->update(['status' => 'pending']);
        } else {
            // Прочая ошибка терминальна (в outgoing_replies нет ретраев, как в n8n).
            $reply->update(['status' => 'failed']);
        }

        Log::warning('SendOutgoingReplyJob: send failed', [
            'outgoing_reply_id' => $reply->id,
            'sender_id' => $sender->id,
            'ratelimit' => $isRateLimit,
            'error' => $message,
        ]);
    }

    /**
     * Блокировка отправителя при ratelimit (та же логика, что в SendQueuedEmailJob):
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

        if ($recent && $oldCount >= 2) {
            $data['is_active'] = false;
        }

        $sender->forceFill($data)->save();
    }

    public function failed(\Throwable $exception): void
    {
        // Джоба упала жёстко (вне try/catch) — снимаем claim, чтобы ответ переехал в очередь.
        $reply = OutgoingReply::find($this->replyId);
        if ($reply && $reply->status === 'sending') {
            $reply->update(['status' => 'pending']);
        }

        Log::error('SendOutgoingReplyJob: hard failure', [
            'outgoing_reply_id' => $this->replyId,
            'error' => $exception->getMessage(),
        ]);
    }
}
