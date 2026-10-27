<?php

namespace App\Services\Senders\Mailboxes;

/**
 * Поставщик почтовых ящиков для пополнения основного пула (MailboxReplenisher): Beget, хостинг REG.RU (ISPmanager).
 * Домен обслуживает один поставщик; адреса SMTP/IMAP и место под ящики — свои у каждого.
 */
interface MailboxProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /** Домен наш у этого поставщика (можно создавать ящики). */
    public function manages(string $domain): bool;

    /**
     * Домены поставщика, на которых ещё может не быть ни одного ящика — кандидаты на «засев» нового домена.
     *
     * @return string[]
     */
    public function seedDomains(): array;

    /** @return string[] локальные части существующих ящиков домена */
    public function mailboxes(string $domain): array;

    public function createMailbox(string $domain, string $local, string $password): void;

    public function dropMailbox(string $domain, string $local): void;

    public function password(): string;

    /** host:port для отправителя. */
    public function smtp(string $domain): string;

    public function imap(string $domain): string;

    /** Сколько ещё ящиков осмысленно завести на аккаунте домена (лимит отправки аккаунта); null — без ограничения. */
    public function roomLeft(string $domain): ?int;

    /** Ключ аккаунта: ящики одного аккаунта делят его место (roomLeft). */
    public function account(string $domain): string;
}
