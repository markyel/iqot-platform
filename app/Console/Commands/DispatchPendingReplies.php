<?php

namespace App\Console\Commands;

use App\Jobs\SendOutgoingReplyJob;
use App\Models\Reports\OutgoingReply;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер отправки готовых ответов поставщикам — замена крон-триггера n8n «Send
 * Outgoing Replies» (раз в 120 мин).
 *
 * Порт «Get Pending Replies»: outgoing_replies.status='pending', по дате создания,
 * лимит; на каждый — claim (status='sending') + job в очередь `replies`.
 *
 * Отправители общие с массовой рассылкой → пред-разносим dispatch по delay ящика,
 * чтобы «замок интервала» в job почти не срабатывал. Реклейм застрявших 'sending'
 * (упавший воркер) старше 30 мин — обратно в 'pending'.
 *
 * Флаг EMAILS_REPLIES_ENABLED по умолчанию OFF: включать ТОЛЬКО после отключения
 * n8n-воркфлоу «Send Outgoing Replies» (иначе двойная отправка). --force обходит
 * флаг для ручного/точечного прогона.
 */
class DispatchPendingReplies extends Command
{
    protected $signature = 'emails:dispatch-replies
        {--force : Запустить даже при выключенном флаге EMAILS_REPLIES_ENABLED}
        {--limit= : Переопределить лимит ответов за тик}
        {--reply= : Точечный прогон одного ответа по outgoing_replies.id}';

    protected $description = 'Поставить pending-ответы из reports.outgoing_replies в очередь отправки replies';

    public function handle(): int
    {
        if (!$this->option('force') && !config('services.email_replies.enabled')) {
            $this->warn('emails:dispatch-replies выключен (EMAILS_REPLIES_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        // Точечный прогон одного ответа (claim + dispatch), минуя выборку батча.
        if ($replyId = $this->option('reply')) {
            $claimed = OutgoingReply::where('id', (int) $replyId)
                ->where('status', 'pending')
                ->update(['status' => 'sending']);

            if (!$claimed) {
                $this->warn("Reply {$replyId} не в статусе pending (или не найден).");
                return self::SUCCESS;
            }

            SendOutgoingReplyJob::dispatch((int) $replyId);
            $this->info("Dispatched reply job for reply {$replyId}.");
            return self::SUCCESS;
        }

        // Реклейм застрявших в 'sending' (упавший воркер) старше 30 минут.
        $reclaimed = OutgoingReply::where('status', 'sending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'pending']);
        if ($reclaimed) {
            $this->info("Reclaimed stale 'sending': {$reclaimed}");
        }

        $limit = (int) ($this->option('limit') ?: config('services.email_replies.batch_limit', 30));

        $rows = DB::connection('reports')->table('outgoing_replies')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['id', 'sender_id']);

        if ($rows->isEmpty()) {
            $this->info('No pending outgoing replies.');
            return self::SUCCESS;
        }

        // Пред-разнос dispatch по ящику: накопительная задержка delay на каждый
        // следующий ответ того же отправителя (как в массовом диспетчере).
        $accumBySender = [];
        $dispatched = 0;

        foreach ($rows as $row) {
            $claimed = OutgoingReply::where('id', $row->id)
                ->where('status', 'pending')
                ->update(['status' => 'sending']);

            if (!$claimed) {
                continue; // забрал другой процесс
            }

            $delay = $accumBySender[$row->sender_id] ?? 0;
            SendOutgoingReplyJob::dispatch((int) $row->id)->delay(now()->addSeconds($delay));
            $accumBySender[$row->sender_id] = $delay + 2; // пред-разнос ~send_delay_seconds
            $dispatched++;
        }

        $this->info("Dispatched reply jobs: {$dispatched}.");
        return self::SUCCESS;
    }
}
