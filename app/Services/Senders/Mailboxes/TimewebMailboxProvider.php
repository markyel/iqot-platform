<?php

namespace App\Services\Senders\Mailboxes;

use App\Services\Regru\RegruApi;
use App\Services\Timeweb\TimewebMailApi;
use App\Support\Senders\SmtpServerCaps;
use Illuminate\Support\Facades\DB;

/**
 * Почта Timeweb Cloud (23.11.2026): независим и от Beget, и от группы «Рунити» (REG.RU). Домены — явным списком
 * services.timeweb.pool_domains (основной пул) и alias_domains с provider=timeweb (ветка «ящик на батч»); почтовый
 * домен (MX, SPF, DKIM) заводится в панели Timeweb заранее. Отправка — smtp.timeweb.ru:465, приём — imap.timeweb.ru:993.
 * У аккаунта суточный лимит писем (2 000 на весь аккаунт): ящиков держим не больше лимит / потолок прогрева ящика
 * (SmtpServerCaps для smtp.timeweb.ru), без заданного лимита новых не создаём — как у REG.RU.
 */
class TimewebMailboxProvider implements AliasMailboxProvider
{
    public const SMTP_HOST = 'smtp.timeweb.ru';

    public const IMAP_HOST = 'imap.timeweb.ru';

    public function __construct(private readonly TimewebMailApi $api, private readonly bool $alias = false)
    {
    }

    public static function forAlias(TimewebMailApi $api): self
    {
        return new self($api, true);
    }

    public function name(): string
    {
        return 'timeweb';
    }

    public function isConfigured(): bool
    {
        // Ветке домен добавляют явно (alias-exp domain-add --provider=timeweb) — достаточно токена.
        return $this->api->isConfigured() && ($this->alias || $this->domains() !== []);
    }

    public function manages(string $domain): bool
    {
        return in_array(mb_strtolower($domain), $this->domains(), true);
    }

    public function seedDomains(): array
    {
        return $this->domains();
    }

    public function mailboxes(string $domain): array
    {
        return $this->api->mailboxes($domain);
    }

    public function createMailbox(string $domain, string $local, string $password): void
    {
        $this->api->createMailbox($domain, $local, $password);
    }

    public function dropMailbox(string $domain, string $local): void
    {
        $this->api->dropMailbox($domain, $local);
    }

    public function password(): string
    {
        return RegruApi::password();
    }

    public function mailboxExists(string $domain, string $local): bool
    {
        return in_array(mb_strtolower($local), $this->mailboxes($domain), true);
    }

    public function setPassword(string $domain, string $local, string $password): void
    {
        $this->api->setPassword($domain, $local, $password);
    }

    public function forwardAndDelete(string $domain, string $local, string $target): void
    {
        $this->api->forwardAndDelete($domain, $local, $target);
    }

    public function setCatchAll(string $domain, string $inbox): void
    {
        $this->api->setCatchAll($domain, $inbox . '@' . $domain);
    }

    public function smtp(string $domain): string
    {
        return self::SMTP_HOST . ':465';
    }

    public function imap(string $domain): string
    {
        return self::IMAP_HOST . ':993';
    }

    public function roomLeft(string $domain): ?int
    {
        $cap = SmtpServerCaps::caps()[self::SMTP_HOST] ?? 0;

        return RegruMailboxProvider::room($cap, (int) config('services.email_warmup.cap', 250), DB::connection('reports')
            ->table('senders')->whereRaw('LOWER(smtp_server) = ?', [self::SMTP_HOST])->where('is_active', 1)->count());
    }

    public function account(string $domain): string
    {
        return 'timeweb';
    }

    /** @return string[] */
    private function domains(): array
    {
        $pool = array_map('mb_strtolower', (array) config('services.timeweb.pool_domains', []));
        try {
            $alias = array_map('mb_strtolower', DB::table('alias_domains')->where('provider', 'timeweb')->pluck('domain')->all());
        } catch (\Throwable) {
            $alias = [];
        }

        return array_values(array_unique($this->alias ? $alias : array_diff($pool, $alias)));
    }
}
