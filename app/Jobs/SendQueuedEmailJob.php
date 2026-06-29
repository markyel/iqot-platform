<?php

namespace App\Jobs;

use App\Models\Reports\EmailBatch;
use App\Models\Reports\EmailQueue;
use App\Models\Reports\RecipientMailbox;
use App\Models\Reports\Sender;
use App\Services\Senders\QueuedEmailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

    // Потолок переносов по занятому слоту (release): ~25*delay ≈ 50с ожидания,
    // дальше письмо возвращается в pending (un-claim), а не множит attempts до
    // переполнения TINYINT(255) → защита от «ядовитых» job'ов, валящих воркеры.
    private const MAX_SLOT_DEFERRALS = 25;

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

        // Ящик получателя заблокирован (N ошибок подряд) — не шлём, не переотправляем.
        // Диспетчер такие письма не клеймит, это страховка от гонки claim→block.
        if (RecipientMailbox::isBlocked((string) $email->to_email)) {
            $email->update([
                'status' => 'error',
                'error_message' => 'recipient mailbox blocked',
            ]);
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

        // АНТИ-БАН ТРОТТЛ к SMTP-хосту — ПЕР-СЕРВЕРНЫЙ. Хостер банит IP за высокую
        // параллельность/частоту с одного адреса, но этот лимит — у КАЖДОГО провайдера
        // свой (разные IP/домены). Поэтому ключ гейта — по smtp_server отправителя:
        // beget, sprinthost, spaceweb троттлятся НЕЗАВИСИМО, каждый в своём gap-темпе,
        // суммарный потолок платформы ≈ ×(число серверов). Cache::add — атомарный SETNX
        // с TTL: ключ живёт gap секунд, в это окно слот по этому серверу никто не возьмёт
        // → темп на сервер ≤ 1/gap. 0 = троттл выключен.
        $globalGap = (int) config('services.email_dispatch.global_min_interval_seconds', 0);
        $dual = (bool) config('services.email_dispatch.dual_smtp_enabled', false);
        $smtpRoute = null;
        $isBeget = ($senderModel->smtp_server === 'smtp.beget.com');

        if ($globalGap > 0) {
            $deferred = false;

            if ($dual && $isBeget) {
                // beget: два независимых исходящих канала (прямой IP beget + прокси-релей),
                // у каждого свой gap-гейт → суммарный темп ~2x при том же per-IP профиле.
                // Берём любой свободный канал; оба заняты — переносим.
                if (Cache::add('emails:send_gate:beget:direct', 1, $globalGap)) {
                    $smtpRoute = [
                        'host' => (string) config('services.email_dispatch.direct_smtp_host'),
                        'peer_name' => 'smtp.beget.com',
                    ];
                } elseif (Cache::add('emails:send_gate:beget:proxy', 1, $globalGap)) {
                    $smtpRoute = null; // default: smtp_server отправителя (через /etc/hosts → прокси)
                } else {
                    $deferred = true;
                }
            } else {
                // Прочие провайдеры (и beget без dual-path) — один гейт на smtp_server.
                $gateKey = 'emails:send_gate:' . md5((string) $senderModel->smtp_server);
                if (!Cache::add($gateKey, 1, $globalGap)) {
                    $deferred = true;
                }
            }

            if ($deferred) {
                if ($this->attempts() >= self::MAX_SLOT_DEFERRALS) {
                    $email->update(['status' => 'pending']);
                    return;
                }
                $this->release(1);
                return;
            }
        }

        // Жёсткая пауза на ящик: атомарно «занимаем слот». Если рано — переносим
        // письмо на send_delay_seconds, статус остаётся 'sending' (claim не теряем).
        $delay = max(1, (int) ($senderModel->send_delay_seconds ?: 2));
        if (!$this->reserveSlot($senderModel->id, $delay)) {
            // Слишком долго ждём слот (ящик перегружен своими письмами) — возвращаем
            // письмо в очередь БД и завершаем job БЕЗ release(): диспетчер перепланирует
            // на следующем тике, attempts не растёт до переполнения.
            if ($this->attempts() >= self::MAX_SLOT_DEFERRALS) {
                $email->update(['status' => 'pending']);
                return;
            }
            $this->release($delay);
            return;
        }

        try {
            $sender->send($email, $smtpRoute);

            $email->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);

            // Успешная доставка — снять метку ошибок с ящика получателя.
            RecipientMailbox::recordSuccess((string) $email->to_email);

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
     * Время пишем и сравниваем через NOW(3) самой БД (reports): миллисекундная
     * точность убирает off-by-one секундного floor (иначе интервал проседал до
     * ~1s вместо delay). Это же NOW() не зависит от рассинхрона таймзоны
     * приложения (UTC) и БД (МСК).
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

        // ОШИБКА АВТОРИЗАЦИИ ящика-отправителя (битый/сменённый пароль): SMTP-хост
        // отвечает 535 «Incorrect authentication data» / «Failed to authenticate».
        // КРИТИЧНО: каждая такая попытка — это failed-auth к smtp-хосту, а beget банит
        // ИСХОДЯЩИЙ IP (у нас — прокси-релей) после 5 неудачных авторизаций на 24ч.
        // Поэтому ретраить бессмысленно и ОПАСНО (тем же паролем снова провалимся и
        // выжжем IP). Немедленно деактивируем отправитель (как 550 в SendOutgoingReplyJob),
        // письмо — terminal error без ретрая. Получателя НЕ штрафуем: это не его вина.
        $isAuthFailure = (bool) preg_match(
            '/failed to authenticate|incorrect authentication(\s+data)?|authentication failed|'
            . 'auth.*(failed|invalid|incorrect)|\b535\b/i',
            $message
        );
        if ($isAuthFailure) {
            $this->deactivateSenderAuth($sender, $message);
            $email->update([
                'status' => 'error',
                'error_message' => $message,
                'retry_count' => (int) $email->retry_count + 1,
            ]);
            Log::error('SendQueuedEmailJob: sender auth failure → deactivated (anti IP-ban)', [
                'email_queue_id' => $email->id,
                'sender_id' => $sender->id,
                'error' => $message,
            ]);
            return;
        }

        // Транзиентные ошибки ТРАНСПОРТА (коннект/таймаут/DNS/сеть) — проблема
        // инфраструктуры отправителя или SMTP-хоста, НЕ получателя. Их нельзя
        // вешать на ящик получателя: при недоступности smtp-сервера (бан IP,
        // мёртвый round-robin-узел, обрыв сети) иначе массово блокируются валидные
        // адреса. Такие письма просто переносятся на ретрай, без штрафа получателю.
        $isTransient = (bool) preg_match(
            '/connection could not be established|connection (refused|reset|timed out|could not be opened)|'
            . 'failed to connect|could not connect|network is unreachable|no route to host|'
            . 'getaddrinfo|name or service not known|could not be resolved|stream_socket_client|'
            . 'operation timed out|connection timed out/i',
            $message
        );

        if ($isRateLimit) {
            // Ratelimit — проблема отправителя, не получателя: блокируем ящик-отправитель,
            // счётчик ошибок получателя НЕ трогаем (иначе блокировали бы валидные адреса).
            $this->blockSender($sender, $message);
        } elseif (!$isTransient) {
            // Устойчивая ошибка уровня получателя (отказ адреса, 5xx user unknown и т.п.) —
            // копим по ящику получателя; при пороге подряд он блокируется (см. RecipientMailbox).
            RecipientMailbox::recordFailure(
                (string) $email->to_email,
                $message,
                (int) config('services.email_dispatch.recipient_error_threshold', 3),
            );
        }
        // транзиентный коннект/таймаут — ни блока отправителя, ни штрафа получателю, только ретрай.

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
            'transient' => $isTransient,
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

    /**
     * Деактивация отправителя при ошибке авторизации (битый пароль).
     * Ретрай бесполезен (тот же пароль) и опасен (бан исходящего IP за 5 failed-auth).
     * Зеркало ветки 550 «sending is disabled» в SendOutgoingReplyJob.
     */
    private function deactivateSenderAuth(Sender $sender, string $reason): void
    {
        $sender->forceFill([
            'is_active' => false,
            'last_block_at' => now(),
            'block_reason' => mb_substr('auth failure: ' . $reason, 0, 255),
        ])->save();
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
