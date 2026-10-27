<?php

namespace Tests\Unit;

use App\Services\Regru\RegruApi;
use App\Services\Senders\DomainHealth;
use App\Services\Senders\MailboxReplenisher;
use App\Services\Senders\Mailboxes\RegruMailboxProvider;
use Tests\TestCase;

class MailboxReplenishTest extends TestCase
{
    public function test_live_domain(): void
    {
        $this->assertTrue(DomainHealth::isLive(['sent' => 533, 'spam_pct' => 1.1], 3.0, 50));   // postserve.store
        $this->assertFalse(DomainHealth::isLive(['sent' => 206, 'spam_pct' => 26.2], 3.0, 50)); // mailnova.ru — горит
        $this->assertFalse(DomainHealth::isLive(['sent' => 0, 'spam_pct' => 0.0], 3.0, 50));    // не слал — судить не по чему
        $this->assertSame('postserve.store', DomainHealth::domainOf('Ivan.Petrov@PostServe.store'));
    }

    public function test_allocate_fills_emptiest_cleanest_domains_within_limits(): void
    {
        $domains = [
            'a.store' => ['occupied' => 7, 'spam_pct' => 0.5],
            'b.store' => ['occupied' => 4, 'spam_pct' => 1.5],
            'c.store' => ['occupied' => 4, 'spam_pct' => 0.9],
            'd.store' => ['occupied' => 8, 'spam_pct' => 0.0],   // потолок домена
        ];
        // Дневной лимит 2: оба — на самые свободные, при равенстве — чище.
        $this->assertSame(['c.store' => 1, 'b.store' => 1], MailboxReplenisher::allocate(10, $domains, 2, 8));
        // Дефицит меньше лимита.
        $this->assertSame(['c.store' => 1], MailboxReplenisher::allocate(1, $domains, 2, 8));
        // Нет дефицита — ничего.
        $this->assertSame([], MailboxReplenisher::allocate(0, $domains, 2, 8));
        // Места нет нигде.
        $this->assertSame([], MailboxReplenisher::allocate(5, ['x' => ['occupied' => 8, 'spam_pct' => 0.0]], 2, 8));
        // Места хватает только на один в домене.
        $this->assertSame(['a.store' => 1], MailboxReplenisher::allocate(5, ['a.store' => $domains['a.store']], 2, 8));
    }

    public function test_allocate_seed_cap_and_account_room(): void
    {
        // Засев нового домена: свой потолок 2, хотя у живых — 8.
        $seed = ['eespc.ru' => ['occupied' => 0, 'spam_pct' => 0.0, 'max' => 2, 'account' => 'regru:server269']];
        $this->assertSame(['eespc.ru' => 2], MailboxReplenisher::allocate(10, $seed, 5, 8));
        $this->assertSame([], MailboxReplenisher::allocate(10, ['eespc.ru' => ['occupied' => 2] + $seed['eespc.ru']], 5, 8));

        // Два домена одного аккаунта делят его место: room 1 — один ящик на оба; Beget без ограничения добирает.
        $domains = $seed + [
            'eespc.online' => ['occupied' => 0, 'spam_pct' => 0.0, 'max' => 2, 'account' => 'regru:server269'],
            'post.store' => ['occupied' => 5, 'spam_pct' => 1.0, 'account' => 'beget'],
        ];
        $plan = MailboxReplenisher::allocate(10, $domains, 4, 8, ['regru:server269' => 1]);
        $this->assertSame(1, ($plan['eespc.ru'] ?? 0) + ($plan['eespc.online'] ?? 0));
        $this->assertSame(3, $plan['post.store']);
        // Места на аккаунте нет вовсе.
        $this->assertSame([], MailboxReplenisher::allocate(10, $seed, 4, 8, ['regru:server269' => 0]));
    }

    public function test_regru_account_room(): void
    {
        // 2700 писем/сутки на аккаунт при потолке ящика 250 → не больше 10 ящиков.
        $this->assertSame(10, RegruMailboxProvider::room(2700, 250, 0));
        $this->assertSame(1, RegruMailboxProvider::room(2700, 250, 9));
        $this->assertSame(0, RegruMailboxProvider::room(2700, 250, 12));
        // Без лимита сервера — не создаём.
        $this->assertSame(0, RegruMailboxProvider::room(0, 250, 0));
        // Лимит меньше потолка ящика — всё же один.
        $this->assertSame(1, RegruMailboxProvider::room(100, 250, 0));
    }

    public function test_regru_api_parsers(): void
    {
        $elems = [['name' => ['$' => 'test@eespc.ru']], ['name' => 'Ivan.Petrov@EESPC.ru'], ['name' => 'info@partleader.ru']];
        $this->assertSame(['test', 'ivan.petrov'], RegruApi::localsOf($elems, 'eespc.ru'));
        $this->assertSame('v=DMARC1; p=none; aspf=r; sp=none', RegruApi::txtOf('_dmarc.eespc.ru. IN TXT "v=DMARC1; p=none; aspf=r; sp=none"'));
        $this->assertNull(RegruApi::txtOf(''));
        $p = RegruApi::password();
        $this->assertSame(18, strlen($p));
        $this->assertMatchesRegularExpression('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z0-9]+$/', $p);
    }
}
