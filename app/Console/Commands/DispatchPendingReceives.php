<?php

namespace App\Console\Commands;

use App\Support\Senders\SenderPool;
use App\Jobs\ReceiveSenderEmailsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Диспетчер приёма почты — замена крон-триггера n8n «Receive and Route Emails v3».
 *
 * Ставит по job на каждый активный ящик с IMAP-кредами в очередь `receive`
 * (опрос идёт параллельно пулом воркеров). Приём не лимитируется паузами, как
 * отправка, поэтому без round-robin: просто раздаём ящики по воркерам.
 *
 * Дубль-job по ящику отсекает Cache::lock внутри самого job.
 */
class DispatchPendingReceives extends Command
{
    protected $signature = 'emails:receive-dispatch
        {--force : Запустить даже при выключенном флаге EMAILS_RECEIVE_ENABLED}';

    protected $description = 'Поставить опрос IMAP активных ящиков в очередь приёма (многопоточно)';

    public function handle(): int
    {
        // Предохранитель фазового перехода с n8n: по расписанию молчим, пока флаг выключен.
        if (!$this->option('force') && !config('services.email_receive.enabled')) {
            $this->warn('emails:receive-dispatch выключен (EMAILS_RECEIVE_ENABLED=false). Используйте --force для ручного запуска.');
            return self::SUCCESS;
        }

        // Ящики «ящик на батч» пересылают почту в общий info@ домена и сами её не хранят.
        $senders = SenderPool::onlyMain(DB::connection('reports')->table('senders'))
            ->where('is_active', 1)
            ->whereNotNull('imap_server')
            ->where('imap_server', '!=', '')
            ->whereNotNull('imap_user')
            ->where('imap_user', '!=', '')
            ->whereNotNull('imap_password')
            ->where('imap_password', '!=', '')
            ->orderBy('id')
            ->pluck('id');

        // Общие ящики доменов ветки «ящик на батч» (info@) — независимо от флага ветки: ответы на уже
        // разосланные письма и на удалённые ящики (катч-олл) должны приниматься.
        $aliasDomains = 0;
        try {
            foreach (DB::table('alias_domains')->whereNotNull('inbox_password')->pluck('id') as $domainId) {
                \App\Jobs\ReceiveAliasInboxJob::dispatch((int) $domainId);
                $aliasDomains++;
            }
        } catch (\Throwable) {
            // таблицы нет — ветка не развёрнута
        }
        if ($aliasDomains > 0) {
            $this->info("Dispatched alias inbox jobs: {$aliasDomains} domain(s).");
        }

        if ($senders->isEmpty()) {
            $this->info('No active mailboxes with IMAP credentials.');
            return self::SUCCESS;
        }

        foreach ($senders as $id) {
            ReceiveSenderEmailsJob::dispatch((int) $id);
        }

        $this->info("Dispatched receive jobs: {$senders->count()} mailbox(es).");
        return self::SUCCESS;
    }
}
