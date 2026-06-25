<?php

namespace App\Jobs;

use App\Models\Reports\Sender;
use App\Services\Senders\ImapMailboxReader;
use App\Services\Senders\IncomingEmailRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Приём входящих писем одного ящика — порт цикла n8n «Receive and Route Emails v3».
 *
 * Многопоточность: разные ящики опрашиваются параллельно (своя очередь `receive`,
 * отдельный пул воркеров — IMAP медленный, не должен голодать отправку). Дубль-job
 * по тому же ящику отсекается Cache::lock (аналог withoutOverlapping, но на ящик).
 *
 * Одно битое письмо не валит весь ящик — обработка каждого в своём try/catch.
 * Пометка \Seen вешается только после успешной маршрутизации (упавшее перечитается).
 */
class ReceiveSenderEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    // Приём идемпотентен по message_id (дедуп в роутере) — одной попытки достаточно.
    public int $tries = 1;

    public function __construct(private readonly int $senderId)
    {
        $this->onQueue('receive');
    }

    public function handle(ImapMailboxReader $reader, IncomingEmailRouter $router): void
    {
        // Защита от наложения тиков: пока ящик опрашивается, повторный job выходит.
        $lock = Cache::lock("receive:sender:{$this->senderId}", 170);
        if (!$lock->get()) {
            return;
        }

        try {
            $sender = Sender::find($this->senderId);
            if (!$sender || !$sender->is_active) {
                return;
            }

            $limit = (int) config('services.email_receive.per_mailbox_limit', 20);

            try {
                $emails = $reader->fetchUnseen($sender, $limit);
            } catch (\Throwable $e) {
                Log::warning('ReceiveSenderEmailsJob: IMAP fetch failed', [
                    'sender_id' => $this->senderId,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ]);
                return;
            }

            $counts = ['replied' => 0, 'conversation' => 0, 'unidentified' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];

            foreach ($emails as $uid => $email) {
                try {
                    $outcome = $router->route($this->senderId, $email);
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;

                    // Успешно разобрано — помечаем прочитанным, чтобы не перечитывать.
                    $reader->markSeen($uid);
                } catch (\Throwable $e) {
                    $counts['error']++;
                    Log::error('ReceiveSenderEmailsJob: route failed', [
                        'sender_id' => $this->senderId,
                        'message_id' => $email->messageId,
                        'error' => mb_substr($e->getMessage(), 0, 300),
                    ]);
                    // \Seen НЕ ставим — письмо перечитается на следующем тике.
                }
            }

            if ($emails !== []) {
                Log::info('ReceiveSenderEmailsJob: processed mailbox', [
                    'sender_id' => $this->senderId,
                    'fetched' => count($emails),
                    'counts' => $counts,
                ]);
            }
        } finally {
            $lock->release();
        }
    }
}
