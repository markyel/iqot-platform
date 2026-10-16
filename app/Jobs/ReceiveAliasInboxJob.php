<?php

namespace App\Jobs;

use App\Models\Reports\Sender;
use App\Services\AliasExp\AliasMailboxFactory;
use App\Services\Senders\ImapMailboxReader;
use App\Services\Senders\IncomingEmailRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Приём ветки «ящик на батч» (docs/alias-mailbox-design.md, Ф4): опрос общего ящика домена (info@), куда
 * пересылается почта всех ящиков-меток (и катч-олл — на удалённые). Ящик-метка — по X-Original-To, иначе по
 * To/Cc; письмо, не адресованное ни одному ящику-метке (служебные Beget, письма на сам info@), — пропуск.
 */
class ReceiveAliasInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 1;

    public function __construct(private readonly int $domainId)
    {
        $this->onQueue('receive');
    }

    public function handle(ImapMailboxReader $reader, IncomingEmailRouter $router): void
    {
        $lock = Cache::lock("receive:alias-domain:{$this->domainId}", 170);
        if (!$lock->get()) {
            return;
        }
        try {
            $dom = DB::table('alias_domains')->find($this->domainId);
            if (!$dom || !$dom->inbox_password) {
                return;
            }
            [$host, $port] = array_pad(explode(':', (string) config('services.alias_exp.imap', 'imap.beget.com:993')), 2, '993');
            $inbox = new Sender([
                'email' => "{$dom->inbox}@{$dom->domain}", 'imap_server' => $host, 'imap_port' => (int) $port,
                'imap_user' => "{$dom->inbox}@{$dom->domain}", 'imap_password' => AliasMailboxFactory::inboxPassword($dom),
                'imap_encryption' => 'ssl',
            ]);

            try {
                $emails = $reader->fetchUnseen($inbox, (int) config('services.email_receive.per_mailbox_limit', 20));
            } catch (\Throwable $e) {
                Log::warning('ReceiveAliasInboxJob: IMAP fetch failed', ['domain' => $dom->domain, 'error' => mb_substr($e->getMessage(), 0, 300)]);
                return;
            }

            $counts = ['foreign' => 0, 'error' => 0];
            foreach ($emails as $uid => $email) {
                try {
                    $senderId = $this->aliasSender(array_values(array_filter(array_merge([$email->originalTo], $email->recipients))));
                    $outcome = $senderId ? $router->routeAlias($senderId, $email) : 'foreign';
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                    $reader->markSeen($uid);
                } catch (\Throwable $e) {
                    $counts['error']++;
                    Log::error('ReceiveAliasInboxJob: route failed', [
                        'domain' => $dom->domain, 'message_id' => $email->messageId, 'error' => mb_substr($e->getMessage(), 0, 300),
                    ]);
                }
            }
            if ($emails !== []) {
                Log::info('ReceiveAliasInboxJob: processed', ['domain' => $dom->domain, 'fetched' => count($emails), 'counts' => $counts]);
            }
        } finally {
            $lock->release();
        }
    }

    /** @param string[] $addresses */
    private function aliasSender(array $addresses): ?int
    {
        $addresses = array_values(array_unique(array_map('mb_strtolower', $addresses)));
        if ($addresses === []) {
            return null;
        }
        // Порядок важен: X-Original-To (первый) точнее To/Cc.
        $map = DB::table('alias_mailboxes')->whereIn('email', $addresses)->whereNotNull('sender_id')->pluck('sender_id', 'email');
        foreach ($addresses as $a) {
            if (isset($map[$a])) {
                return (int) $map[$a];
            }
        }

        return null;
    }
}
