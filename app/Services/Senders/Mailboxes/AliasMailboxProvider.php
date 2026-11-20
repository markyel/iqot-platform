<?php

namespace App\Services\Senders\Mailboxes;

/**
 * Поставщик ящиков для ветки «ящик на батч» (docs/alias-mailbox-design.md): кроме создания/удаления — пересылка всей
 * почты ящика в общий ящик домена (копию ящик не хранит) и катч-олл домена (поздние ответы на удалённые ящики).
 * С 20.11.2026 — Beget и хостинг REG.RU (до этого ветка жила только на API Beget, лежавшем после аварии 02.11).
 */
interface AliasMailboxProvider extends MailboxProvider
{
    public function mailboxExists(string $domain, string $local): bool;

    public function setPassword(string $domain, string $local, string $password): void;

    /** Вся почта ящика — в $target (полный адрес), ящик копию не хранит. */
    public function forwardAndDelete(string $domain, string $local, string $target): void;

    /** Письма на несуществующие адреса домена (в т.ч. удалённые ящики) — в $inbox@$domain. */
    public function setCatchAll(string $domain, string $inbox): void;
}
