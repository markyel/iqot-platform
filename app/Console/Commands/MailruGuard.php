<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Адресаты на почте Mail.ru — только с чистых доменов ящиков (решение владельца 23.11.2026).
 *
 * После восстановления из январской базы в рассылку вернулись ящики на старых .ru-доменах (fastmailr.ru, postserve.ru,
 * tomailbox.ru, …), которые до сбоя были заменены именно из-за отказов Mail.ru: в первый день 23.11 — 25–42% отказов
 * «550 spam message rejected» с каждого такого домена, у stroy-lift.ru и eespc.ru — ноль.
 *
 * Поставщикам, чья почта на Mail.ru (MX *.mail.ru: mail.ru/bk.ru/list.ru/inbox.ru и корпоративная почта на Mail.ru),
 * ставится пауза «получатель × наш домен» (SenderPauses, scope=domain) для каждого домена из
 * services.email_dispatch.mailru_blocked_sender_domains — её уже учитывают план дня, мониторинг и диспетчер, письма
 * таким адресатам уходят только с остальных ящиков. Их уже стоящие в очереди письма со сгоревших доменов снимаются
 * (иначе ждали бы конца паузы). Пауза продлевается каждым прогоном (ежедневно), снять — убрать домен из списка.
 */
class MailruGuard extends Command
{
    protected $signature = 'emails:mailru-guard {--dry-run} {--force}';

    protected $description = 'Адресаты на Mail.ru: пауза для сгоревших доменов ящиков, снятие их писем из очереди';

    private const CONN = 'reports';

    public function handle(): int
    {
        $blocked = array_values(array_filter(array_map(fn ($d) => mb_strtolower(trim($d)),
            (array) config('services.email_dispatch.mailru_blocked_sender_domains', []))));
        if ($blocked === []) {
            $this->info('Список сгоревших доменов пуст (EMAILS_MAILRU_BLOCKED_SENDER_DOMAINS) — ничего не делаю.');

            return self::SUCCESS;
        }
        if (!$this->option('force') && !config('services.email_dispatch.enabled')) {
            $this->warn('Рассылка выключена (EMAILS_DISPATCH_ENABLED=false). --force для ручного прогона.');

            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $days = max(1, (int) config('services.email_dispatch.mailru_pause_days', 3));
        $db = DB::connection(self::CONN);

        $suppliers = $db->table('suppliers')->where('is_active', 1)->where('notify_email', 1)
            ->whereNotNull('email')->where('email', 'like', '%@%')->get(['id', 'email']);
        $hosted = [];
        foreach ($suppliers as $s) {
            $email = mb_strtolower(trim((string) $s->email));
            if (self::onMailru(substr((string) strrchr($email, '@'), 1))) {
                $hosted[$email] = (int) $s->id;
            }
        }

        $now = now();
        $until = $now->copy()->addDays($days)->toDateTimeString();
        $pauses = 0;
        foreach ($hosted as $email => $supplierId) {
            foreach ($blocked as $domain) {
                $pauses++;
                if ($dry) {
                    continue;
                }
                $db->table('supplier_sender_pauses')->updateOrInsert(
                    ['supplier_id' => $supplierId, 'pause_key' => 'mailru:' . $domain],
                    ['recipient_email' => $email, 'scope' => 'domain', 'sender_id' => null, 'sender_domain' => $domain,
                        'signals' => 1, 'paused_until' => $until, 'last_signal_at' => $now,
                        'last_reason' => 'mailru: domain rejected as spam', 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // Уже стоящие письма этим адресатам со сгоревших доменов — снять (иначе ждут конца паузы).
        $cancelled = 0;
        foreach (array_chunk(array_keys($hosted), 500) as $chunk) {
            $q = $db->table('email_queue as q')->join('senders as s', 's.id', '=', 'q.sender_id')
                ->where('q.status', 'pending')->whereIn(DB::raw('LOWER(q.to_email)'), $chunk)
                ->whereIn(DB::raw("LOWER(SUBSTRING_INDEX(s.email, '@', -1))"), $blocked);
            $cancelled += $dry ? $q->count() : $q->update(['q.status' => 'cancelled',
                'q.error_message' => 'mailru: sender domain rejected as spam', 'q.updated_at' => $now]);
        }

        $this->info('Адресатов на Mail.ru: ' . count($hosted) . " из {$suppliers->count()}, пауз «получатель × домен» на {$days} дн.: {$pauses}, "
            . "снято писем из очереди: {$cancelled}" . ($dry ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    /** Почта домена на серверах Mail.ru (MX *.mail.ru). Кеш сутки. */
    public static function onMailru(string $domain): bool
    {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }
        if (in_array($domain, ['mail.ru', 'bk.ru', 'list.ru', 'inbox.ru', 'internet.ru', 'mail.ua', 'vk.com'], true)) {
            return true;
        }

        return (bool) Cache::remember('mx:mailru:' . $domain, 86400, function () use ($domain) {
            $records = @dns_get_record($domain, DNS_MX) ?: [];
            foreach ($records as $r) {
                if (self::isMailruHost((string) ($r['target'] ?? ''))) {
                    return 1;
                }
            }

            return 0;
        });
    }

    public static function isMailruHost(string $host): bool
    {
        $host = rtrim(mb_strtolower(trim($host)), '.');

        return $host === 'mail.ru' || str_ends_with($host, '.mail.ru');
    }
}
