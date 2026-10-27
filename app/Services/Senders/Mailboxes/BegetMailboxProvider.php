<?php

namespace App\Services\Senders\Mailboxes;

use App\Services\AliasExp\BegetMailApi;

/**
 * Beget: домен наш, если почтовое API отвечает по нему. Домены без ящиков API не перечисляет — засева нет,
 * новые домены Beget входят в пул вручную. Лимита на аккаунт нет (троттл по серверу — в отправке).
 */
class BegetMailboxProvider implements MailboxProvider
{
    /** @var array<string,string[]|false> домен => ящики (false — не наш) */
    private array $cache = [];

    public function __construct(private readonly BegetMailApi $api)
    {
    }

    public function name(): string
    {
        return 'beget';
    }

    public function isConfigured(): bool
    {
        return $this->api->isConfigured();
    }

    public function manages(string $domain): bool
    {
        return $this->load($domain) !== false;
    }

    public function seedDomains(): array
    {
        return [];
    }

    public function mailboxes(string $domain): array
    {
        return $this->load($domain) ?: [];
    }

    public function createMailbox(string $domain, string $local, string $password): void
    {
        $this->api->createMailbox($domain, $local, $password);
        unset($this->cache[$domain]);
    }

    public function dropMailbox(string $domain, string $local): void
    {
        $this->api->dropMailbox($domain, $local);
        unset($this->cache[$domain]);
    }

    public function password(): string
    {
        return BegetMailApi::password();
    }

    public function smtp(string $domain): string
    {
        return (string) config('services.mailbox_replenish.smtp', 'smtp.beget.com:465');
    }

    public function imap(string $domain): string
    {
        return (string) config('services.mailbox_replenish.imap', 'imap.beget.com:993');
    }

    public function roomLeft(string $domain): ?int
    {
        return null;
    }

    public function account(string $domain): string
    {
        return 'beget';
    }

    /** @return string[]|false */
    private function load(string $domain): array|false
    {
        if (!array_key_exists($domain, $this->cache)) {
            try {
                $this->cache[$domain] = array_map(static fn ($m) => mb_strtolower((string) ($m['mailbox'] ?? '')), $this->api->listMailboxes($domain));
            } catch (\Throwable) {
                $this->cache[$domain] = false; // не наш домен в Beget — API отвечает ошибкой
            }
        }

        return $this->cache[$domain];
    }
}
