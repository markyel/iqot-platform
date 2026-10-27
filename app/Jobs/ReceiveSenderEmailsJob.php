<?php

namespace App\Jobs;

use App\Models\Reports\Sender;
use App\Services\Ops\OpsAlerter;
use App\Services\Senders\ImapMailboxReader;
use App\Services\Senders\IncomingEmailRouter;
use App\Services\Senders\SenderBanContainment;
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

    /** Кеш: начало текущей серии отказов IMAP-входа ящика (timestamp). */
    private const AUTH_FAIL_KEY = 'receive:authfail:';

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
                if (self::isAuthFailure($e->getMessage())) {
                    $this->trackAuthFailure($sender, $e->getMessage());
                }
                return;
            }
            Cache::forget(self::AUTH_FAIL_KEY . $this->senderId);

            $counts = ['replied' => 0, 'conversation' => 0, 'unidentified' => 0, 'bounce' => 0, 'duplicate' => 0, 'skipped' => 0, 'error' => 0];

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

    /** Отказ входа (а не сеть/таймаут): неверный пароль, нужен пароль приложения и т.п. */
    public static function isAuthFailure(string $error): bool
    {
        return (bool) preg_match('/AUTHENTICATIONFAILED|authentication failed|failed to authenticate|invalid credentials|LOGIN failed|parol prilozheniya|Application password is REQUIRED/i', $error);
    }

    /** Пора снимать: вход отклоняется с $firstAt без единого успешного опроса не меньше $hours часов. */
    public static function shouldDeactivate(?int $firstAt, int $now, int $hours): bool
    {
        return $hours > 0 && $firstAt !== null && $now - $firstAt >= $hours * 3600;
    }

    /**
     * Хронический отказ IMAP-входа: начало серии — в кеше (сбрасывается любым успешным опросом); серия дольше
     * auth_fail_deactivate_hours — ящик снимаем (is_active=0, как при отказе SMTP-входа в отправке) и оповещаем.
     */
    private function trackAuthFailure(Sender $sender, string $error): void
    {
        $key = self::AUTH_FAIL_KEY . $sender->id;
        $now = now()->getTimestamp();
        $firstAt = Cache::get($key);
        if ($firstAt === null) {
            Cache::put($key, $now, now()->addDays(7));
            return;
        }
        $hours = (int) config('services.email_receive.auth_fail_deactivate_hours', 24);
        if (!self::shouldDeactivate((int) $firstAt, $now, $hours)) {
            return;
        }
        $sender->forceFill([
            'is_active' => false,
            'last_block_at' => now(),
            'block_reason' => mb_substr('imap auth failure: ' . $error, 0, 255),
        ])->save();
        SenderBanContainment::contain((int) $sender->id, 'auth_failure');
        Cache::forget($key);
        Log::warning('ReceiveSenderEmailsJob: ящик снят — IMAP отклоняет вход', ['sender_id' => $sender->id, 'hours' => $hours]);
        app(OpsAlerter::class)->send('<b>Ящик снят: IMAP отклоняет вход</b> ' . $hours . " ч подряд\n" . e((string) $sender->email)
            . ' (#' . $sender->id . ")\n" . e(mb_substr($error, 0, 200)) . "\nВернуть: исправить пароль и is_active=1.");
    }
}
