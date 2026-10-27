<?php

namespace Tests\Unit;

use App\Services\Senders\DomainHealth;
use App\Services\Senders\MailboxReplenisher;
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
}
